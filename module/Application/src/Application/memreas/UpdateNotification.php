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
	protected $friend_id;
	protected $AddNotification;
	protected $tblNotification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
	}
	public function exec($frmweb = '') {
		try {
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
				error_log ( "UpdateNotification::_POST ['xml']--->" . $_POST ['xml'] . PHP_EOL );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
				error_log ( "UpdateNotification::frmweb--->" . $frmweb . PHP_EOL );
			}
			$message = '';
			$time = time ();
			if (empty ( $data->updatenotification->notification )) {
				$status = "failure";
				$message = "Notification not found";
			} else {
				foreach ( $data->updatenotification->notification as $notification ) {
					$this->user_id = (trim ( $notification->user_id ));
					$notification_id = (trim ( $notification->notification_id ));
					$notification_message = '';
					if (! empty ( $notification->message )) {
						$notification_message = trim ( $notification->message );
					}
					error_log ( '$notification' . PHP_EOL . json_encode ( $notification, JSON_PRETTY_PRINT ) . PHP_EOL );
					$notification_status = trim ( $notification->status );
					// save notification in table
					$this->tblNotification = $this->dbAdapter->find ( "\Application\Entity\Notification", $notification_id );
					if (! $this->tblNotification) {
						$status = "failure";
						$message = "notification not found";
					} else {
						$this->tblNotification->response_status = $notification_status;
						$this->tblNotification->is_read = 1;
						$this->tblNotification->update_time = $time;
						
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT) {
							$this->handleAddFriendToEventResponse ();
						} // end add friend to event update
						
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND) {
							$this->handleAddFriendResponse ();
						} // end add friend update
						
						$this->dbAdapter->flush ();
						// $this->notification->send();
						$status = "success";
						$message = "Notification Updated";
						/*
						 * $this->notification->setUpdateMessage($this->tblNotification->notification_type); $this->notification->add($this->tblNotification->user_id); $this->notification->type=$this->tblNotification->notification_type; $links = json_decode($this->tblNotification->links,true); $this->notification->event_id= $links['event_id']; $this->notification->send();
						 */
					}
				}
			}
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'update notification failed ->' . $e->getMessage ();
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
			error_log ( 'UpdateNotification ----> ' . $xml_output . PHP_EOL );
		}
	}
	public function handleAddFriendResponse() {
		try {
			// error_log("UpdateNotification::ADD_FRIEND".PHP_EOL);
			$this->friend_id = $this->tblNotification->user_id;
			$UserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'user_id' => $this->user_id,
					'friend_id' => $this->friend_id 
			) );
			
			if ($UserFriend) {
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->friend_id );
				// accepted
				if (($notification_status == 1) || (strtolower ( $notification_status ) == 'accept')) {
					$UserFriend->user_approve = 1;
					// save in user friend_friend
					$this->dbAdapter->persist ( $UserFriend );
					$nmessage = $userOBj->username . ' accepted friend request with message:' . ' ' . $notification_message;
					
					/*
					 * If the receiver accepts thes add the sender as a friend of the receiver
					 */
					$this->addFriendRevRec ( $this->user_id, $this->friend_id );
				}
				// declined
				if (($notification_status == 2) || (strtolower ( $notification_status ) == 'decline')) {
					$nmessage = $userOBj->username . ' declined friend request' . ' ' . $notification_message;
				}
				// ignored
				if (($notification_status == 3) || (strtolower ( $notification_status ) == 'ignore')) {
					$nmessage = $userOBj->username . ' ignored friend request' . ' ' . $notification_message;
				}
				
				/*
				 * Update the existing notification table meta
				 */
				$meta = json_decode ( $this->tblNotification->meta, true );
				$meta ['received'] ['message'] = $nmessage;
				$this->tblNotification->meta = $meta;
				
				/*
				 * Add a new notification for the response
				 */
				/**
				 * Build array and send notifications...
				 */
				$data = array ();
				$data ['addNotification'] ['sender'] = $this->user_id;
				$data ['addNotification'] ['receiver'] = $this->friend_id;
				$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_FRIEND_RESPONSE;
				$data ['addNotification'] ['notification_methods'] [] = 'email';
				$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
				$data ['addNotification'] ['meta'] = $meta;
				
				// add notification in db.
				$result = $this->AddNotification->exec ( $data );
				$data = simplexml_load_string ( $result );
				error_log ( 'AddNotification ----> ' . $result . PHP_EOL );
				error_log ( 'AddNotification id----> ' . $data->notification_id . PHP_EOL );
				
				if ($notification_status != 3) {
					// send push message add user id
					$this->notification->add ( $this->user_id );
				}
			} // user friend updated
		} catch ( \Exception $e ) {
			throw new Exception ( $e->getMessage () );
		}
	} // end handleAddFriendResponse()
	public function handleAddFriendToEventResponse() {
		try {
			// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT".PHP_EOL);
			$this->friend_id = $this->tblNotification->user_id;
			$json_data = json_decode ( $this->tblNotification->links, true );
			
			$this->user_id = $json_data ['from_id'];
			$UserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'user_id' => $this->user_id,
					'friend_id' => $this->friend_id 
			) );
			$EventFriend = $this->dbAdapter->getRepository ( "\Application\Entity\EventFriend" )->findOneBy ( array (
					'event_id' => $json_data ['event_id'],
					'friend_id' => $this->friend_id 
			) );
			$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $json_data ['event_id'] );
			if ($UserFriend) {
				// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if userfriend...status is $status".PHP_EOL);
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->friend_id );
				// accepted
				if ($notification_status == 1) {
					$UserFriend->user_approve = 1;
					$this->dbAdapter->persist ( $UserFriend );
					$EventFriend->user_approve = 1;
					$this->dbAdapter->persist ( $EventFriend );
					$nmessage = $userOBj->username . ' Accepted ' . $eventOBj->name . ' ' . $notification_message;
					/*
					 * If the receiver accepts thes add the sender as a friend of the receiver
					 */
					$this->addFriendRevRec ( $this->user_id, $this->friend_id );
					// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if status==1 ... just set event_friend".PHP_EOL);
				}
				// decline
				if ($notification_status == 2) {
					$nmessage = $userOBj->username . ' declined ' . $eventOBj->name . ' ' . $notification_message;
				}
				// ignored
				if ($notification_status == 3) {
					$nmessage = $userOBj->username . ' ignored ' . $eventOBj->name . ' ' . $notification_message;
				}
				// add ntoification
				$json_data ['from_id'] = $this->user_id;
				
				// save nofication intable
				$ndata = array (
						'addNotification' => array (
								'network_name' => 'memreas',
								'user_id' => $this->user_id,
								'meta' => $nmessage,
								'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT_RESPONSE,
								'links' => json_encode ( $json_data ) 
						) 
				);
				
				if ($notification_status != 3) {
					// send push message add user id
					$this->notification->add ( $this->user_id );
					// add notification in db.
					$this->AddNotification->exec ( $ndata );
				}
			} // user friend updated
		} catch ( \Exception $e ) {
			throw new Exception ( $e->getMessage () );
		}
	} // end handleAddFriendToEventResponse()
	public function addFriendRevRec() {
		try {
			/*
			 * If the receiver accepts then add the sender as a friend of the receiver
			 */
			$time = time ();
			$inUserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'user_id' => $this->friend_id,
					'friend_id' => $this->user_id 
			) );
			
			$inFriend = $this->dbAdapter->find ( 'Application\Entity\Friend', $this->user_id );
			
			if (! $inFriend) {
				$profile_pic = $this->dbAdapter->getRepository ( 'Application\Entity\Media' )->findOneBy ( array (
						'user_id' => $this->user_id,
						'is_profile_pic' => '1' 
				) );
				$profile_pic_url = MC::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
				if ($profile_pic) {
					$metadata = $profile_pic->metadata;
					$profile_image = json_decode ( $metadata, true );
					$profile_pic_url = $profile_image ['S3_files'] ['path'];
				}
				
				$userFOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->user_id );
				
				$tblFriend = new \Application\Entity\Friend ();
				$tblFriend->friend_id = $this->user_id;
				$tblFriend->network = 'memreas';
				$tblFriend->social_username = empty ( $userFOBj ) ? '' : $userFOBj->username;
				$tblFriend->url_image = $profile_pic_url;
				$tblFriend->create_date = $time;
				$tblFriend->update_date = $time;
				
				$this->dbAdapter->persist ( $tblFriend );
			}
			
			if (! $inUserFriend) {
				$tblUserFriend = new \Application\Entity\UserFriend ();
				$tblUserFriend->friend_id = $this->user_id;
				$tblUserFriend->user_id = $this->friend_id;
				$tblUserFriend->user_approve = 1;
				$this->dbAdapter->persist ( $tblUserFriend );
			}
		} catch ( \Exception $e ) {
			throw new Exception ( $e->getMessage () );
		}
	} // end addFriendRevRec(...)
} // end class

?>
