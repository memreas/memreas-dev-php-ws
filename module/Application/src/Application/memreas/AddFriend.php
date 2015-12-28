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

class AddFriend {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
	protected $AddTag;
	protected $notification;
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
	public function exec() {
		try {
			// error_log('file--->'. __FILE__ . ' method -->'. __METHOD__ . ' line number::' . __LINE__ . PHP_EOL);
			// error_log('xml--->'.$_POST ['xml'] . PHP_EOL);
			
			$data = simplexml_load_string ( $_POST ['xml'] );
			$message = ' ';
			$user_id = trim ( $data->addfriend->user_id );
			$friend_id = trim ( $data->addfriend->friend_id );
			$time = time ();
			if (empty ( $user_id )) {
				$message .= 'user id is empty';
				$status = 'Failure';
			} else if (empty ( $friend_id )) {
				$message .= 'user id is empty';
				$status = 'Failure';
			} else {
				/**
				 * Store user_friend table for request...
				 */
				$tblUserFriend = new \Application\Entity\UserFriend ();
				$tblUserFriend->user_id = $user_id;
				$tblUserFriend->friend_id = $friend_id;
				$this->dbAdapter->persist ( $tblUserFriend );
				$this->dbAdapter->flush ();
				
				/**
				 * Fetch user info...
				 */
				$user = $this->dbAdapter->find ( 'Application\Entity\Friend', $user_id );
				
				/**
				 * Build array and send notifications...
				 */
				$data = array ();
				$data ['addNotification'] ['sender_uid'] = $user_id;
				$data ['addNotification'] ['receiver_uid'] = $friend_id;
				$data ['addNotification'] ['status'] = 0; // used on front end for button class??
				$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_FRIEND;
				$data ['addNotification'] ['notification_methods'] [] = 'email';
				$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
				$meta = array ();
				$meta ['sent'] ['sender_user_id'] = $user_id;
				$meta ['sent'] ['receiver_user_id'] = $friend_id;
				$meta ['sent'] ['message'] = 'add friend request from @' . $_SESSION ['username'];
				// $meta['sent']['message'] = 'add friend request from @'.$user->username;
				$data ['addNotification'] ['meta'] = $meta;
				$this->AddNotification->exec ( $data );
				$this->notification->add ( $friend_id );
				if (! empty ( $data ['addNotification'] ['meta'] )) {
					
					// Email
					error_log ( 'AddFriend $receiver_uid--->' . $data ['addNotification'] ['receiver_uid'] . PHP_EOL );
					error_log ( 'AddFriend $sender_uid--->' . $data ['addNotification'] ['sender_uid'] . PHP_EOL );
					error_log ( 'AddFriend $type--->' . Email::FRIEND_REQUEST . PHP_EOL );
					Email::sendEmailNotification ( $this->service_locator, $this->dbAdapter, $data ['addNotification'] ['receiver_uid'], $data ['addNotification'] ['sender_uid'], Email::FRIEND_REQUEST, '' );
					
					// Push Notification
					$this->notification->type = $data ['addNotification'] ['notification_type'];
					$this->notification->setMessage ( $this->notification->type, $meta ['sent'] ['message'] );
					$this->notification->send ();
				}
			}
			$status = 'success';
			$message = 'add friend request sent';
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'friend request not sent ->' . $e->getMessage ();
		}
		
		ob_clean ();
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<addfriendresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		$xml_output .= "<message>" . $message . "</message>";
		$xml_output .= "</addfriendresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
