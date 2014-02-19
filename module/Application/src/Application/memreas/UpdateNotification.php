<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\UUID;
use \Exception;

class UpdateNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	public $user_id;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$message = '';
		$time = time ();
		if (empty ( $data->updatenotification->notification )) {
			$status = "failure";
			$message = "Notification not found";
		} else {
			foreach ( $data->updatenotification->notification as $notification ) {
				$this->user_id = $user_id = (trim ( $notification->user_id ));
				$notification_id = (trim ( $notification->notification_id ));
				
				$status = trim ( $notification->status );
				// save notification in table
				$tblNotification = $this->dbAdapter->find ( "\Application\Entity\Notification", $notification_id );
				if (! $tblNotification) {
					$status = "failure";
					$message = "Notification not found";
				} else {
					$tblNotification->status = $status;
					$tblNotification->is_read = 1;
					
					$tblNotification->update_time = $time;
					$this->dbAdapter->flush ();
					$status = "Sucess";
					$message = "Notification Updated";
					/*
					 * $this->notification->setUpdateMessage($tblNotification->notification_type); $this->notification->add($tblNotification->user_id); $this->notification->type=$tblNotification->notification_type; $links = json_decode($tblNotification->links,true); $this->notification->event_id= $links['event_id']; $this->notification->send();
					 */
				}
			}
		}
		
		if (empty ( $frmweb )) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<updatenotification>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>" . $message . "</message>";
			$xml_output .= "<notification_id>$notification_id</notification_id>";
			$xml_output .= "</updatenotification>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}
}

?>
