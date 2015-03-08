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
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		$message = ' ';
		$event_id = trim ( $data->likemedia->event_id );
		$media_id = trim ( $data->likemedia->media_id );
		$user_id = trim ( $data->likemedia->user_id );
		$is_like = trim ( $data->likemedia->is_like );
		$time = time ();
		$isIdSet = 0;
		
		if (isset ( $media_id ) && ! empty ( $media_id )) {
			$isIdSet = 1;
		} else {
			$media_id = "";
		}
		
		if (isset ( $event_id ) && ! empty ( $event_id )) {
			$isIdSet = 1;
		} else {
			$event_id = "";
		}
		
		$status = 'ok';
		if (! $isIdSet) {
			$message = 'event_id and media_id are empty';
			$status = 'Failure';
		}
		if (! isset ( $is_like ) || empty ( $is_like )) {
			$message = 'is_like is empty';
			$status = 'Failure';
		}
		if (! isset ( $user_id ) || empty ( $user_id )) {
			$message = 'user_id is empty';
			$status = 'Failure';
		}
		
		if ($status != 'Failure') {
			$q = "select c.comment_id from Application\Entity\Comment c" . " where (c.media_id ='$media_id' or c.event_id='$event_id')" . " and c.type = 'like' " . " and c. user_id='$user_id'" . " AND c.like=1";
			
			$statement = $this->dbAdapter->createQuery ( $q );
			$row = $statement->getResult ();
			
			if (! empty ( $row [0] )) {
				$status = 'Success';
				$message = 'You Already Like This Media';
			} else {
				/*
				 * Check media and/or event id
				 */
				if (!empty($media_id)) {
					$q_event_media = "SELECT m  FROM Application\Entity\EventMedia m where m.media_id='$media_id'";
					
					$statement = $this->dbAdapter->createQuery ( $q_event_media );
					$result_event_media = $statement->getResult ();
					
					if (empty ( $result_event_media [0] )) {
						
						$status = 'Failure';
						$message = "No Media for this Event";
					}
				} else if (!empty($event_id)) {
					$q_event = "SELECT e  FROM Application\Entity\Event e where e.event_id='$event_id'";
					
					$statement = $this->dbAdapter->createQuery ( $q_event );
					$result_event = $statement->getResult ();
					
					if (empty ( $result_event [0] )) {
						
						$status = 'Failure';
						$message = "No Event found";
					}
				}
				/*
				 * If ok insert...
				 */
				if ($status != 'Failure') {
					$comment_id = MUUID::fetchUUID ();
					if(empty($event_id)) {
						$event_id = $result_event_media [0]->event_id;
					}
					
					$tblComment = new \Application\Entity\Comment ();
					$tblComment->comment_id = $comment_id;
					$tblComment->media_id = $media_id;
					$tblComment->user_id = $user_id;
					$tblComment->type = 'like';
					$tblComment->like = 1;
					$tblComment->event_id = $event_id;
					$tblComment->create_time = $time;
					$tblComment->update_time = $time;
					
					$this->dbAdapter->persist ( $tblComment );
					$this->dbAdapter->flush ();
					
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
