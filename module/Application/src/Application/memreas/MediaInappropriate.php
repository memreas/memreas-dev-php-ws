<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Application\Model\MemreasConstants;
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
			$message = '';
			if (empty ( $data->mediainappropriate->media_id )) {
				$message = 'media_id is empty...';
				$status = 'Failure';
			} else if (empty ( $data->mediainappropriate->event_id )) {
				$message = 'event_id is empty...';
				$status = 'Failure';
			} else if (empty ( $data->mediainappropriate->reporting_user_id )) {
				$message = 'reporting_user_id is empty...';
				$status = 'Failure';
			} else if (empty ( $data->mediainappropriate->reason_types )) {
				$message = 'reason is empty...';
				$status = 'Failure';
			} else if (empty ( $data->mediainappropriate->inappropriate )) {
				$message = 'inappropriate is empty...';
				$status = 'Failure';
			} else {
				//
				// Set vars
				//
				$event_id = trim ( $data->mediainappropriate->event_id );
				$reporting_user_id = trim ( $data->mediainappropriate->reporting_user_id );
				$media_id = trim ( $data->mediainappropriate->media_id );
				$inappropriate = trim ( $data->mediainappropriate->inappropriate );
				$reason_types = $data->mediainappropriate->reason_types;
				
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
					 * "reason" : "reason..."
					 * "date_created" : "date_created"
					 * },
					 * },
					 * "event:event_id" : {
					 * ...
					 * },
					 * }
					 * }
					 * }
					 */
					
					$json_array = json_decode ( $result_media [0]->metadata, true );
					$media_inappropriate_event;
					if (empty ( $json_array ['S3_files'] ['media_inappropriate'] )) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::if (empty ( $json_array [S3_files] [media_inappropriate] )) --> $event_id-->', $event_id );
						$media_inappropriate_event = [ ];
					} else {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::if (!empty ( $json_array [S3_files] [media_inappropriate] )) --> $event_id-->', $event_id );
						// media_inappropriate exists
						// just add to existing...
						$media_inappropriate_event = $media_inappropriate_event = $json_array ['S3_files'] ['media_inappropriate'];
					}
					//
					// once proper array is retrieved the entries are the same
					// - only add to events if event_id wasn't added prior
					// - only add user data if they haven't reported media prior.
					//
					if (! isset ( $media_inappropriate_event ['events'] )) {
						$media_inappropriate_event ['events'] [] = $event_id;
					} else if (! in_array ( $event_id, $media_inappropriate_event ['events'] )) {
						$media_inappropriate_event ['events'] [] = $event_id;
					}
					
					$logMediaInappropriate = false;
					if (! isset ( $media_inappropriate_event ['users'] )) {
						$logMediaInappropriate = true;
					} else if (! in_array ( $reporting_user_id, $media_inappropriate_event ['users'] )) {
						$logMediaInappropriate = true;
					}
					if ($logMediaInappropriate) {
						$media_inappropriate_event ['users'] [] = $reporting_user_id;
						$media_inappropriate_event [$event_id] ['event'] ['users'] [] = $reporting_user_id;
						Mlog::addone ( '$reason_types--->', $reason_types );
						$reason_type = $reason_types [0];
						foreach ( $reason_type as $reason ) {
							Mlog::addone ( '(string) $reason--->', ( string ) $reason );
							$media_inappropriate_event [$event_id] ['event'] [$reporting_user_id] ['user'] ['reason'] [] = ( string ) $reason;
						}
						$media_inappropriate_event [$event_id] ['event'] [$reporting_user_id] ['user'] ['date_created'] = MNow::now ();
					} else {
						throw new \Exception ( "prior report received" );
					}
					
					$json_array ['S3_files'] ['media_inappropriate'] = $media_inappropriate_event;
				}
				// array is modified already
				//
				error_log ( "json_array ..." . json_encode ( $json_array ) . PHP_EOL );
				
				/*
				 * Updates the media table
				 * - if count of users is > 5 then set flag else add flag data
				 */
				if (count ( $media_inappropriate_event ['users'] ) > 5) {
					$q = "UPDATE Application\Entity\Media m" . " SET m.report_flag= $inappropriate, m.metadata='" . json_encode ( $json_array ) . "'" . " WHERE m.media_id ='$media_id'";
				} else {
					$q = "UPDATE Application\Entity\Media m" . " SET m.metadata='" . json_encode ( $json_array ) . "'" . " WHERE m.media_id ='$media_id'";
				}
				$statement = $this->dbAdapter->createQuery ( $q );
				$result = $statement->getResult ();
				
				if ($result) {
					$status = 'success';
					$message = 'mediainappropriate completed';
				} else {
					$status = 'failure';
					$message = 'mediainappropriate failed';
				}
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
 * TODO: Determine if comments need to be updated
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