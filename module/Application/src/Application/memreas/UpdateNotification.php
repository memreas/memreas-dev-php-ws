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
//error_log ( "Inside UpdateNotification __construct..." );
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
//error_log("UpdateNotification::_POST ['xml']--->".$_POST ['xml'].PHP_EOL);
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
//error_log("UpdateNotification::frmweb--->".$frmweb.PHP_EOL);
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
//error_log("UpdateNotification::ADD_FRIEND_TO_EVENT".PHP_EOL);						
						$friend_id=$tblNotification->user_id;
						$json_data = json_decode($tblNotification->links,true);

						$user_id   =  $json_data['from_id'];
						$UserFriend = $this->dbAdapter->getRepository( "\Application\Entity\UserFriend")
                         					->findOneBy(array('user_id' => $user_id,'friend_id' => $friend_id)); 
                        $EventFriend = $this->dbAdapter->getRepository( "\Application\Entity\EventFriend")
                         					->findOneBy(array('event_id' => $json_data['event_id'] ,'friend_id' => $friend_id)); 					               				
                        $eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $json_data['event_id'] );
                         if($UserFriend){
//error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if userfriend...status is $status".PHP_EOL);						
                         	$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $friend_id );
                        	//accepted
                        	if($status == 1){
                        		$UserFriend->user_approve = 1;            
                        		$this->dbAdapter->persist ( $UserFriend );
                        		$EventFriend->user_approve = 1;
                        		$this->dbAdapter->persist ( $EventFriend );
                        		$nmessage = $userOBj->username . ' Accepted ' . $eventOBj->name .' ' . $notification_message;
//error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if status==1 ... just set event_friend".PHP_EOL);						
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
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT_RESPONSE,
										'links' => $tblNotification->links, 
										)
								
							);
 								// send push message add user id
		 						$this->notification->add ( $user_id );
 							//add notification in  db.
							$this->AddNotification->exec ( $ndata );
                         }//user friend updated
                         					
					}
						
					if($tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND ){
//error_log("UpdateNotification::ADD_FRIEND".PHP_EOL);						
						$friend_id=$tblNotification->user_id;
						$json_data = json_decode($tblNotification->links,true);

						$user_id   =  $json_data['from_id'];
						$UserFriend = $this->dbAdapter->getRepository( "\Application\Entity\UserFriend")
                         					->findOneBy(array('user_id' => $user_id,'friend_id' => $friend_id));                				
                        
                        if($UserFriend){
                        	$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $friend_id );

                        	//accepted
                        	if($status == 1){
                        		$UserFriend->user_approve = 1;
                        		 $this->dbAdapter->persist ( $UserFriend );
                        		$nmessage = $userOBj->username . ' Accepted Friend Request' .' ' . $notification_message;
                        		
                        		/*
                        		 * TODO : If the receiver accepts thes add the sender as a friend of the receiver  
                        		 */
                        		$tblFriend = new \Application\Entity\Friend();
                        		$tblFriend->friend_id = $user_id;
                        		$tblFriend->network = 'memreas';
                        		$tblFriend->social_username = "";
                        		$tblFriend->url_image = "";
                        		$tblFriend->create_date = $time;
                        		$tblFriend->update_date = $time;
                        		
                        		$tblUserFriend = new \Application\Entity\UserFriend ();
                        		$tblUserFriend->friend_id = $user_id;
                        		$tblUserFriend->user_id = $friend_id;
                        		
                         		try {
                        			$this->dbAdapter->persist($tblFriend);
                        			$this->dbAdapter->persist($tblUserFriend);
                        			$this->dbAdapter->flush();
                        			//error_log("Enter AddFriendtoevent.exec() - succeeded to insert tblFriend" . PHP_EOL);
                        		} catch (\Exception $exc) {
                        			error_log("Enter AddFriendtoevent.exec() - failure to insert tblFriend" . PHP_EOL);
                        			$status = 'failure';
                        			$error = 1;
                        		}
                        		
                        		$tblUserFriend = new \Application\Entity\UserFriend ();
                        		$tblUserFriend->friend_id = $friend_id;
                        		$tblUserFriend->user_id = $user_id;
                        		
                        		try {
                        			$this->dbAdapter->persist($tblUserFriend);
                        			$this->dbAdapter->flush();
                        			$sendMessage=1;
                        			$message .= 'User Friend Successfully added';
                        			$status = 'Success';
                        		} catch (\Exception $exc) {
                        			$status = 'Failure';
                        			$error = 1;
                        		}
                        		
                        		
                        	}
                        	 //ignored
                        	if($status == 2){
                        	 	$nmessage = $userOBj->username . ' Ignored Friend Request' .' ' . $notification_message;
                        	}
                        	//rejected
                        	if($status == 3){
                        		$nmessage = $userOBj->username . ' Rejected Friend Request' .' ' . $notification_message ;
                        	}
                         	//add ntoification 
                        	
                        	
							// save nofication intable
 							$ndata = array (
								'addNotification' => array (
									'network_name' => 'memreas',
										'user_id' => $user_id,
										'meta' => $nmessage,
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND_RESPONSE,
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
