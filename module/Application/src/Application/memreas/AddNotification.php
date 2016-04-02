<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use Application\memreas\Email;

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
			// error_log('file--->'. __FILE__ . ' method -->'. __METHOD__ . ' line number::' . __LINE__ . PHP_EOL);
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
				Mlog::addone (  __LINE__ ,$frmweb ); 
			}
			
			// save notification in table
			$time = time ();
			$this->notification_id = $notification_id = MUUID::fetchUUID ();
			$tblNotification = new \Application\Entity\Notification ();
			$tblNotification->notification_id = $notification_id;
			$tblNotification->sender_uid = $data->addNotification->sender_uid;
			$tblNotification->receiver_uid = $data->addNotification->receiver_uid;
			$tblNotification->notification_type = $data->addNotification->notification_type;
			$tblNotification->meta = json_encode ( $data->addNotification->meta );
			$tblNotification->is_read = empty ( $data->addNotification->is_read ) ? 0 : $data->addNotification->is_read;
			$tblNotification->status = empty ( $data->addNotification->status ) ? 0 : $data->addNotification->status;
			if ($tblNotification->status == 0) {
				$tblNotification->response_status = '';	
			} else if ($tblNotification->status == 1) {
				$tblNotification->response_status = 'ACCEPT';
			} else if ($tblNotification->status == 2) {
				$tblNotification->response_status = 'DECLINE';
			} else if ($tblNotification->status == 3) {
				$tblNotification->response_status = 'IGNORE';
			}
			$tblNotification->notification_methods = json_encode ( $data->addNotification->notification_methods );
			$tblNotification->create_time = $time;
			$tblNotification->update_time = $time;
			$this->dbAdapter->persist ( $tblNotification );
			$this->dbAdapter->flush ();
			
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
		}
		 
		return true;
	} // end exec()
}

?>
