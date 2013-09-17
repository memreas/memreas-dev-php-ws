<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class CountViewEvent {

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
$user_id = trim($data->countviewevent->user_id);
$is_my_event = trim($data->countviewevent->is_my_event);
$is_friend_event = trim($data->countviewevent->is_friend_event);
$is_public_event = trim($data->countviewevent->is_public_event);
$limit = trim($data->countviewevent->limit);
$limit = 10;
$error_flag=0;
$limit_old = $limit;
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";

$totlecount = 0;
$query_event = "select e 
                from Application\Entity\Event e 
                where e.user_id='$user_id'";
$q = "SELECT  event.event_id ,event.name
          FROM event 
          inner join user_friend as uf 
          on uf.friend_id=event.user_id 
          where uf.user_id='$user_id'";
$q_public = "select e from Application\Entity\Event e where e.public=1";

//-----------------------------------pagination----------------------
    if ($is_my_event) {
        //$resultset = mysql_query($query_event);
        // $statement = $this->dbAdapter->createStatement($query_event);
          //  $resultset = $statement->execute();
          //  $row = $result->current();
        $statement = $this->dbAdapter->createQuery($query_event);
  $resultset = $statement->getResult();
        

    } 
    else    if ($is_friend_event) {
       // $resultset = mysql_query($q);
        // $statement1 = $this->dbAdapter->createStatement($q);
         //   $resultset = $statement1->execute();
            //$row = $result->current();
        
        $statement = $this->dbAdapter->createQuery($q);
  $resultset = $statement->getResult();

    } 
    else    if ($is_public_event) {
        //$resultset = mysql_query($q_public
        // $statement2 = $this->dbAdapter->createStatement($q_public);
          //  $resultset = $statement2->execute();
            //$row = $result->current();
        $statement = $this->dbAdapter->createQuery($q_public);
  $resultset = $statement->getResult();

    }
    if($resultset && count($resultset)>0)
    {
        $totlecount = count($resultset);
    if ($totlecount > $limit)
        $nopage = ceil(($totlecount) / $limit);
    else
        $nopage = 1;
    
        $xml_output .= "<xml>";
        $xml_output .= "<countvieweventsresponse>";
        $xml_output .= "<nopage>$nopage</nopage>";
        $xml_output .= "<norecords>$totlecount</norecords>";
        $xml_output .= "</countvieweventsresponse>";
        $xml_output .= "</xml>";
    }else if(!$resultset) { 
        $error_flag=1;
        $message=  mysql_error();
    }else{$error_flag=2;
        $message="No record found";
    }
    if($error_flag){
        $xml_output .= "<xml>";
        $xml_output .= "<countvieweventsresponse>";
        $xml_output .= "<status>Failure</status>";
        $xml_output .="<message>" .$message. "</message>";
        $xml_output .= "</countvieweventsresponse>";
        $xml_output .= "</xml>";
    }

    echo $xml_output;


           }

}

?>
