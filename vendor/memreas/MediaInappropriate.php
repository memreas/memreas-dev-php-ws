<?php
namespace memreas;

use Zend\Session\Container;

use Application\Model\MemreasConstants; 
use memreas\AWSManager;
use memreas\UUID;

class MediaInappropriate {

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
       
$data = simplexml_load_string($_POST['xml']);
//echo "<pre>";
//print_r($data);
$message = ' ';
$media_id = trim($data->mediainappropriate->media_id);
$is_appropriate = trim($data->mediainappropriate->is_appropriate);
$event_id = trim($data->mediainappropriate->event_id);
$user_id = trim($data->mediainappropriate->user_id);
if (!isset($media_id) || empty($media_id)) {
    $message = 'media_id is empty';
    $status = 'Failure';
} else{
 $q = "UPDATE comment SET inappropriate= $is_appropriate WHERE media_id ='$media_id'";
//$result = mysql_query($q);
    $statement = $this->dbAdapter->createStatement($q);
            $result = $statement->execute();
         
if (!$result) {
    $status = 'Failure';
    $message = mysql_error();
} else if(mysql_affected_rows()<=0){
//    $status = 'Failure';
//    $message = "Error in updation Plz check arguments";
    
      // $uuid=  getUUID();
        $uuid = UUID::getUUID($this->dbAdapter);

     $query_comment = "insert into comment(comment_id,media_id,user_id,type,text, event_id,inappropriate,create_time,update_time)
                    values('$uuid','$media_id','$user_id','text','','$event_id',$is_appropriate,'$time','$time')";
     //$result1 = mysql_query($query_comment);
        $statement = $this->dbAdapter->createStatement($query_comment);
            $result1 = $statement->execute();
            // $row = $result->current();

            

     if (!$result1) {
    $status = 'Failure';
    $message = mysql_error();
     }
    else
    {
    $status = 'Success';
    $message = 'Appropriate flag Successfully Updated';
}
}else
    {
    $status = 'Success';
    $message = 'Appropriate flag Successfully Updated';
}

}

header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";

$xml_output.= "<mediainappropriateresponse>";
$xml_output.= "<status>$status</status>";
$xml_output.= "<message>$message</message>";
$xml_output.= "</mediainappropriateresponse>";
$xml_output.= "</xml>";
echo $xml_output;
}
}
?>
