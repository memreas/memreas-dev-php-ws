<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;

class LikeMedia {
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
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		// echo "<pre>";
		// print_r($data);
		$message = ' ';
		$media_id = trim ( $data->likemedia->media_id );
		$user_id = trim ( $data->likemedia->user_id );
		$is_like = trim ( $data->likemedia->is_like );
		$time = time ();
		if (! isset ( $media_id ) || empty ( $media_id )) {
			$message = 'media_id is empty';
			$status = 'Failure';
		} else if (! isset ( $is_like ) || empty ( $is_like )) {
			$message = 'is_like is empty';
			$status = 'Failure';
		} else if (! isset ( $user_id ) || empty ( $user_id )) {
			$message = 'user_id is empty';
			$status = 'Failure';
		} else {
			$q = "select c.comment_id from Application\Entity\Comment c where c.media_id ='$media_id' and c.type = 'like' and c. user_id='$user_id'";
			// $result = mysql_query($q);
			// $statement = $this->dbAdapter->createStatement($q);
			// $result = $statement->execute();
			// $row = $result->current();
			
			$statement = $this->dbAdapter->createQuery ( $q );
			$row = $statement->getResult ();
			
			if (! empty ( $row [0] )) {
				$status = 'Success';
				$message = 'You Already Like This Media';
			} else {
				$q_event_media = "SELECT m  FROM Application\Entity\EventMedia m where m.media_id='$media_id'";
				// $result_event_media = mysql_query($q_event_media);
				// $statement = $this->dbAdapter->createStatement($q_event_media);
				// $result = $statement->execute();
				// $result_event_media = $result->current();
				$statement = $this->dbAdapter->createQuery ( $q_event_media );
				$result_event_media = $statement->getResult ();
				// echo '<pre>';print_r($result_event_media);exit;
				
				if (empty ( $result_event_media [0] )) {
					
					$status = 'Failure';
					$message = "No Media for this Event";
				} else {
					$comment_id = MUUID::fetchUUID ();
					// $row = mysql_fetch_assoc($result_event_media);
					
					$tblComment = new \Application\Entity\Comment ();
					$tblComment->comment_id = $comment_id;
					$tblComment->media_id = $media_id;
					$tblComment->user_id = $user_id;
					$tblComment->type = 'like';
					$tblComment->event_id = $result_event_media [0]->event_id;
					$tblComment->create_time = $time;
					$tblComment->update_time = $time;
					
					$this->dbAdapter->persist ( $tblComment );
					$this->dbAdapter->flush ();
					
					/*
					 * $q_comment = "INSERT INTO Application\Entity\Comment (comment_id,`media_id`, `user_id`, `type`, `event_id`, `create_time`, `update_time`) VALUES ('$comment_id','$media_id', '$user_id', 'like', '" . $row['event_id'] . "', '$time', '$time')";
					 */
					// mysql_query($q_comment) or die(mysql_error());
					// $statement = $this->dbAdapter->createStatement($q_comment);
					// $result = $statement->execute
					// $statement = $this->dbAdapter->createQuery($q_comment);
					// $result = $statement->getResult();
					
					$status = "Success";
					$message .= "You Like succesfully";
				}
			}
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		
		$xml_output .= "<likemediaresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		$xml_output .= "<message>" . $message . "</message>";
		$xml_output .= "</likemediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
