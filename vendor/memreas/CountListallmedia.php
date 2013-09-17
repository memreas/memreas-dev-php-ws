<?php
namespace memreas;

use Zend\Session\Container;

use Application\Model\MemreasConstants; 
use memreas\AWSManager;
use memreas\UUID;

class CountListallmedia {

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
        $data = simplexml_load_string($_POST['xml']);
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$user_id = trim($data->countlistallmedia->user_id);
$event_id = trim($data->countlistallmedia->event_id);
$device_id = trim($data->countlistallmedia->device_id);
$error_flag=0;
$message='';
if(isset ($data->countlistallmedia->limit))
{
    $pagelimit = $data->countlistallmedia->limit;
}
 else {
    $pagelimit = 10;
}

$q = "select media.metadata ,media.media_id
    from Application\Entity\Media 
    inner join Application\Entity\EventMedia as em on media.media_id=em.media_id
    inner join Application\Entity\Event  as e on em.event_id=e.event_id
    where e.event_id='$event_id' ORDER BY `media`.`media_id` DESC";

//$result=  mysql_query($q);
//$statement = $this->dbAdapter->createStatement($q);
       //     $result = $statement->execute();
	   
	   $statement = $this->dbAdapter->createQuery($q);
  $result = $statement->getResult();

if(!$result)
{
    $error_flag=1;
    $message= mysql_error();
}
else{
    $result_data=count($result);
    if($result_data>0)
    {        
        $norecords=$result_data;
        
        if($result_data>$pagelimit)
            $nopage=ceil(($result_data)/$pagelimit);
        else
            $nopage=1;  
        $xml_output .= "<countlistallmediareponse>";
        $xml_output.= "<nopage>$nopage</nopage>";
        $xml_output.= "<norecords>$norecords</norecords>";
        $xml_output.= "</countlistallmediareponse>";
     }else
     {  $error_flag=2;         
        $message="No Record Found";
     }
}
if($error_flag)
{
        $xml_output.="<status>Failure</status>";
        $xml_output.="<message>$message</message>";
        $xml_output .= "<countlistallmediareponse>";
        $xml_output.= "<nopage></nopage>";
        $xml_output.= "<norecords></norecords>";
        $xml_output.= "</countlistallmediareponse>";
}
$xml_output .= "</xml>";
echo $xml_output;

}
}
?>
