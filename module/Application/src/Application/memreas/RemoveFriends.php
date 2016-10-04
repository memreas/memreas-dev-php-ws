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
use Application\Entity\EventFriend;

class RemoveFriends {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		try {
			$data = simplexml_load_string ( $_POST ['xml'] );
			$friend_ids = $data->removefriends->friend_ids->friend_id;
			$user_id = $data->removefriends->user_id;
			if (! empty ( $friend_ids )) {
				$friendList = array ();
				foreach ( $friend_ids as $friend_id ) {
					$friendList [] = "'" . $friend_id . "'";
				}
				
				$friendList = implode ( ', ', $friendList );
				

				//assume true unless exception
				$friend_result = true;
				
				// remove friend from user's user_friend entry
				$query = "DELETE FROM Application\Entity\UserFriend uf 
									WHERE uf.friend_id IN ({$friendList}) 
									AND uf.user_id = '{$user_id}'";
				$friend_statement = $this->dbAdapter->createQuery ( $query );
				//$friend_result = $friend_statement->getResult ();
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'remove friend from users user_friend entry ::$query::', $query );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$friend_result::', $friend_result );
				
				// remove user from friend's user_friend entry
				$query = "DELETE FROM Application\Entity\UserFriend uf 
									WHERE uf.friend_id = '{$user_id}' 
									AND uf.user_id IN ({$friendList})";
				$friend_statement = $this->dbAdapter->createQuery ( $query );
				//$friend_result = $friend_statement->getResult ();
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::remove user from friends user_friend entry $query::', $query );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$friend_result::', $friend_result );
				
				// delete add notification
				$query = "DELETE FROM Application\Entity\Notification n
				WHERE n.sender_uid = '{$user_id}'
				AND n.receiver_uid IN ({$friendList})
				and n.notification_type = 'ADD_FRIEND'";
				$friend_statement = $this->dbAdapter->createQuery ( $query );
				//$friend_result = $friend_statement->getResult ();
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$query::', $query );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$friend_result::', $friend_result );
				
				// delete response notification
				$query = "DELETE FROM Application\Entity\Notification n 
									WHERE n.receiver_uid IN ({$friendList}) 
									AND n.sender_uid = '{$user_id}'
									and n.notification_type = 'ADD_FRIEND_RESPONSE'";
				$friend_statement = $this->dbAdapter->createQuery ( $query );
				$friend_result = $friend_statement->getResult ();
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$query::', $query );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$friend_result::', $friend_result );
				
				
			}
		} catch ( Exception $e ) {
			$friend_result = false;
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::exception::', $e->getMessage () );
		}
		
		if ($friend_result) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<removefriendsresponse>";
			$xml_output .= "<status>Success</status>";
			$xml_output .= "<message>friends removed</message>";
			$xml_output .= "</removefriendsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		} else {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<removefriendsresponse>";
			$xml_output .= "<status>Failure</status>";
			$xml_output .= "<message>error occurred</message>";
			$xml_output .= "</removefriendsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}
}
?>
