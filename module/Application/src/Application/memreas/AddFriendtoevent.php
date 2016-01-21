<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Zend\View\Model\ViewModel;
use Application\memreas\Email;

class AddFriendtoevent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	protected $AddNotification;
	protected $email;
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
				error_log ( "Enter AddFriendtoevent.exec() xml ----> " . $_POST ['xml'] . PHP_EOL );
			} else {
				$data = simplexml_load_string ( $frmweb );
				error_log ( "Enter AddFriendtoevent.exec() frmweb ----> " . $frmweb . PHP_EOL );
			}

			/**
			 * fetch input vars
			 */
			$friend_array = $data->addfriendtoevent->friends->friend;
			$user_id = (trim ( $data->addfriendtoevent->user_id ));
			$event_id = (trim ( $data->addfriendtoevent->event_id ));
			//$group_array = (trim ( $data->addfriendtoevent->groups ));
			$email_array = $data->addfriendtoevent->emails->email;
			
			$status = "Success";
			$message = "move to memreas tab";
			$time = time ();
			$error = 0;
			
			/**
			 * fetch user and event objects
			 */
			$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
			$eventRepo = $this->dbAdapter->getRepository ( 'Application\Entity\Event' );
			$eventOBj = $eventRepo->findOneBy ( array (
					'event_id' => $event_id 
			) );
			
			if (empty ( $userOBj ) || empty ( $eventOBj )) {
				throw new Exception ( "User or Event Not Found" );
			}
			
			/**
			 * add group to event_group
			 */
			//$result = $this->addToGroup ( $group_array );
			
			/**
			 * add friends to event loop
			 */
			if (! empty ( $friend_array ) && ! $error) {
				/*
				 * Level 3 Friend Checking here
				 */
				$allowAddFriends = $eventRepo->checkFriendLevelRule ( $event_id, $eventOBj->user_id, $user_id, $friend_id );
				Mlog::addone ( '$friendLevel', $friendLevel );
				
				foreach ( $friend_array as $key => $value ) {
					
					$network_name = addslashes ( trim ( $value->network_name ) );
					$friend_name = addslashes ( trim ( $value->friend_name ) );
					$friend_id = trim ( $value->friend_id );
					$profile_pic_url = isset ( $value->profile_pic_url ) ? stripslashes ( trim ( $value->profile_pic_url ) ) : '';
					
					/*
					 * Fetch from Friend table if exists
					 */
					$user_friend_query = "select uf from Application\Entity\UserFriend uf where uf.user_id='$user_id' and uf.friend_id='$friend_id' and uf.user_approve=1";
					$statement = $this->dbAdapter->createQuery ( $user_friend_query );
					$result_friend = $statement->getOneOrNullResult ();
					if ($result_friend) {
						/*
						 * Fetch friend from user by id or name
						 */
						if (! empty ( $friend_id )) {
							$friend = $this->dbAdapter->getRepository ( 'Application\Entity\User' )->findOneBy ( array (
									'user_id' => $friend_id,
									'disable_account' => 0 
							) );
						} else {
							$friend = $this->dbAdapter->getRepository ( 'Application\Entity\User' )->findOneBy ( array (
									'username' => $friend_name,
									'disable_account' => 0 
							) );
						}
						
						/*
						 * If friend exists set friend vars
						 */
						if (empty ( $friend )) {
							$message .= "$friend_name not found\n";
							continue;
						}
					} // end if ($result_friend)
					
					/*
					 * adding friend to event
					 */
					if (! empty ( $event_id ) && $error == 0) {
						
						Mlog::addone ( basename ( __FILE__ ) . '$event_id', $event_id );
						Mlog::addone ( basename ( __FILE__ ) . '$friend_id', $friend_id );
						$check_event_friend = "SELECT e FROM Application\Entity\EventFriend e  where e.event_id='" . $event_id . "' and e.friend_id='" . $friend_id . "'";
						$statement = $this->dbAdapter->createQuery ( $check_event_friend );
						$r = $statement->getResult ();
						
						if (count ( $r ) > 0) {
							Mlog::addone ( basename ( __FILE__ ) . 'EventFriend count > 0', '' );
							$status = "Success";
							// $error = 1;
							$message .= "$friend_name is already in your Event Friend list.";
							error_log ( "$friend_name is already in your Event Friend list. ---> $friend_id" . PHP_EOL );
						} else {
							// insert EventFriend
							$tblEventFriend = new \Application\Entity\EventFriend ();
							$tblEventFriend->friend_id = $friend_id;
							$tblEventFriend->event_id = $event_id;
							Mlog::addone ( basename ( __FILE__ ) . 'setting allowAddFriends', '...' );
							if (! $allowAddFriends) {
								$tblEventFriend->friend_level = $allowAddFriends;
							}
							
							try {
								$this->dbAdapter->persist ( $tblEventFriend );
								$this->dbAdapter->flush ();
								$message .= 'Event Friend Successfully added';
								$status = 'Success';
							} catch ( \Exception $exc ) {
								Mlog::addone ( basename ( __FILE__ ) . 'Exception', $exc->getMessage () );
								$message .= '';
								$status = 'failure';
							}
						}
						Mlog::addone ( basename ( __FILE__ ) . 'Finished add to event friend', '...' );
						
						/**
						 * Check if existing notification exists...
						 */
						$checkExistingNotificationQuery = "SELECT n FROM Application\Entity\Notification n
								  where n.sender_uid = '$user_id'
								  and n.receiver_uid = '$friend_id'
								  and n.notification_type = '" . \Application\Entity\Notification::ADD_FRIEND_TO_EVENT . "'";
						Mlog::addone ( basename ( __FILE__ ) . '$checkExistingNotificationQuery', $checkExistingNotificationQuery );
						$statement = $this->dbAdapter->createQuery ( $checkExistingNotificationQuery );
						$checkExistingNotificationResult = $statement->getArrayResult ();
						Mlog::add ( $checkExistingNotificationResult, 'p', 1 );
						if (! empty ( $checkExistingNotificationResult )) {
						/**
						 * TODO: Check for prior notification
						 */
						}
						
						/**
						 * Build array and send notifications...
						 */
						$data = array ();
						$data ['addNotification'] ['sender_uid'] = $user_id;
						$data ['addNotification'] ['receiver_uid'] = $friend_id;
						$data ['addNotification'] ['status'] = 0; // used on front end for button class??
						$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_FRIEND_TO_EVENT;
						$data ['addNotification'] ['notification_methods'] [] = 'email';
						$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
						$meta = array ();
						$meta ['sent'] ['sender_user_id'] = $user_id;
						$meta ['sent'] ['receiver_user_id'] = $friend_id;
						$meta ['sent'] ['event_id'] = $event_id;
						$meta ['sent'] ['message'] = $userOBj->username . ' invites you to !' . $eventOBj->name;
						$data ['addNotification'] ['meta'] = $meta;
						$this->AddNotification->exec ( $data );
						$this->notification->add ( $friend_id );
						if (! empty ( $data ['addNotification'] ['meta'] )) {
							// Email
							Email::sendEmailNotification ( $this->service_locator, $this->dbAdapter, $data ['addNotification'] ['receiver_uid'], $data ['addNotification'] ['sender_uid'], Email::EVENT_INVITE, $meta ['sent'] ['message'] );
							// Push Notification
							$this->notification->type = $data ['addNotification'] ['notification_type'];
							$this->notification->setMessage ( $this->notification->type, $meta ['sent'] ['message'] );
							$this->notification->send ();
						}
						$status = 'success';
						$message = 'friend add to event request sent';
					} // endif (!empty($event_id) && $error == 0)
				} // end foreach
			} // end if (!empty($friend_array))
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'friend request not sent ->' . $e->getMessage ();
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<addfriendtoeventresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>" . $message;
		if (! empty ( $message ))
			$xml_output .= " And " . $message;
		$xml_output .= "</message>";
		$xml_output .= "<event_id>$event_id</event_id>";
		$xml_output .= "</addfriendtoeventresponse>";
		$xml_output .= "</xml>";
		/**
		 * Send final xml output
		 */
		if ($frmweb == '') {
			echo $xml_output;
		}
		Mlog::addone ( __FILE__ . "output", $xml_output );
	} // end exec
	
	/**
	 *
	 * @method addToGroup
	 * @param array $group_array        	
	 * @return string
	 */
	private function addToGroup($group_array) {
		if (! empty ( $group_array ) && ! $error) {
			// error_log("Enter AddFriendtoevent.exec() - !empty(group_array)" . PHP_EOL);
			foreach ( $group_array as $key => $value ) {
				$group_id = $value->group->group_id;
				if ($group_id != 'null') {
					$check = "SELECT e  FROM  Application\Entity\EventGroup e  where e.event_id='" . $event_id . "' and e.group_id='" . $group_id . "'";
					$statement = $this->dbAdapter->createQuery ( $check );
					$result_check = $statement->getResult ();
					
					if ($result_check->count () > 0) {
						$message = 'event group already exist.';
						$status = 'Failure';
					} else {
						// insert
						$tblEventGroup = new \Application\Entity\EventGroup ();
						$tblEventGroup->group_id = $group_id;
						$tblEventGroup->event_id = $event_id;
						
						$this->dbAdapter->persist ( $tblEventGroup );
						$this->dbAdapter->flush ();
						
						$message = 'event group Successfully added ';
						$status = 'Success';
					}
				} // end if ($group_id != 'null')
			} // end foreach
		} // end if (!empty($group_array))
		return $status;
	}
} // end class
?>
