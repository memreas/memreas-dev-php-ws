<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;

class AddNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification_id;
	protected $aws_manager;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->aws_manager = new AWSManagerSender ( $service_locator );
		$this->viewRender = $service_locator->get ( 'ViewRenderer' );
	}
	public function exec($frmweb = '') {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '...' );
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, $frmweb );
			}
			
			//
			// Add sql check here to see if notification is logged...
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '...' );
			$suid = $data->addNotification->sender_uid;
			$ruid = $data->addNotification->receiver_uid;
			$ntype = $data->addNotification->notification_type;
			$sql = "SELECT count(n.sender_uid)  FROM Application\Entity\Notification as n 
					where n.sender_uid = '$suid' 
					and n.receiver_uid = '$ruid' 
					and n.notification_type = '$ntype'";
			$statement = $this->dbAdapter->createQuery ( $sql );
			$sentPrior = $statement->getSingleScalarResult ();
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'check for prior notification sql --> ' . $sql );
			
			//
			// Notification is ok to proceed
			//
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'result of check for prior notification sql $sentPrior--> ' . $sentPrior );
			if ($sentPrior) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::Failure - notification sent prior' );
				$status = 'Failure';
				$message = 'notification sent prior';
			} else {
				//
				// save notification in table
				//
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::Success - adding notification' );
				$this->notification_id = $notification_id = MUUID::fetchUUID ();
				$tblNotification = new \Application\Entity\Notification ();
				$tblNotification->notification_id = $notification_id;
				$tblNotification->sender_uid = $data->addNotification->sender_uid;
				$tblNotification->receiver_uid = $data->addNotification->receiver_uid;
				$tblNotification->notification_type = $data->addNotification->notification_type;
				$tblNotification->meta = json_encode ( $data->addNotification->meta );
				$tblNotification->is_read = empty ( $data->addNotification->is_read ) ? 0 : $data->addNotification->is_read;
				$tblNotification->status = empty ( $data->addNotification->status ) ? 0 : $data->addNotification->status;
				if ((strtolower ( $tblNotification->status ) == 'add') || ($tblNotification->status == 0)) {
					$tblNotification->response_status = 'add';
				} else if ((strtolower ( $tblNotification->status ) == 'accept') || ($tblNotification->status == 1)) {
					$tblNotification->response_status = 'accept';
				} else if ((strtolower ( $tblNotification->status ) == 'decline') || ($tblNotification->status == 2)) {
					$tblNotification->response_status = 'decline';
				} else if ((strtolower ( $tblNotification->status ) == 'ignore') || ($tblNotification->status == 3)) {
					$tblNotification->response_status = 'ignore';
				}
				$tblNotification->notification_methods = json_encode ( $data->addNotification->notification_methods );
				$tblNotification->create_time = time ();
				$tblNotification->update_time = time ();
				$this->dbAdapter->persist ( $tblNotification );
				$this->dbAdapter->flush ();
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'Success? - $this->dbAdapter->flush ()... ' );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->notification_id --->', $tblNotification->notification_id );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->sender_uid --->', $tblNotification->sender_uid );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->receiver_uid --->', $tblNotification->receiver_uid );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->notification_type --->', $tblNotification->notification_type );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->meta --->', $tblNotification->meta );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->is_read --->', $tblNotification->is_read );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->status --->', $tblNotification->status );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->notification_methods --->', $tblNotification->notification_methods );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->create_time --->', $tblNotification->create_time );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblNotification->update_time --->', $tblNotification->update_time );
				
				$status = 'Success';
				$message = 'notification added';
			} // end if
			
			if (empty ( $frmweb )) {
				header ( "Content-type: text/xml" );
				$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$xml_output .= "<xml>";
				$xml_output .= "<addnotification>";
				$xml_output .= "<status>$status</status>";
				$xml_output .= "<message>" . $message . "</message>";
				$xml_output .= "<notification_id>$this->notification_id</notification_id>";
				$xml_output .= "</addnotification>";
				$xml_output .= "</xml>";
				echo $xml_output;
			}
		} catch ( \Exception $e ) {
			if (empty ( $frmweb )) {
				$status = 'failure';
				$message .= 'addnotification error ->' . $e->getMessage ();
				header ( "Content-type: text/xml" );
				$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$xml_output .= "<xml>";
				$xml_output .= "<addnotification>";
				$xml_output .= "<status>$status</status>";
				$xml_output .= "<message>" . $message . "</message>";
				$xml_output .= "</addnotification>";
				$xml_output .= "</xml>";
				echo $xml_output;
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, $e->getMessage () );
				throw new Exception ( __CLASS__ . __METHOD . __LINE__, $e->getMessage () );
			}
		}
		
		return true;
	} // end exec()
}

?>
