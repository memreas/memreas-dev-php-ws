<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class DeletePhoto {

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
        echo 'm';
$data = simplexml_load_string($_POST['xml']);
$mediaid = trim($data->deletephoto->mediaid);
//$photourl =  trim($data->deletephoto->photourl);
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$xml_output .= "<deletephotoresponse>";
//$photoname=1;
if(isset ($mediaid) && !empty ($mediaid)){
$seldata = "select * from media where media_id='$mediaid'";
//$resseldata = mysql_query($seldata);
 $statement = $this->dbAdapter->createStatement($seldata);
    $resseldata = $statement->execute();
             // $row = $result->current();
//print_r($resseldata);exit;

if ($resseldata->count() > 0) {
    $selrow = mysql_fetch_array($resseldata);
    $json_array = json_decode($selrow['metadata'], true);
            if(isset($json_array['type']['image'])){
                $imagename=  basename($json_array['S3_files']['path']);   
            }    
    $query = "DELETE FROM media where media_id='$mediaid'";
   // $result = mysql_query($query);
    $statement = $this->dbAdapter->createStatement($query);
    $result = $statement->execute();
            //$row = $result->current();

    if (mysql_affected_rows() > 0) {

        $xml_output .= "<status>success</status>";
        $xml_output.="<message>Photo deleted successfully</message>";
        
//        if(isset($imagename))
//        unlink('../public/userimage/'. $imagename);
        
    } else {
        $xml_output .= "<status>failure</status><message>No record found</message>";
    }
}else {
        $xml_output .= "<status>failure</status><message>Given media id is wrong.</message>";
    }
}else {
        $xml_output .= "<status>failure</status><message>Please check that you have given media id.</message>";
    }
$xml_output .= "</deletephotoresponse>";
$xml_output .= "</xml>\n";
echo $xml_output;
       
}
}
?>
