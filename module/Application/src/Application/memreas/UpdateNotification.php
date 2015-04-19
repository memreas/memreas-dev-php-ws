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
	protected $receiver_uid;
	protected $sender_uid;
	protected $AddNotification;
	protected $tblNotification;
	protected $notification_id;
	protected $notification_status;
	protected $notification_message;
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
					$this->notification_id = $notification->notification_id;
					$this->notification_status = '';
					if (! empty ( $notification->status )) {
						$this->notification_status = trim ( $notification->status );
					}
					$this->notification_message = '';
					if (! empty ( $notification->message )) {
						$this->notification_message = trim ( $notification->message );
					}
					error_log ( '$notification' . PHP_EOL . json_encode ( $notification, JSON_PRETTY_PRINT ) . PHP_EOL );
					// save notification in table
					$this->tblNotification = $this->dbAdapter->find ( "\Application\Entity\Notification", $this->notification_id );
					if (! $this->tblNotification) {
						$status = "failure";
						$message = "notification not found";
					} else {
						/*
						 * user_id = receiver_id (logged in user is receiving notification)
						 * friend_id = sender_id (user who sent notification)
						 */
						$this->user_id = $this->receiver_uid = $this->tblNotification->receiver_uid;
						$this->sender_uid = $this->tblNotification->sender_uid;
						$this->tblNotification->response_status = $this->notification_status;
						$this->tblNotification->is_read = 1;
						$this->tblNotification->update_time = $time;
						
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT) {
							$result = $this->handleAddFriendToEventResponse ();
							if (! $result) {
								throw new \Exception ( $e->getMessage () );
							}
						} // end add friend to event update
						
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND) {
							$this->handleAddFriendResponse ();
						} // end add friend update
						
						$this->dbAdapter->flush ();
						$status = "success";
						$message = "notification updated";
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
			$xml_output .= "<notification_id>$this->notification_id</notification_id>";
			$xml_output .= "</updatenotification>";
			$xml_output .= "</xml>";
			echo $xml_output;
			error_log ( 'UpdateNotification ----> ' . $xml_output . PHP_EOL );
		}
	}
	public function handleAddFriendResponse() {
		error_log ( 'Inside handleAddFriendResponse' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
		try {
			
			/*
			 * Here user_id is sender_id (logged in user who sent) and friend_id is receiver_id (friend who received the friend request)
			 */
			$UserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'friend_id' => $this->receiver_uid,
					'user_id' => $this->sender_uid 
			) );
			
			if (! $UserFriend) {
				throw new \Exception ( 'UserFriend not found' );
			} else {
				error_log ( 'Inside if ($UserFriend)' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->sender_uid );
				// accepted
				error_log ( 'Inside if ($UserFriend)' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				
				if (($this->notification_status == 1) || (strtolower ( $this->notification_status ) == 'accept')) {
					error_log ( 'Inside if ($UserFriend->user_approve = 1;)' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$this->notification_status = 'accept';
					$email_notification_status = 'accepted';
					$UserFriend->user_approve = 1;
					// save in user friend_friend
					$this->dbAdapter->persist ( $UserFriend );
					$nmessage = $userOBj->username . ' accepted friend request with message:' . ' ' . $this->notification_message;
					
					/*
					 * If the receiver accepts thes add the sender as a friend of the receiver
					 */
					error_log ( 'exec next $this->addFriendRevRec ( $this->receiver_uid, $this->sender_uid )' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$this->addFriendRevRec ( $this->receiver_uid, $this->sender_uid );
				}
				// declined
				if (($this->notification_status == 2) || (strtolower ( $this->notification_status ) == 'decline')) {
					$this->notification_status = 'decline';
					$email_notification_status = 'declined';
					error_log ( 'Inside if ($UserFriend->user_approve = 2;)' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$nmessage = $userOBj->username . ' declined friend request' . ' ' . $this->notification_message;
				}
				// ignored
				if (($this->notification_status == 3) || (strtolower ( $this->notification_status ) == 'ignore')) {
					$this->notification_status = 'ignore';
					error_log ( 'Inside if ($UserFriend->user_approve = 3;)' . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$nmessage = $userOBj->username . ' ignored friend request' . ' ' . $this->notification_message;
				}
				
				/*
				 * Update the existing notification table meta
				 */
				$meta = json_decode ( $this->tblNotification->meta, true );
				$meta ['received'] ['message'] = $nmessage;
				$this->tblNotification->meta = json_encode ( $meta );
				error_log ( 'Update notification table meta-->' . json_encode ( $meta ) . ' ::::file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				
				/*
				 * Add a new notification for the response
				 */
error_log('$this->receiver_uid-->'.$this->receiver_uid.' ::::file--->'. basename(__FILE__ ). PHP_EOL);					
error_log('$this->sender_uid-->'.$this->sender_uid.' ::::file--->'. basename(__FILE__ ). PHP_EOL);					
				/**
				 * Build array and send notifications...
				 */
				$data = array ();
				$data ['addNotification'] ['sender_uid'] = $this->receiver_uid;
				$data ['addNotification'] ['receiver_uid'] = $this->sender_uid;
				$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_FRIEND_RESPONSE;
				$data ['addNotification'] ['notification_methods'] [] = 'email';
				$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
				$data ['addNotification'] ['meta'] = $meta;
				
				// add notification in db.
				$result = $this->AddNotification->exec ( $data );
				$data = simplexml_load_string ( $result );
				error_log ( 'AddNotification ----> ' . $result . PHP_EOL );
				error_log ( 'AddNotification id----> ' . $data->notification_id . PHP_EOL );
				
				if ($this->notification_status != 3) {
					// send email (reversed due to response)
					$email_sender_uid = $this->receiver_uid;
					$email_receiver_uid = $this->sender_uid;
					Email::sendEmailNotification ( $this->service_locator, $this->dbAdapter, $email_receiver_uid, $email_sender_uid, Email::FRIEND_REQUEST_RESPONSE, $email_notification_status, $nmessage );
					// send push message add user id
					$this->notification->add ( $this->receiver_uid );
				}
			} // user friend updated
		} catch ( \Exception $e ) {
			throw new \Exception ( $e->getMessage () );
		}
	} // end handleAddFriendResponse()
	public function handleAddFriendToEventResponse() {
		try {
			// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT".PHP_EOL);
			$this->sender_uid = $this->tblNotification->user_id;
			$json_data = json_decode ( $this->tblNotification->links, true );
			
			$UserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'user_id' => $this->receiver_uid,
					'friend_id' => $this->sender_uid 
			) );
			$EventFriend = $this->dbAdapter->getRepository ( "\Application\Entity\EventFriend" )->findOneBy ( array (
					'event_id' => $json_data ['event_id'],
					'friend_id' => $this->sender_uid 
			) );
			$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $json_data ['event_id'] );
			if ($UserFriend) {
				// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if userfriend...status is $status".PHP_EOL);
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->sender_uid );
				// accepted
				if ($this->notification_status == 1) {
					$UserFriend->user_approve = 1;
					$this->dbAdapter->persist ( $UserFriend );
					$EventFriend->user_approve = 1;
					$this->dbAdapter->persist ( $EventFriend );
					$nmessage = $userOBj->username . ' Accepted ' . $eventOBj->name . ' ' . $this->notification_message;
					/*
					 * If the receiver accepts thes add the sender as a friend of the receiver
					 */
					$this->addFriendRevRec ( $this->receiver_uid, $this->sender_uid );
					// error_log("UpdateNotification::ADD_FRIEND_TO_EVENT->Inside if status==1 ... just set event_friend".PHP_EOL);
				}
				// decline
				if ($this->notification_status == 2) {
					$nmessage = $userOBj->username . ' declined ' . $eventOBj->name . ' ' . $this->notification_message;
				}
				// ignored
				if ($this->notification_status == 3) {
					$nmessage = $userOBj->username . ' ignored ' . $eventOBj->name . ' ' . $this->notification_message;
				}
				
				// save nofication intable
				$ndata = array (
						'addNotification' => array (
								'network_name' => 'memreas',
								'user_id' => $this->receiver_uid,
								'meta' => $nmessage,
								'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT_RESPONSE,
								'links' => json_encode ( $json_data ) 
						) 
				);
				
				if ($this->notification_status != 3) {
					// send push message add user id
					$this->notification->add ( $this->receiver_uid );
					// add notification in db.
					$this->AddNotification->exec ( $ndata );
				}
			} // user friend updated
		} catch ( \Exception $e ) {
			throw new \Exception ( $e->getMessage () );
		}
	} // end handleAddFriendToEventResponse()
	protected function addFriendRevRec() {
		try {
			/*
			 * If the receiver accepts then add the sender as a friend of the receiver
			 */
			$time = time ();
			$inUserFriend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
					'user_id' => $this->sender_uid,
					'friend_id' => $this->receiver_uid 
			) );
			
			$inFriend = $this->dbAdapter->find ( 'Application\Entity\Friend', $this->receiver_uid );
			
			if (! $inFriend) {
				$profile_pic = $this->dbAdapter->getRepository ( 'Application\Entity\Media' )->findOneBy ( array (
						'user_id' => $this->receiver_uid,
						'is_profile_pic' => '1' 
				) );
				$profile_pic_url = MC::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
				if ($profile_pic) {
					$metadata = $profile_pic->metadata;
					$profile_image = json_decode ( $metadata, true );
					$profile_pic_url = $profile_image ['S3_files'] ['path'];
				}
				
				$userFOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->receiver_uid );
				
				$tblFriend = new \Application\Entity\Friend ();
				$tblFriend->friend_id = $this->receiver_uid;
				$tblFriend->network = 'memreas';
				$tblFriend->social_username = empty ( $userFOBj ) ? '' : $userFOBj->username;
				$tblFriend->url_image = $profile_pic_url;
				$tblFriend->create_date = $time;
				$tblFriend->update_date = $time;
				
				$this->dbAdapter->persist ( $tblFriend );
			}
			
			if (! $inUserFriend) {
				$tblUserFriend = new \Application\Entity\UserFriend ();
				$tblUserFriend->friend_id = $this->receiver_uid;
				$tblUserFriend->user_id = $this->sender_uid;
				$tblUserFriend->user_approve = 1;
				$this->dbAdapter->persist ( $tblUserFriend );
			}
		} catch ( \Exception $e ) {
			throw new \Exception ( $e->getMessage () );
		}
	} // end addFriendRevRec(...)
} // end class

?>
