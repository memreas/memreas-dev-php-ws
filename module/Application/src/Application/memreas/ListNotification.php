<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Notification;
use Application\memreas\Utility;


class ListNotification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        $this->url_signer = new MemreasSignedURL();
        // $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $oClass = new \ReflectionClass ('Application\Entity\Notification');
        $array = $oClass->getConstants ();
        unset($array['EMAIL'],$array['MEMREAS'],$array['NONMEMREAS']);
        $array = array_flip($array);
        
        $error_flag = 0;
        $message = '';
        $data = simplexml_load_string($_POST ['xml']);
        $userid = trim($data->listnotification->user_id);
        // $device_id=trim($data->listphotos->device_id);
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listnotificationresponse>";
        if (!empty($userid)) {

            $query_user_notification = "SELECT m FROM Application\Entity\Notification m   where m.user_id ='$userid' AND m.is_read = '0' AND m.notification_method > 0  ORDER BY m.update_time DESC";
            $statement = $this->dbAdapter->createQuery($query_user_notification);
            $result = $statement->getArrayResult();

            $xml_output .= "<notifications>";
            if (count($result) > 0) {

                $count = 0;
                $xml_output .= "<status>success</status>";
                $eventRepository = $this->dbAdapter->getRepository('Application\Entity\Event');
                foreach ($result as $row) {

                    $links = json_decode($row['links'], true);
                    $from_user_id = $links['from_id'];
                    $profile_pic = $this->dbAdapter->getRepository('Application\Entity\Media')->findOneBy(array(
                        'user_id' => $from_user_id,
                        'is_profile_pic' => 1
                    ));

                    $xml_output .= "<notification>";
                    if ($profile_pic)
                        $json_array = json_decode($profile_pic->metadata, true);
                    $url1 = MemreasConstants::ORIGINAL_URL. '/memreas/img/profile-pic.jpg';
                    if (!empty($json_array ['S3_files'] ['path']))
                        $url1 = $json_array ['S3_files'] ['path'];
                    $pic_79x80 = '';
                    if (!empty($json_array ['S3_files']['thumbnails'] ['79x80']))
                        $pic_79x80 = $json_array ['S3_files']['thumbnails'] ['79x80'];
                    $pic_448x306 = '';
                    if (!empty($json_array ['S3_files'] ['thumbnails']['448x306']))
                        $pic_448x306 = $json_array ['S3_files'] ['thumbnails']['448x306'];
                    $pic_98x78 = '';
                    if (!empty($json_array ['S3_files']['thumbnails'] ['98x78']))
                        $pic_98x78 =  $json_array ['S3_files']['thumbnails'] ['98x78'];
                    
                    $xml_output .= "<profile_pic><![CDATA[" . $this->url_signer->signArrayOfUrls($url1) . "]]></profile_pic>";
                    $xml_output .= "<profile_pic_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls($pic_79x80) . "]]></profile_pic_79x80>";
                    $xml_output .= "<profile_pic_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls($pic_448x306) . "]]></profile_pic_448x306>";
                    $xml_output .= "<profile_pic_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls($pic_98x78) . "]]></profile_pic_98x78>";

                    if (isset($links['event_id']))
                        $xml_output .= "<event_id>{$links['event_id']}</event_id>";
                    else
                        $xml_output .= "<event_id></event_id>";

                    $xml_output .= "<notification_id>{$row['notification_id']}</notification_id>";

                    $xml_output .= "<meta><![CDATA[{$row['meta']}]]></meta>";

                    $xml_output .= "<notification_type>{$row['notification_type']}</notification_type>";

                    if(isset($array[$row['notification_type']])){
                        $xml_output .= "<notification_type_text>".$array[$row['notification_type']]."</notification_type_text>";
                    }else{
                        $xml_output .= "<notification_type_text>NOT_FOUND</notification_type_text>";
                    }
                   
                    $xml_output .= "<notification_status>{$row['status']}</notification_status>";
                    $xml_output .= "<notification_updated>{$row['update_time']}</notification_updated>";
                    $xml_output .= '<updated_about>'.Utility::formatDateDiff($row['update_time']).'</updated_about>';


                    if ($row['notification_type'] == Notification::ADD_FRIEND_TO_EVENT ||
                        $row['notification_type'] == Notification::ADD_MEDIA

                        ) {

                        $eventMedia = $eventRepository->getEventMedia($links['event_id'], 1);
                        //echo'<pre>';print_r($eventMedia);
                        $eventMediaUrl='';
                        if(isset($eventMedia[0])){
                        $eventMediaUrl = $eventRepository->getEventMediaUrl($eventMedia[0]['metadata'], 'thumb');
                        $xml_output .= "<event_media_url><![CDATA[$eventMediaUrl]]></event_media_url>";
                        }
                    } else if ($row['notification_type'] == Notification::ADD_COMMENT) {
                        $commenId= isset($links['comment_id'])?$links['comment_id']:'0';
                        $comment = $this->dbAdapter->find('Application\Entity\Comment', $commenId);

                        if(!empty($comment)){
                            $xml_output .= "<comment><![CDATA[$comment->text]]></comment>";
                            $xml_output .= "<comment_id>$comment->comment_id</comment_id>";
                            $xml_output .= "<comment_time>$comment->create_time</comment_time>";
                            $xml_output .= "<media_id>$comment->media_id</media_id>";
                            $mediaOBj = $this->dbAdapter->find('Application\Entity\Media', $comment->media_id);
                            
                            //$eventRepository->getEventMediaUrl($eventMedia[0]['metadata'], 'thumb');
                             $url  = MemreasConstants::ORIGINAL_URL. '/memreas/img/pic-1.jpg';
                            if($mediaOBj){
                                $json_array = json_decode ( $mediaOBj->metadata, true );
                                $url =$eventRepository->getEventMediaUrl($mediaOBj->metadata, 'thumb');
                                
                                
                            } 
                            $path = $this->url_signer->fetchSignedURL ( $url );
                            $xml_output .= "<media_path><![CDATA[" . $path . "]]></media_path>";
                             
                            $xml_output .= "<media_type></media_type>";
                            if($json_array ['S3_files'] ['file_type']== 'video'){
                                $xml_output .= '<media_type>'.$json_array ['S3_files'] ['type'] ['video']['format'].'</media_type>';

                            } else if($json_array ['S3_files'] ['file_type']== 'audio'){
                                $xml_output .= '<media_type>'.$json_array ['S3_files'] ['type'] ['audio']['format'].'</media_type>';

                            }else if ($json_array ['S3_files'] ['file_type']== 'image') {
                                $xml_output .= '<media_type>'.$json_array ['S3_files'] ['type'] ['image']['format'].'</media_type>';
                            }



                        }else{
                            $xml_output .= "<comment><![CDATA[]]></comment>";
                            $xml_output .= "<comment_id></comment_id>";
                            $xml_output .= "<comment_time></comment_time>";
                            $xml_output .= "<media_id></media_id>";

                            
                        }
                    }

                    $xml_output .= "</notification>";
                }

                if (count($result) == 0) {
                    $xml_output .= "<status>failure</status>";
                    $xml_output .= "<message>No record found</message>";
                }
            } else {
                $xml_output .= "<status>failure</status>";
                $xml_output .= "<message>No record found</message>";
            }

            $xml_output .= "</notifications></listnotificationresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

}
