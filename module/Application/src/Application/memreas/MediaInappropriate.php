<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;

class MediaInappropriate {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec($frmweb = false, $output = '') {
		$time = "";
        if (empty ( $frmweb )) {
		    $data = simplexml_load_string ( $_POST ['xml'] );
        }
        else {
            $data = json_decode ( json_encode ( $frmweb ) );
        }
		// echo "<pre>";
		// print_r($data);
		$message = ' ';
		$media_id = trim ( $data->mediainappropriate->media_id );
		$is_appropriate = trim ( $data->mediainappropriate->is_appropriate );
		$event_id = trim ( $data->mediainappropriate->event_id );
		$user_id = trim ( $data->mediainappropriate->user_id );
		if (! isset ( $media_id ) || empty ( $media_id )) {
			$message = 'media_id is empty';
			$status = 'Failure';
		} else {
			$q = "UPDATE Application\Entity\Comment c SET c.inappropriate= $is_appropriate WHERE c.media_id ='$media_id'";
			// $result = mysql_query($q);
			// $statement = $this->dbAdapter->createStatement($q);
			// $result = $statement->execute();
			
			$statement = $this->dbAdapter->createQuery ( $q );
			$result = $statement->getResult ();
			
			if (empty ( $result )) {
				
				$uuid = MUUID::fetchUUID ();
				$tblComment = new \Application\Entity\Comment ();
				$tblComment->comment_id = $uuid;
				$tblComment->media_id = $media_id;
				$tblComment->user_id = $user_id;
				$tblComment->type = 'text';
				$tblComment->text = '';
				$tblComment->event_id = $event_id;
				$tblComment->inappropriate = $is_appropriate;
				$tblComment->create_time = $time;
				$tblComment->update_time = $time;
				
				$this->dbAdapter->persist ( $tblComment );
				$this->dbAdapter->flush ();
				
				// $query_comment = "insert into Application\Entity\Comment (comment_id,media_id,user_id,type,text, event_id,inappropriate,create_time,update_time)
				// values('$uuid','$media_id','$user_id','text','','$event_id',$is_appropriate,'$time','$time')";
				// $result1 = mysql_query($query_comment);
				// $statement = $this->dbAdapter->createStatement($query_comment);
				// $result1 = $statement->execute();
				// $row = $result->current();
				// $statement = $this->dbAdapter->createQuery($query_comment);
				// $result1 = $statement->getResult();
			}
			
			$status = 'Success';
			$message = 'Appropriate flag Successfully Updated';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		
		$xml_output .= "<mediainappropriateresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "</mediainappropriateresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}
?>
