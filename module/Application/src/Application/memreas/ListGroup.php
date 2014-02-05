<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Entity\Album;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class ListGroup {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		
		// ->get('memreasdevdb');
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml><listgroupresponse>";
		
		$data = simplexml_load_string ( $_POST ['xml'] );
		// echo "hi<pre>";
		// print_r($data);
		$message = ' ';
		$error_flag = 0;
		$user_id = trim ( $data->listgroup->user_id );
		
		/*
		 * $query_group = "SELECT group_id FROM group where user_id='$user_id'";
		 */
		$query_group = "select g from Application\Entity\Group as g ";
		$statement = $this->dbAdapter->createQuery ( $query_group );
		$result_group = $statement->getResult ();
		// echo '<pre>';print_r($result_group);exit;
		// $q = $this->em->getConnection();
		// $result_group = $q->fetchAll($query_group);
		
		if (count ( $result_group ) == 0) {
			$error_flag = 2;
			$message = "No Record Found";
		} else {
			$xml_output .= "<status>Success</status>";
			$xml_output .= "<message>Group List</message><groups>";
			foreach ( $result_group as $row ) {
				
				$group_id = $row->group_id;
				$group_name = $row->group_name;
				$q = "select fg from Application\Entity\FriendGroup as fg where fg.group_id='$group_id'";
				// $result_f_g = mysql_query($q);
				$statement = $this->dbAdapter->createQuery ( $q );
				$result_f_g = $statement->getResult ();
				// $row = $result->current();
				
				$xml_output .= "<group><group_id>$group_id</group_id>";
				$xml_output .= "<group_name>$group_name</group_name>";
				$xml_output .= "<friends>";
				if (count ( $result_f_g ) > 0) {
					foreach ( $result_f_g as $row1 ) {
						$xml_output .= "<friend>";
						$xml_output .= "<friend_id>" . $row1->friend_id . "</friend_id>";
						$xml_output .= "</friend>";
					}
				} else {
					$xml_output .= "<friend>";
					$xml_output .= "<friend_id></friend_id>";
					$xml_output .= "</friend>";
				}
				$xml_output .= "</friends>";
				$xml_output .= "</group>";
			}
			
			$xml_output .= "</groups>";
		}
		
		if ($error_flag) {
			$xml_output .= "<status>Failure</status>";
			$xml_output .= "<message>$message</message><groups><group>";
			$xml_output .= "<group_id></group_id>";
			$xml_output .= "<group_name></group_name>";
			$xml_output .= "<friends>";
			$xml_output .= "<friend>";
			$xml_output .= "<friend_id></friend_id>";
			$xml_output .= "</friend>";
			$xml_output .= "</friends></group></groups>";
		}
		$xml_output .= "</listgroupresponse></xml>";
		echo $xml_output;
	}
}
?>
