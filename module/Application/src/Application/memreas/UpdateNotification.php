<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants as MC;
use Application\memreas\UUID;
 
 use \Exception;

class UpdateNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	public $user_id;
		protected $AddNotification;

	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
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
				$notification_message = '';
				if(!empty($notification->message)){
						$notification_message =  trim ( $notification->message );
				}
				
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
					
					if($tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT ){
						$friend_id=$tblNotification->user_id;
						$json_data = json_decode($tblNotification->links,true);

						$user_id   =  $json_data['from_id'];
						$UserFriend = $this->dbAdapter->getRepository( "\Application\Entity\UserFriend")
                         					->findOneBy(array('user_id' => $user_id,'friend_id' => $friend_id));                				
                        $eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $json_data['event_id'] );

                        if($UserFriend){
                        	$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $friend_id );
                        	//accepted
                        	if($status == 1){
                        		$UserFriend->user_approve = 1;
                        		$nmessage = $userOBj->username . ' Accepted ' . $eventOBj->name .' ' . $notification_message;
                        	}
                        	 //ignored
                        	if($status == 2){
                        	 	$nmessage = $userOBj->username . ' Ignored ' . $eventOBj->name .' ' . $notification_message;
                        	}
                        	//rejected
                        	if($status == 3){
                        		$nmessage = $userOBj->username . ' Rejected ' . $eventOBj->name . ' ' . $notification_message ;
                        	}
                         	//add ntoification 
                        	
                        	
							// save nofication intable
 							$ndata = array (
								'addNotification' => array (
									'network_name' => 'memreas',
										'user_id' => $user_id,
										'meta' => $nmessage,
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT,
										'links' => $tblNotification->links, 
										)
								
							);
 								// send push message add user id
		 						$this->notification->add ( $user_id );
 							//add notification in  db.
							$this->AddNotification->exec ( $ndata );
                         }//user friend updated
                         					
					}

					$this->dbAdapter->flush ();
					//$this->notification->send();
					$status = "success";
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
