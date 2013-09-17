<?php
namespace memreas;

use Zend\Session\Container;
use Application\Entity\Album;
use Application\Model\MemreasConstants; 
use memreas\AWSManager;
use memreas\UUID;

class ListGroup {

	protected $message_data;
	protected $memreas_tables;
	protected $service_locator; 
	protected $dbAdapter;

	public function __construct($message_data, $memreas_tables, $service_locator) {
error_log("Inside__construct...");
	   $this->message_data = $message_data;
	   $this->memreas_tables = $memreas_tables;
	   $this->service_locator = $service_locator;   
	   $this->dbAdapter = $service_locator
	                   ->get('doctrine.entitymanager.orm_default');

//	   ->get('memreasdevdb');
	   //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}


    
	public function exec() {
     
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml><listgroupresponse>";

$data = simplexml_load_string($_POST['xml']);
//echo "hi<pre>";
//print_r($data);
$message = ' ';
$error_flag = 0;
$user_id = trim($data->listgroup->user_id);

$qb = $this->dbAdapter->createQueryBuilder();

//$qb->select('em');
      //  $qb->from('Application\Entity\EventMedia', 'em');$qb->join('Application\Entity\Event', 'e', 'ON', 'em.event_id = e.event_id');
$query = $qb->getQuery()->getResult();

   /*$query_group = "SELECT group_id	
FROM group where user_id='$user_id'";
  */
    //$query_group ="select a.user_id from Application\Entity\Group as a ";
	//SELECT `group_id` FROM `group` WHERE 1

 // $query_group ="select a.group_id from Application\Entity\Group as a";
  // $query_group ="select a.id from Application\Entity\Album as a where a.title ='2134'";

//$result_group = mysql_query($query_group);
 //$statement = $this->dbAdapter->getRepository('Application\Entity\Album')->findAll();
 
 // $statement = $this->dbAdapter->find('Album',1);

echo '<pre>';print_r($query);exit;
//$statement = $this->dbAdapter->createQuery($query_group);
  //$result_group = $statement->getResult();
  		 // echo '<pre>';print_r($result_group);exit;
	//$q = $this->em->getConnection();
//$result_group = $q->fetchAll($query_group);

   
if (!$result_group) {
    $error_flag = 1;
    $message = mysql_error();
} else {
    if ($result_group->count() == 0) {
        $error_flag = 2;
        $message = "No Record Found";
    } else {
        $xml_output.="<status>Success</status>";
        $xml_output.="<message>Group List</message><groups>";
        while ($row = $result_group->next()) {
            $group_id = $row['group_id'];
            $group_name = $row['group_name'];
            $q = "select * from friend_group where group_id='$group_id'";
           // $result_f_g = mysql_query($q);
            $statement = $this->dbAdapter->createQuery($q);
            $result_f_g = $statement->getResult();
           // $row = $result->current();

            $xml_output.="<group><group_id>$group_id</group_id>";
            $xml_output.="<group_name>$group_name</group_name>";
            $xml_output.="<friends>";
            if ($result_f_g->count()) {
                while ($row = ($result_f_g->next())) {
                    $xml_output.="<friend>";
                    $xml_output.= "<friend_id>" . $row['friend_id'] . "</friend_id>";
                    $xml_output.= "</friend>";
                }
                
            } else {
                $xml_output.="<friend>";
                $xml_output.= "<friend_id></friend_id>";
                $xml_output.= "</friend>";
            }
            $xml_output.="</friends>";
            $xml_output.="</group>";
        }
    
        $xml_output.="</groups>";
    }
}
if ($error_flag) {
    $xml_output.="<status>Failure</status>";
    $xml_output.= "<message>$message</message><groups><group>";
    $xml_output.="<group_id></group_id>";
    $xml_output.="<group_name></group_name>";
    $xml_output.="<friends>";
    $xml_output.="<friend>";
    $xml_output.= "<friend_id></friend_id>";
    $xml_output.= "</friend>";
    $xml_output.="</friends></group></groups>";
}
$xml_output.="</listgroupresponse></xml>";
echo $xml_output;
}
}
?>
