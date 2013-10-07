<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ListAllmedia {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
error_log("ListAllmedia.__construct enter" . PHP_EOL);    
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
error_log("ListAllmedia.__construct exit" . PHP_EOL);    
    }

    public function exec() {

error_log("ListAllmedia.exec enter" . PHP_EOL);    
error_log("ListAllmedia.exec xml ---> " . $_POST['xml'] . PHP_EOL);    
        $data = simplexml_load_string($_POST['xml']);
error_log("ListAllmedia.exec data ---> " . print_r($data, true) . PHP_EOL);    
//echo "<pre>";
//print_r($data);
        $message = ' ';
        $containt = ' ';
        $user_id = trim($data->listallmedia->user_id);
        $event_id = trim($data->listallmedia->event_id);
        $device_id = trim($data->listallmedia->device_id);
        $error_flage = 0;


        if (isset($data->listallmedia->page)) {
            $page = $data->listallmedia->page;
        } else {
            $page = 1;
        }
        if (!isset($data->listallmedia->limit) || empty($data->listallmedia->limit) || $data->listallmedia->limit == 0)
            $limit = 10;
        else
            $limit = trim($data->listallmedia->limit);

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output.= "<xml>";
        $xml_output.= "<listallmediaresponse>";
        $xml_output.= "<medias>";

//$q = "select * from media where user_id='$user_id' and is_profile_pic='0' ORDER BY media.create_date DESC Limit $from ,$limit";
// changed by milap to fetch the profile pic also - with girish - 11-7-2013
        $from = ($page - 1) * $limit;
        if (empty($event_id)) {
error_log("ListAllmedia.exec if empty event_id ---> " . print_r($data, true) . PHP_EOL);    
            $q1 = "select m from Application\Entity\Media m where m.user_id='$user_id' ORDER BY m.create_date DESC";
            $statement = $this->dbAdapter->createQuery($q1);
            $statement->setMaxResults($limit);
            $statement->setFirstResult($from);
            $result = $statement->getResult();
        } else {
error_log("ListAllmedia.exec if empty event_id else  ---> " . print_r($data, true) . PHP_EOL);    
error_log("ListAllmedia.exec event_id  ---> $event_id" . PHP_EOL);    
            /* $q = "select media.metadata ,media.media_id
              from Application\Entity\Media  as media
              inner join Application\Entity\EventMedia as em on media.media_id=em.media_id
              inner join Application\Entity\Event as event on em.event_id=event.event_id
              where event.event_id='$event_id' ORDER BY media.create_date DESC Limit $from ,$limit"; */

            $qb = $this->dbAdapter->createQueryBuilder();
            $qb->select('media.media_id', 'media.metadata');
            $qb->from('Application\Entity\Media', 'media');
            $qb->join('Application\Entity\EventMedia', 'em', 'WITH', 'media.media_id = em.media_id');
            $qb->join('Application\Entity\Event', 'event', 'WITH', 'em.event_id = event.event_id');
            $qb->where('event.event_id = ?1');
            $qb->orderBy('media.create_date', 'DESC');
            $qb->setParameter(1, $event_id);
            $qb->setMaxResults($limit);
            $qb->setFirstResult($from);

error_log("ListAllmedia.exec qb->getQuery()->getSQL()  ---> " . print_r($qb->getQuery()->getSQL(), true) . PHP_EOL);    

            $result = $qb->getQuery()->getResult();
error_log("ListAllmedia.exec result  ---> " . print_r($result, true) . PHP_EOL);    
        }
//echo '<pre>';print_r($result); exit;
//echo $q;exit;
//$result = mysql_query($q);
        //$statement = $this->dbAdapter->createStatement($q);
        //         $result = $statement->execute();
        //       $row = $result->current();
//$statement = $this->dbAdapter->createQuery($q1);
        //$result = $statement->getResult();
        
        
            if (count($result) <= 0) {
                $error_flage = 2;
                $message = "No Record found for this Event";
            } else {
                $xml_output.="<page>$page</page>";
                $xml_output.="<status>Success</status>";
                $xml_output.="<message>Media List</message>";
                foreach ($result as  $row ){
                	
                    
                    $url79x80 = '';
                    $url448x306 = '';
                    $url98x78 = '';
                    $thum_url = '';
                    $is_download = 0;


                    $json_array = json_decode($row['metadata'], true);
error_log("ListAllmedia.exec row['metadata']  ---> " . $row['metadata'] . PHP_EOL);    

                    if (isset($json_array['type']['image']) && is_array($json_array['type']['image'])) {
                        $type = "image";
                        if (isset($json_array['S3_files']['79x80']))
                            $url79x80 = $json_array['S3_files']['79x80'];
                        if (isset($json_array['S3_files']['448x306']))
                            $url448x306 = $json_array['S3_files']['448x306'];
                        if (isset($json_array['S3_files']['98x78']))
                            $url98x78 = $json_array['S3_files']['98x78'];
                    }
                    else if (isset($json_array['type']['video']) && is_array($json_array['type']['video'])) {
                        $type = "video";
                        $thum_url = isset($json_array['S3_files']['1080p_thumbails'][0]['Full']) ? $json_array['S3_files']['1080p_thumbails'][0]['Full'] : ''; //get video thum
                        $url79x80 = isset($json_array['S3_files']['1080p_thumbails'][1]['79x80']) ? $json_array['S3_files']['1080p_thumbails'][1]['79x80'] : ''; //get video thum
                        $url448x306 = isset($json_array['S3_files']['1080p_thumbails'][2]['448x306']) ? $json_array['S3_files']['1080p_thumbails'][2]['448x306'] : ''; //get video thum
                        $url98x78 = isset($json_array['S3_files']['1080p_thumbails'][3]['98x78']) ? $json_array['S3_files']['1080p_thumbails'][3]['98x78'] : ''; //get video thum
                    } 
                    else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio'])) {
                        $type = "audio";
//                $rs_audio=mysql_query("select * from comment  WHERE media_id='".$row['media_id']."' and type='audio' limit 1") or die(mysql_error());
//                if(mysql_num_rows($rs_audio)>0)
                        continue;
                    }
                    else
                        $type = "Type not Mentioned";

                    $url = isset($json_array['S3_files']['path']) ? $json_array['S3_files']['path'] : '';
                    $media_name = basename($url);
                    if (isset($json_array['local_filenames']['device'])) {
                    	$device = (array) $json_array['local_filenames']['device'];
                    } else {
                    	$device = array();
                    }
//        echo "<pre>";print_r($device);
//            echo $user_id . '_' . $device_id;echo "<br/>".$row['media_id'];
//            
                    //if ($event_id != 0) {
                    if (in_array($user_id . '_' . $device_id, $device)) {
                        $is_download = 1;
                    }
//            }
                    $xml_output.="<media>";
                    $xml_output.="<media_id>" . $row['media_id'] . "</media_id>";
                    $xml_output.="<main_media_url><![CDATA[" . MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $url . "]]></main_media_url>";
                    $xml_output.="<is_downloaded>$is_download</is_downloaded>";
                    $xml_output.="<event_media_video_thum>";
                    $xml_output.=(!empty($thum_url)) ? MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $thum_url : '';
                    $xml_output.= "</event_media_video_thum>";
                    $xml_output.="<media_url_79x80><![CDATA[";
                    $xml_output.=(!empty($url79x80)) ? MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $url79x80 : '';
                    $xml_output.= "]]></media_url_79x80>";
                    $xml_output.="<media_url_98x78><![CDATA[";
                    $xml_output.=(!empty($url98x78)) ? MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $url98x78 : '';
                    $xml_output.= "]]></media_url_98x78>";
                    $xml_output.="<media_url_448x306><![CDATA[";
                    $xml_output.=(!empty($url448x306)) ? MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $url448x306 : '';
                    $xml_output.= "]]></media_url_448x306>";
                    $xml_output.="<type>$type</type>";
                    $xml_output.="<media_name><![CDATA[" . $media_name . "]]></media_name>";
                    $xml_output.="</media>";
                }
            }
        
        if ($error_flage) {
            $xml_output.="<status>Failure</status>";
            $xml_output.= "<message>$message</message>";
            $xml_output.="<media>";
            $xml_output.="<media_id></media_id>";
            $xml_output.="<is_downloaded></is_downloaded>";
            $xml_output.="<event_media_video_thum></event_media_video_thum>";
            $xml_output.="<media_url_79x80></media_url_79x80>";
            $xml_output.="<media_url_98x78></media_url_98x78>";
            $xml_output.="<media_url_448x306></media_url_448x306>";
            $xml_output.="<type></type>";
            $xml_output.="<media_name></media_name>";
            $xml_output.="</media>";
        }

        $xml_output .= "</medias>";
        $xml_output.="</listallmediaresponse>";
        $xml_output.="</xml>";
        echo $xml_output;
error_log("ListAllmedia.exec xml_output ---> " . $xml_output . PHP_EOL);    
    }

}

?>
