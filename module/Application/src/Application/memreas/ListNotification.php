<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Notification;

class ListNotification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        // $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $error_flag = 0;
        $message = '';
        $data = simplexml_load_string($_POST ['xml']);
        $userid = trim($data->listnotification->user_id);
        // $device_id=trim($data->listphotos->device_id);
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listnotificationresponse>";
        if (isset($userid) && !empty($userid)) {

            $query_user_notification = "SELECT m FROM Application\Entity\Notification m   where m.user_id ='$userid' AND m.is_read = '0' AND m.notification_method > 0  ORDER BY m.create_time DESC";
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
                    $url1 = null;
                    if (!empty($json_array ['S3_files'] ['path']))
                        $url1 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path'];
                    $pic_79x80 = '';
                    if (!empty($json_array ['S3_files'] ['79x80']))
                        $pic_79x80 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files']['thumbnails'] ['79x80'];
                    $pic_448x306 = '';
                    if (!empty($json_array ['S3_files'] ['448x306']))
                        $pic_448x306 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['thumbnails']['448x306'];
                    $pic_98x78 = '';
                    if (!empty($json_array ['S3_files'] ['98x78']))
                        $pic_98x78 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files']['thumbnails'] ['98x78'];

                    $xml_output .= "<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";
                    $xml_output .= "<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
                    $xml_output .= "<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
                    $xml_output .= "<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";

                    if (isset($links['event_id']))
                        $xml_output .= "<event_id>{$links['event_id']}</event_id>";
                    else
                        $xml_output .= "<event_id></event_id>";
                    $xml_output .= "<notification_id>{$row['notification_id']}</notification_id>";

                    $xml_output .= "<meta>{$row['meta']}</meta>";
                    $xml_output .= "<notification_type>{$row['notification_type']}</notification_type>";
                    $xml_output .= "<notification_status>{$row['status']}</notification_status>";
                    if ($row['notification_type'] == Notification::ADD_FRIEND_TO_EVENT) {

                        $eventMedia = $eventRepository->getEventMedia($links['event_id'], 1);
                        //echo'<pre>';print_r($eventMedia);
                        $eventMediaUrl='';
                        if(isset($eventMedia[0])){
                        $eventMediaUrl = $eventRepository->getEventMediaUrl($eventMedia[0]['metadata'], 'thumb');
                        $xml_output .= "<event_media_url><![CDATA[$eventMediaUrl]]></event_media_url>";
                        }
                    } else if ($row['notification_type'] == Notification::ADD_COMMENT) {

                        $commentRec = $this->dbAdapter->find('Application\Entity\Comment', $links['comment_id']);
                        if(isset($comment->text)){
                            
                            $xml_output .= "<comment><![CDATA[$comment->text]]></comment>";
                            $xml_output .= "<comment_id>$comment->comment_id</comment_id>";
                            $xml_output .= "<comment_time>$comment->create_time</comment_time>";
                        }else{
                            
                            $xml_output .= "<comment><![CDATA[]]></comment>";
                            $xml_output .= "<comment_id></comment_id>";
                            $xml_output .= "<comment_time></comment_time>";
                            
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
                $xml_output .= "<message>User id is not given.</message>";
            }

            $xml_output .= "</notifications></listnotificationresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

}
