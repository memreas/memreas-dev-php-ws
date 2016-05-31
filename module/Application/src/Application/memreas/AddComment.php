<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;
use Application\memreas\Email;
use swearjar\Tester;

class AddComment {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	protected $AddNotification;
	protected $addTag;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		// error_log ( "Inside AddComment__construct..." );
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
		if (! $this->addTag) {
			$this->addTag = new AddTag ( $service_locator );
		}
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		$this->tester = new Tester ();
	}
	public function exec() {
		// error_log("Inside Add Comment exec()".PHP_EOL);
		error_log ( "Inside Add Comment _POST ['xml'] ---> " . $_POST ['xml'] . PHP_EOL );
		try {
			
			$data = simplexml_load_string ( $_POST ['xml'] );
			$event_id = trim ( $data->addcomment->event_id );
			$media_id = trim ( $data->addcomment->media_id );
			$comment = trim ( $data->addcomment->comments );
			$comment = strip_tags ( $comment, '<script><div><b><strong><i><p><a><img><ul><li><ol><i><u><em>' );
			$user_id = trim ( $data->addcomment->user_id );
			$audio_media_id = trim ( $data->addcomment->audio_media_id );
			$message = "";
			$time = time ();
			if (! isset ( $event_id ) || empty ( $event_id )) {
				$message = 'event id is empty';
				$status = 'Failure';
			} else if (empty ( $comment ) && empty ( $audio_media_id )) {
				throw new \Exception ( 'audio and text are empty' );
			} else if (empty ( $user_id )) {
				throw new \Exception ( 'user id is empty' );
			} else {
				$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
				$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $event_id );
				if (! $userOBj) {
					throw new \Exception ( 'user not found' );
				} else if (! $eventOBj) {
					throw new \Exception ( 'event_id not found' );
				} else {
						
					$type;
					if (! empty ( $comment )) {
						// profanity check
						if (! empty ( $comment )) {
							$comment = $this->tester->censor ( $comment );
						}
						$type = 'text';
						$uuid = MUUID::fetchUUID ();
						$tblComment = new \Application\Entity\Comment ();
						$tblComment->comment_id = $uuid;
						$tblComment->media_id = $media_id;
						$tblComment->audio_id = '';
						$tblComment->user_id = $user_id;
						$tblComment->type = $type;
						$tblComment->event_id = $event_id;
						$tblComment->text = $comment;
						$tblComment->create_time = $time;
						$tblComment->update_time = $time;
						$this->dbAdapter->persist ( $tblComment );
						$this->dbAdapter->flush ();
					}
					if (! empty ( $audio_media_id )) {
						$type = 'audio';
						$uuid = MUUID::fetchUUID ();
						$tblComment = new \Application\Entity\Comment ();
						$tblComment->comment_id = $uuid;
						$tblComment->media_id = $media_id;
						$tblComment->audio_id = $audio_media_id;
						$tblComment->user_id = $user_id;
						$tblComment->type = $type;
						$tblComment->event_id = $event_id;
						$tblComment->text = '';
						$tblComment->create_time = $time;
						$tblComment->update_time = $time;
						$this->dbAdapter->persist ( $tblComment );
						$this->dbAdapter->flush ();
					}
					$status = 'success';
					$message = "Comment successfully added";
					
					if ($status != 'failure') {
						// add tags
						$metaTag ['comment'] [] = $uuid;
						$metaTag ['event'] [] = $event_id;
						$metaTag ['media'] [] = $media_id;
						$metaTag ['user'] [] = $user_id;
						
						// add tags
						$this->addTag->getEventname ( $comment, $metaTag );
						// $this->addTag->getUserName($comment,$metaTag);
						$this->addTag->getKeyword ( $comment, $metaTag );
						
						// send notification owner of the event and all who commented.
						$qb = $this->dbAdapter->createQueryBuilder ();
						$qb->select ( 'f.network,f.friend_id' );
						$qb->from ( 'Application\Entity\Friend', 'f' );
						$qb->join ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = f.friend_id AND ef.user_approve=1' );
						$qb->where ( 'ef.event_id = ?1 AND ef.friend_id != ?2' );
						$qb->setParameter ( 1, $event_id );
						$qb->setParameter ( 2, $user_id );
						
						// Check if comment is made by owner or not
						$eventRepo = $this->dbAdapter->getRepository ( 'Application\Entity\Event' );
						$efusers = $qb->getQuery ()->getResult ();
						$nmessage = $userOBj->username . ' has commented on !' . $eventOBj->name . ' event';
						
						// add event owner in notifcation list
						if ($eventOBj->user_id != $user_id) {
							$efusers [] = array (
									'network' => 'memreas',
									'friend_id' => $eventOBj->user_id 
							);
						}
						foreach ( $efusers as $ef ) {
							/**
							 * Build array and send notifications...
							 */
							$data = array ();
							$data ['addNotification'] ['sender_uid'] = $user_id;
							$data ['addNotification'] ['receiver_uid'] = $ef ['friend_id'];
							$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_COMMENT;
							$data ['addNotification'] ['notification_methods'] [] = 'email';
							$data ['addNotification'] ['notification_methods'] [] = 'push_notification';
							$meta ['sent'] ['event_id'] = $event_id;
							$meta ['sent'] ['event_name'] = $eventOBj->name;
							$meta ['sent'] ['from_id'] = $user_id;
							$meta ['sent'] ['from_username'] = $userOBj->username;
							$meta ['sent'] ['comment_id'] = $uuid;
							$meta ['sent'] ['media_id'] = $media_id;
							$meta ['sent'] ['comment'] = $nmessage;
							$data ['addNotification'] ['meta'] = json_encode ( $meta );
							Mlog::add ( __CLASS__ . __METHOD__ . '::$data.addNotification...' );
							Mlog::add ( $data, 'j', 1 );
							
							// add notification in db.
							$result = $this->AddNotification->exec ( $data );
							
							if ($ef ['network'] == 'memreas') {
								$this->notification->add ( $ef ['friend_id'] );
								$friendUser = $eventRepo->getUser ( $ef ['friend_id'], 'row' );
								Email::$item ['type'] = 'user-comment';
								Email::$item ['friend_name'] = $friendUser ['username'];
								Email::$item ['user_name'] = $userOBj->username;
								Email::$item ['event_name'] = $eventOBj->name;
								
								Email::$item ['email'] = $friendUser ['email_address'];
								Email::$item ['message'] = $comment;
								Email::collect ();
							}
						} // end for loop
						
						$this->notification->setMessage ( $comment );
						$this->notification->type = \Application\Entity\Notification::ADD_COMMENT;
						$this->notification->event_id = $event_id;
						$this->notification->media_id = $media_id;
						$this->notification->send ();
						
						Email::sendmail ( $this->service_locator );
					}
				}
			}
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message = $e->getMessage ();
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<addcommentresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "</addcommentresponse>";
		$xml_output .= "</xml>";
		error_log ( "Leaving Add Comment exec() - xml_output-->" . $xml_output . PHP_EOL );
		echo $xml_output;
	}
}