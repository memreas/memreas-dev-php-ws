<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Zend\View\Model\ViewModel;

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
		try {
			$time = "";
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
			}
			error_log ( "Inside MediaInappropriate _POST ['xml'] ---> " . $_POST ['xml'] . PHP_EOL );
			error_log ( "Inside MediaInappropriate data ---> " . print_r ( $data, true ) . PHP_EOL );
			// echo "<pre>";
			// print_r($data);
			$message = ' ';
			$event_id = trim ( $data->mediainappropriate->event_id );
			$user_id = trim ( $data->mediainappropriate->user_id );
			$media_id = trim ( $data->mediainappropriate->media_id );
			$inappropriate = trim ( $data->mediainappropriate->inappropriate );
			$reason_types = $data->mediainappropriate->reason_types->reason_type;
			error_log ( "event_id ..." . $event_id . PHP_EOL );
			error_log ( "user_id ..." . $user_id . PHP_EOL );
			error_log ( "media_id ..." . $media_id . PHP_EOL );
			error_log ( "inappropriate ..." . $inappropriate . PHP_EOL );
			foreach ( $reason_types as $reason ) {
				error_log ( "reason ..." . $reason . PHP_EOL );
			}
			// error_log ( "reason_types ..." . json_encode($reason_types) . PHP_EOL );
			if (! isset ( $media_id ) || empty ( $media_id )) {
				$message = 'media_id is empty';
				$status = 'Failure';
			} else if (! isset ( $event_id ) || empty ( $event_id )) {
				$message = 'event_id is empty';
				$status = 'Failure';
			} else if (! isset ( $user_id ) || empty ( $user_id )) {
				$message = 'user_id is empty';
				$status = 'Failure';
			} else if (! isset ( $reason_types ) || empty ( $reason_types )) {
				$message = 'reason_types is empty';
				$status = 'Failure';
			} else {
				
				/*
				 * Fetch the media row
				 */
				$query_event = "select m
                from Application\Entity\Media m
                where m.media_id='$media_id'";
				$statement = $this->dbAdapter->createQuery ( $query_event );
				$result_media = $statement->getResult ();
				
				/*
				 * Update Media and any associated comments
				 */
				if ($result_media) {
					
					/*
					 * Update the media flag and update the json...
					 * Sample json below...
					 */
					/*
					 * "inappropriate" : {
					 * "events" : [event1,event2,...],
					 * "event:event_id" : {
					 * "users" : [user1,user2,...],
					 * "user:user_id" : {
					 * "reason_types" : [
					 * "sexual_content",
					 * "violent_content",
					 * "hate_content",
					 * "other_content"
					 * ],
					 * "date_created" : "date_created"
					 * }
					 * }
					 * }
					 */
					
					$json_array = json_decode ( $result_media [0]->metadata, true );
					$media_inappropriate;
					$media_inappropriate_event;
					if (empty ( $json_array ['S3_files'] ['media_inappropriate'] )) {
						$media_inappropriate = Array ();
						$media_inappropriate ['media_inappropriate'] ['events'] [] = $event_id;
						$media_inappropriate ['media_inappropriate'] ['event:' . $event_id] ['users'] = $user_id;
						$now = date ( 'Y-m-d H:i:s' );
						$media_inappropriate ['media_inappropriate'] ['event:' . $event_id] ['user:' . $user_id] ['date_created'] = $now;
						$media_inappropriate_event = $media_inappropriate;
					} else {
						// Check for event_id
						if (in_array ( $event_id, $media_inappropriate ['media_inappropriate'] ['events'] )) {
							$media_inappropriate = $media_inappropriate ['media_inappropriate'] ['event' . $event_id];
						} else {
							// create entry for this event...
							$media_inappropriate = Array ();
							$media_inappropriate ['media_inappropriate'] ['events'] [] = $event_id;
							$media_inappropriate ['media_inappropriate'] ['event:' . $event_id] ['users'] = $user_id;
							$now = date ( 'Y-m-d H:i:s' );
							$media_inappropriate ['media_inappropriate'] ['event:' . $event_id] ['user:' . $user_id] ['date_created'] = $now;
							$media_inappropriate_event = $media_inappropriate;
						}
					}
					// Found or created array now populate
					if (is_array ( $reason_types )) {
						foreach ( $reason_types as $reason ) {
							$media_inappropriate_event ['media_inappropriate'] ['event:' . $event_id] ['user:' . $user_id] [] = $reason;
						}
					} else {
						$media_inappropriate_event ['media_inappropriate'] ['event:' . $event_id] ['user:' . $user_id] [] = $reason;
					}
				}
				$json_array ['S3_files'] = $media_inappropriate_event;
				error_log ( "json_array ..." . json_encode ( $json_array ) . PHP_EOL );
				
				$status = 'Success';
				$message = 'Appropriate flag Successfully Updated';
			}
		} catch ( \Exception $exc ) {
			$status = 'Failure';
			$message = $exc->getMessage ();
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
		error_log ( "xml_output--->" . $xml_output . PHP_EOL );
	} // public function __construct
} // end class MediaInappropriate

/*
 * TODO: Determine is comments need to be updated
 * Comments will be hidden by default...
 * $q = "UPDATE Application\Entity\Comment c SET c.inappropriate= $is_appropriate WHERE c.media_id ='$media_id'";
 * $statement = $this->dbAdapter->createQuery ( $q );
 * $result = $statement->getResult ();
 *
 * if (empty ( $result )) {
 * $uuid = MUUID::fetchUUID ();
 * $tblComment = new \Application\Entity\Comment ();
 * $tblComment->comment_id = $uuid;
 * $tblComment->media_id = $media_id;
 * $tblComment->user_id = $user_id;
 * $tblComment->type = 'text';
 * $tblComment->text = '';
 * $tblComment->event_id = $event_id;
 * $tblComment->inappropriate = $is_appropriate;
 * $tblComment->create_time = $time;
 * $tblComment->update_time = $time;
 *
 * $this->dbAdapter->persist ( $tblComment );
 * $this->dbAdapter->flush ();
 * }
 */

/*
 * TODO: Determine if email needs to be sent... to owner ... likely not
 *
 * $mediaOBj = $this->dbAdapter->find('Application\Entity\Media', $media_id);
 *
 * $userOBj = $this->dbAdapter->find('Application\Entity\User', $mediaOBj->user_id);
 * $reporterObj = $this->dbAdapter->find('Application\Entity\User', $user_id);
 * $viewVar = array();
 * $viewModel = new ViewModel ();
 * $aws_manager = new AWSManagerSender($this->service_locator);
 * $viewModel->setTemplate('email/media-inappropriate');
 * $viewRender = $this->service_locator->get('ViewRenderer');
 *
 * //convert to array
 * $to = array($userOBj->email_address);
 *
 * $subject = 'Memreas media reported by user';
 * $viewVar['username'] = $userOBj->username;
 * $viewVar['report_username'] = $reporterObj->username;
 * $viewVar['description'] = "Media has been reported";
 * $viewVar['report_email'] = $reporterObj->email_address;
 * $viewModel->setVariables($viewVar);
 * $html = $viewRender->render($viewModel);
 *
 * try {
 * $aws_manager->sendSeSMail($to, $subject, $html);
 * } catch (\Exception $exc) {}
 */
?>