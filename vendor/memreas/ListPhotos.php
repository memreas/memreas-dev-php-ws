<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ListPhotos {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('memreasdevdb');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $error_flag=0;
$message='';
$data = simplexml_load_string($_POST['xml']);
$userid = trim($data->listphotos->userid);
$device_id=trim($data->listphotos->device_id);
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$xml_output .= "<listphotosresponse>";
if (isset($userid) && !empty($userid)) {

//    echo $query="SELECT m.media_id,m.metadata
//FROM event_media AS em
//RIGHT JOIN event AS e ON e.event_id = em.event_id
//LEFT JOIN media AS m ON m.media_id = em.media_id
//WHERE e.user_id = '$userid'
//OR m.user_id = '$userid'
//ORDER BY m.create_date DESC ";
    
    $query_user_media = "SELECT * FROM media where user_id ='$userid' ORDER BY create_date DESC";
    $query_user_event_media="SELECT m.media_id,m.metadata
FROM event_media AS em
JOIN event AS e ON e.event_id = em.event_id
JOIN media AS m ON m.media_id = em.media_id
WHERE e.user_id = '$userid' and m.user_id!='$userid'
ORDER BY m.create_date DESC";
   // $result = mysql_query($query_user_media) or die(mysql_error());
    //$result1 = mysql_query($query_user_event_media) or die(mysql_error());
    $statement = $this->dbAdapter->createStatement($query_user_media);
            $result = $statement->execute();
          //  $row = $result->current();
        $statement1 = $this->dbAdapter->createStatement($query_user_event_media);
            $result1 = $statement1->execute();
            $row = $result1->current();
    

    if ($result->count() > 0 || $result1->count() > 0) {
        $count = 0;
        while ($row = mysql_fetch_assoc($result)) {
            $json_array = json_decode($row['metadata'], true);
            if (isset($json_array['type']['image'])) {
                $count++;
                $meta[$count]['media_id']=$row['media_id'];
                $meta[$count]['url'] = $json_array['S3_files'];
                $meta[$count]['download']= $json_array['local_filenames']['device'];
            }
            
        }
         while ($row = mysql_fetch_assoc($result1)) {
            $json_array = json_decode($row['metadata'], true);
            if (isset($json_array['type']['image'])) {
                $count++;
                $meta[$count]['media_id']=$row['media_id'];
                $meta[$count]['url'] = $json_array['S3_files'];
                $meta[$count]['download']= $json_array['local_filenames']['device'];
            }
            
        }
        
//        echo '<pre>';
//                print_r($meta);//exit;
        $xml_output .= "<status>success</status>";
        $xml_output .= "<noofimage>$count</noofimage>";
        $xml_output .= "<images>";
        foreach ($meta as $metadata) {
                
            $xml_output .= "<image>";
            $xml_output .= "<media_id>" . $metadata['media_id'] . "</media_id>";
            $xml_output .= "<name>" . CLOUDFRONT_DOWNLOAD_HOST.$metadata['url']['path'] . "</name>";
            $download=0;
//                print_r();exit;
                foreach ($metadata['download'] as $value) {
                    $str=$userid.'_'.$device_id;
                    if(strcasecmp($value,$str )==0)
                            $download=1;
                }
            $xml_output.="<is_download>".$download."</is_download>";
            $xml_output .= "</image>";

        }
        $xml_output .= "</images>";
    } 
    //-----------------for users event
    if($result1->count()==0 && $result->count()==0){
        $error_flag=2;
        $message="no record found";

}
if($error_flag){
    $xml_output .= "<status>failure</status>";
    $xml_output .= "<noofimage>0</noofimage>";
    $xml_output .="<message>$message</message>";
    $xml_output .= "<images>";
    $xml_output .= "<image><media_id></media_id>";
    $xml_output .= "<name></name>";
    $xml_output .= "</image>";
    $xml_output .= "</images>";
}
} else {
    $xml_output .= "<status>failure</status>";
    $xml_output .= "<noofimage>0</noofimage>";
    $xml_output .="<message>User id is not given.</message>";
    $xml_output .= "<images>";
    $xml_output .= "<image><media_id></media_id>";
    $xml_output .= "<name></name>";
    $xml_output .= "</image>";
    $xml_output .= "</images>";
}

$xml_output .="</listphotosresponse>";
$xml_output .="</xml>";
echo $xml_output;

    }

}

?>
