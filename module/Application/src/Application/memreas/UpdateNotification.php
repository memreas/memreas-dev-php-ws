<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use \Exception;

class UpdateNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	public $user_id;
	public $friend_id;
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
		Mlog::addone ( 'UpdateNotification.exec()', '...' );
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
						/**
						 *  
						 * NOTE: We're updating an existing notification so...
						 * user_id = receiver_id (logged in user is receiving notification)
						 * friend_id = sender_id (user who sent notification)
						 */
						$this->user_id = $this->sender_uid = $this->tblNotification->receiver_uid;
						$this->friend_id = $this->receiver_uid = $this->tblNotification->sender_uid;
						$this->tblNotification->response_status = $this->notification_status;
						$this->tblNotification->is_read = 1;
						$this->tblNotification->update_time = time ();
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT) {
							$result = $this->handleAddFriendToEventResponse ();
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->tblNotification->notification_type::ADD_FRIEND_TO_EVENT-->', $result );
							if (! $result) {
								throw new \Exception ( $e->getMessage ( 'error in handleAddFriendToEventResponse' ) );
							}
						} // end add friend to event update
						
						if ($this->tblNotification->notification_type == \Application\Entity\Notification::ADD_FRIEND) {
							$result = $this->handleAddFriendResponse ();
							if (! $result) {
								Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->tblNotification->notification_type::ADD_FRIEND-->', $result );
								throw new \Exception ( 'error in handleAddFriendResponse' );
							}
						} // end add friend update
						
						/**
						 * Flush all persisted statements to the db!
						 */
						$this->dbAdapter->flush ();
						$status = "success";
						$message = "notification updated";
						
						//
						// Invalidate listnotifications for sender and receiver
						//
						$redis = AWSMemreasRedisCache::getHandle();
						$redis->invalidateNotifications ( $this->sender_uid );
						$redis->invalidateNotifications ( $this->receiver_uid );
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
	public function handleAddFriendToEventResponse() {
		try {
			$json_data = json_decode ( $this->tblNotification->meta, true );
			Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$this->tblNotification->meta', $this->tblNotification->meta );
			$EventFriend = $this->dbAdapter->getRepository ( "\Application\Entity\EventFriend" )->findOneBy ( array (
					'event_id' => $json_data ['sent'] ['event_id'],
					'friend_id' => $this->sender_uid 
			) );
			$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $json_data ['sent'] ['event_id'] );
			$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->sender_uid );
			
			if (($this->notification_status == 1) || (strtolower ( $this->notification_status ) == 'accept')) {
				// accepted
				/**
				 * Update status for event_friend table
				 */
				$EventFriend->user_approve = 1;
				$this->dbAdapter->persist ( $EventFriend );
				
				$this->notification_status = 'accept';
				$email_notification_status = 'accepted';
			} else if (($this->notification_status == 2) || (strtolower ( $this->notification_status ) == 'decline')) {
				// declined
				$this->notification_status = 'decline';
				$email_notification_status = 'declined';
			} else if (($this->notification_status == 3) || (strtolower ( $this->notification_status ) == 'ignore')) {
				// ignored
				$this->notification_status = 'ignore';
			}
			$nmessage = "$userOBj->username has $email_notification_status your invite to !$eventOBj->name";
			if (! empty ( $this->notification_message )) {
				$nmessage .= " with message: $this->notification_message";
			}
			
			$result = $this->handleNotification ( \Application\Entity\Notification::ADD_FRIEND_TO_EVENT_RESPONSE, Email::EVENT_INVITE_RESPONSE, $nmessage );
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$e->getMessage ()', $e->getMessage () );
			throw new \Exception ( $e->getMessage () );
		}
		return $result;
	} // end handleAddFriendToEventResponse()
	public function handleAddFriendResponse() {
		try {
			
			/*
			 * Here user_id is sender_id (logged in user who sent) and friend_id is receiver_id (friend who received the friend request)
			 */
			
			$user_friend = $this->checkInUserFriend ( $this->receiver_uid, $this->sender_uid );
			if (! empty ( $user_friend )) {
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $this->sender_uid );
				// accepted
				if (($this->notification_status == 1) || (strtolower ( $this->notification_status ) == 'accept')) {
					$this->notification_status = 'accept';
					$email_notification_status = 'accepted';
					$nmessage = $userOBj->username . ' accepted friend request with message:' . ' ' . $this->notification_message;
					
					/*
					 * If the receiver accepts thes add the sender as a friend of the receiver
					 */
					$this->addFriendRevRec ( $this->sender_uid, $this->receiver_uid );
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
				
				$result = $this->handleNotification ( \Application\Entity\Notification::ADD_FRIEND_RESPONSE, Email::FRIEND_REQUEST_RESPONSE, $nmessage );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->handleNotification::$result', $result );
				return $result;
				// user friend updated
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::', 'empty user friend entry - check parameters...' );
				throw new Exception ( 'empty user friend entry - check parameters...' );
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$e->getMessage ()', $e->getMessage () );
			throw new \Exception ( $e->getMessage () );
		}
	} // end handleAddFriendResponse()
	protected function addFriendRevRec($sender_id, $receiver_id) {
		try {
			/**
			 * Add to UserFriend
			 */
			$inUserFriend = $this->addUserFriend ( $this->sender_uid, $this->receiver_uid );
			// Mlog::addone ( '$this->addUserFriend($this->sender_uid, $this->receiver_uid);', 'inserted...' );
			/**
			 * If sender or receiver isn't in Friend add...
			 */
			$this->addFriend ( $this->receiver_uid );
			$this->addFriend ( $this->sender_uid );
		} catch ( \Exception $e ) {
			throw new \Exception ( $e->getMessage () );
		}
	} // end addFriendRevRec(...)
	protected function checkInUserFriend($user_id, $friend_id) {
		$user_friend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
				'user_id' => $user_id,
				'friend_id' => $friend_id 
		) );
		if ((! empty ( $user_friend )) && ($user_friend->user_approve == 0)) {
			$user_friend->user_approve = 1;
			$this->dbAdapter->persist ( $user_friend );
		}
		return $user_friend;
	}
	protected function addUserFriend($user_id, $friend_id) {
		// Mlog::addone ( 'addUserFriend($user_id, $friend_id)', '...' );
		// Mlog::addone ( 'addUserFriend($user_id, $friend_id)', $user_id );
		// Mlog::addone ( 'addUserFriend($user_id, $friend_id)', $friend_id );
		$user_friend = $this->dbAdapter->getRepository ( "\Application\Entity\UserFriend" )->findOneBy ( array (
				'user_id' => $user_id,
				'friend_id' => $friend_id 
		) );
		// Mlog::add ( $user_friend, 'p' );
		// Mlog::out ();
		if (empty ( $user_friend )) {
			$tblUserFriend = new \Application\Entity\UserFriend ();
			$tblUserFriend->friend_id = $friend_id;
			$tblUserFriend->user_id = $user_id;
			$tblUserFriend->user_approve = 1;
			$user_friend = $this->dbAdapter->persist ( $tblUserFriend );
			// Mlog::add ( $user_friend, 'p' );
		}
		
		return $user_friend;
	}
	protected function addFriend($friend_id) {
		$inFriend = $this->dbAdapter->find ( 'Application\Entity\Friend', $friend_id );
		if (! $inFriend) {
			$profile_pic = $this->dbAdapter->getRepository ( 'Application\Entity\Media' )->findOneBy ( array (
					'user_id' => $friend_id,
					'is_profile_pic' => '1' 
			) );
			$profile_pic_url = ''; // signing url will return static profile pic
			if ($profile_pic) {
				$metadata = $profile_pic->metadata;
				$profile_image = json_decode ( $metadata, true );
				$profile_pic_url = $profile_image ['S3_files'] ['thumbnails'] ['79x80'] [0];
			}
			
			$userFOBj = $this->dbAdapter->find ( 'Application\Entity\User', $friend_id );
			
			$time = time ();
			$tblFriend = new \Application\Entity\Friend ();
			$tblFriend->friend_id = $friend_id;
			$tblFriend->network = 'memreas';
			$tblFriend->social_username = $userFOBj->username;
			$tblFriend->url_image = $profile_pic_url;
			$tblFriend->create_date = $time;
			$tblFriend->update_date = $time;
			
			$inFriend = $this->dbAdapter->persist ( $tblFriend );
		}
		return $inFriend;
	}
	protected function handleNotification($response_type, $email_type, $nmessage) {
		/*
		 * Update the existing notification table meta
		 */
		$meta = json_decode ( $this->tblNotification->meta, true );
		// Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$this->tblNotification->meta', $this->tblNotification->meta );
		$meta ['received'] ['message'] = $nmessage;
		$this->tblNotification->meta = json_encode ( $meta );
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$meta', $meta );
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$this->sender_uid', $this->sender_uid );
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$this->receiver_uid', $this->receiver_uid );
		
		/**
		 * Build array and send notifications...
		 *  - note sender 
		 */
		$data = array ();
		$data ['addNotification'] ['sender_uid'] = $this->user_id;
		$data ['addNotification'] ['receiver_uid'] = $this->friend_id;
		$data ['addNotification'] ['notification_type'] = $response_type;
		$data ['addNotification'] ['notification_methods'] [] = 'email';
		$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
		$data ['addNotification'] ['meta'] = $meta;
		
		// add notification in db.
		$result = $this->AddNotification->exec ( $data );
		
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$this->AddNotification->exec ( $data )->$result', $result );
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$data->notification_id', $data->notification_id );
		
		//
		// Send notification of email or push notification if !ignore
		//
		if ( !((strtolower ( $this->notification_status ) == 'ignore') || ($this->notification_status == 3))) {
			// send email
			$email_sender_uid = $this->user_id;
			$email_receiver_uid = $this->friend_id;
			Email::sendEmailNotification ( $this->service_locator, $this->dbAdapter, $email_receiver_uid, $email_sender_uid, $email_type, $this->notification_status, $nmessage );

			// send push message to receiver_uid so add friend_id
			$result = $this->notification->add ( $this->friend_id );
		}
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->notification->add ( $this->friend_id )::$result', $result );
		return $result;
	}
} // end class

?>
