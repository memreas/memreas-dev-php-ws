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
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
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
  $qb = $this->dbAdapter->createQueryBuilder();
$qb->select('m.media_id','m.metadata');
        $qb->from('Application\Entity\EventMedia', 'em');$qb->join('Application\Entity\Event', 'e', 'WITH', 'em.event_id = e.event_id');
        $qb->join('Application\Entity\Media', 'm', 'WITH', 'm.media_id = em.media_id');
        $qb->where('e.user_id = ?1 and m.user_id!=?1');
        $qb->orderBy('m.create_date' ,'DESC');
         $qb->setParameter(1,$userid);
       
        
$result1 = $qb->getQuery()->getResult();
  //echo '<pre>';print_r($result1);

    $query_user_media = "SELECT m FROM Application\Entity\Media m where m.user_id ='$userid' ORDER BY m.create_date DESC";
   /* $query_user_event_media="SELECT m.media_id,m.metadata
FROM Application\Entity\EventMedia AS em
JOIN Application\Entity\Event AS e 
 ON e.event_id = em.event_id*
JOIN Application\Entity\Media AS m 
ON m.media_id = em.media_id
WHERE e.user_id = '$userid' and m.user_id!='$userid'
ORDER BY m.create_date DESC";*/
   // $result = mysql_query($query_user_media) or die(mysql_error());
    //$result1 = mysql_query($query_user_event_media) or die(mysql_error());
  //  $statement = $this->dbAdapter->createStatement($query_user_media);
    //        $result = $statement->execute();
          //  $row = $result->current
    
    $statement = $this->dbAdapter->createQuery($query_user_media);
  $result = $statement->getResult();
    
        ///$statement1 = $this->dbAdapter->createStatement($query_user_event_media);
           // $result1 = $statement1->execute();
            //$row = $result1->current();
  //$statement = $this->dbAdapter->createQuery($query_user_event_media);
 // $result1 = $statement->getResult();
    

    if (count($result) > 0 || count($result1) > 0) {
        $count = 0;
        foreach  ( $result as $row) {
            $json_array = json_decode($row->metadata, true);
            if (isset($json_array['type']['image'])) {
                $count++;
                $meta[$count]['media_id']=$row->media_id;
                $meta[$count]['url'] = $json_array['S3_files'];
                $meta[$count]['download']= $json_array['local_filenames']['device'];
            }
            
        }
         foreach ( $result1 as $row1) {
            $json_array = json_decode($row1['metadata'], true);
            if (isset($json_array['type']['image'])) {
                $count++;
                $meta[$count]['media_id']=$row1['media_id'];
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
            $xml_output .= "<name>" . MemreasConstants:: CLOUDFRONT_DOWNLOAD_HOST.$metadata['url']['path'] . "</name>";
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
    if(count($result1)==0 && count($result)==0){
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
