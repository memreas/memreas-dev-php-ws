<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\UUID;

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
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $error_flag = 0;
        $message = '';
        $data = simplexml_load_string($_POST['xml']);
        $userid = trim($data->listnotification->user_id);
//$device_id=trim($data->listphotos->device_id);
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listnotificationresponse>";
        if (isset($userid) && !empty($userid)) {

            $query_user_media = "SELECT m FROM Application\Entity\Notification m   where m.user_id ='$userid' AND m.is_read = '0' ORDER BY m.create_time DESC";
            $statement = $this->dbAdapter->createQuery($query_user_media);
           
            $result = $statement->getArrayResult();

           
    
    

            $xml_output .= "<notifications>";
            if (count($result) > 0) {
                $count = 0;
                $xml_output .= "<status>success</status>";
                 foreach ($result as $row) {
                    $profile_pic = $this->dbAdapter->getRepository('Application\Entity\Media')->findOneBy(array('user_id' => $row['user_id'],'is_profile_pic' => 1));
                    
                    
                    $xml_output .= "<notification>";
                    if($profile_pic)
                    $json_array = json_decode($profile_pic->metadata, true);
                           $url1 = null;
                           if (!empty($json_array['S3_files']['path']))
                                $url1 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['path'];
                            $pic_79x80 ='';
                            if (!empty($json_array['S3_files']['79x80']))
                                $pic_79x80 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['79x80'];
                            $pic_448x306 ='';
                            if (!empty($json_array['S3_files']['448x306']))
                                $pic_448x306 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['448x306'];
                            $pic_98x78='';
                            if (!empty($json_array['S3_files']['98x78']))
                                $pic_98x78 = MemreasConstants:: CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['98x78'];
                        
                        $xml_output.="<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";
                        $xml_output.="<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
                        $xml_output.="<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
                        $xml_output.="<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";
                        $json_array_link = json_decode($row['links'] , true);

                        $xml_output .= "<event_id>{$json_array_link['event_id']}</event_id>";
                        $xml_output .= "<notification_id>{$row['notification_id']}</notification_id>";

                    $xml_output .= "<meta>{$row['meta']}</meta>";
                    $xml_output .= "<notification_type>{$row['notification_type']}</notification_type>";
                    $xml_output .= "</notification>";
                }

            }
            
            if (count($result) == 0) {
                
                $xml_output .= "<status>failure</status>";

                $xml_output .="<message>No record founf</message>";
            }
        } else {
            $xml_output .= "<status>failure</status>";

            $xml_output .="<message>User id is not given.</message>";
        }

        $xml_output .="</notifications></listnotificationresponse>";
        $xml_output .="</xml>";
        echo $xml_output;
    }

}

?>
