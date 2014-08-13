<?php

namespace Application\memreas;

use Application\memreas\MUUID;
use Application\memreas\Email;


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
	}
	public function exec() {
// error_log("Inside Add Comment exec()".PHP_EOL);
// error_log("Inside Add Comment _POST ['xml'] ---> ".$_POST ['xml'].PHP_EOL);

		$data = simplexml_load_string ( $_POST ['xml'] );
		$event_id = trim ( $data->addcomment->event_id );
		$media_id = trim ( $data->addcomment->media_id );
		$comment = trim ( $data->addcomment->comments );
		$user_id = trim ( $data->addcomment->user_id );
		$audio_media_id = trim ( $data->addcomment->audio_media_id );
		$message = "";
		$time = time ();
		if (!isset ( $event_id ) || empty( $event_id )) {
			$message = 'event id is empty';
			$status = 'Failure';
		} else if (empty ( $media_id )) {
			$message = 'media_id is empty';
			$status = 'Failure';
		} else if (
					empty($comment) &&
					(
						!isset($audio_media_id) || empty($audio_media_id) 
					)
				  )
		{
			$messages = 'comment is empty';
			$status = 'Failure';
		} else if (empty ( $user_id )) {
			$messages = 'user_id is empty';
			$status = 'Failure';
		} else {
			$uuid = MUUID::fetchUUID ();
			$tblComment = new \Application\Entity\Comment ();
			$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
			$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $event_id );
				
			if (!isset($audio_media_id) || empty($audio_media_id)) {

				$tblComment->comment_id = $uuid;
				$tblComment->media_id = $media_id;
				$tblComment->user_id = $user_id;
				$tblComment->type = 'text';
				$tblComment->event_id = $event_id;
				$tblComment->text = $comment;
				$tblComment->create_time = $time;
				$tblComment->update_time = $time;
				$this->dbAdapter->persist ( $tblComment );
				$this->dbAdapter->flush();

				$status = 'success';
// error_log("Inserted Comment without audio_media_id ---> ".$audio_media_id.PHP_EOL);
			} else {
				$tblComment->comment_id = $uuid;
				$tblComment->media_id = $media_id;
				$tblComment->user_id = $user_id;
				$tblComment->type = 'audio';
				$tblComment->event_id = $event_id;
				$tblComment->text = $comment;
				$tblComment->audio_id = $audio_media_id;
				$tblComment->create_time = $time;
				$tblComment->update_time = $time;
				$this->dbAdapter->persist ( $tblComment );
				$this->dbAdapter->flush ();

				$status = 'success';
// error_log("Inserted Comment with audio_media_id ---> ".$audio_media_id.PHP_EOL);
			}

			$message = "Comment successfuly added";

			if ($status == 'failure') {
				$status = 'failure';
			} else {

				// add tags
				$metaTag['comment'][] = $uuid;
				$metaTag['event'][] = $event_id;
				$metaTag['media'][] = $media_id;
				$metaTag['user'] [] = $user_id;
						

				// add tags
				$this->addTag->getEventname($comment,$metaTag);
				// $this->addTag->getUserName($comment,$metaTag);
				$this->addTag->getKeyword ( $comment, $metaTag );

				//  send notification owner of the event and all who commented.
				$query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
				$qb = $this->dbAdapter->createQueryBuilder ();
				$qb->select ( 'f.network,f.friend_id' );
				$qb->from ( 'Application\Entity\Friend', 'f' );
				$qb->join ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = f.friend_id AND ef.user_approve=0' );
				$qb->where ( 'ef.event_id = ?1' );
				$qb->setParameter ( 1, $event_id );

                //Check if comment is made by owner or not
               

                    $efusers = $qb->getQuery ()->getResult ();
                    $nmessage = $userOBj->username . ' Has commented on !' . $eventOBj->name . ' event';

                    $cdata ['addNotification'] ['meta'] = $nmessage;
                    foreach ( $efusers as $ef ) {
                    	if ($eventOBj->user_id == $ef ['friend_id']) continue;

                        $cdata = array (
                                    'addNotification' => array (
                                            'network_name' => $ef ['network'],
                                            'user_id' => $eventOBj->user_id,
                                            'meta' => $nmessage,
                                            'notification_type' => \Application\Entity\Notification::ADD_COMMENT,
                                            'links' => json_encode ( array (
                                                    'event_id' => $event_id,
                                                    'from_id' => $user_id,
                                                    'comment_id' => $uuid
                                            ) )
                                    )
                            );
                        if ($ef ['network'] == 'memreas') {
                            $this->notification->add ( $ef ['friend_id'] );
                        } else {
                            $this->notification->addFriend ( $ef ['friend_id'] );
                        }
                        $this->AddNotification->exec ( $cdata );
                        Email::$item['type'] ='user-comment';
                    	Email::$item['name'] =$userOBj->username;
                		Email::$item['email'] =$userOBj->email_address;
                		Email::$item['message'] =$ndata ['addNotification'] ['meta'];
                		Email::collect();
                    }

                    $this->notification->setMessage ( $cdata ['addNotification'] ['meta'] );
                    $this->notification->type = \Application\Entity\Notification::ADD_COMMENT;
                    $this->notification->event_id = $event_id;
                    $this->notification->media_id = $media_id;
                    $this->notification->send ();
                    
                	Email::sendmail($this->service_locator);

                
			}

			// echo '<pre>';print_r($result);exit;
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";

		$xml_output .= "<addcommentresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";

		$xml_output .= "</addcommentresponse>";
		$xml_output .= "</xml>";
		
error_log("Leaving Add Comment exec()".PHP_EOL);
echo $xml_output;
	}
}