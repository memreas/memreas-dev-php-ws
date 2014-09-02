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
			$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
			$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $event_id );
			if(!$userOBj){
				$messages = 'user not found';
			    $status = 'Failure';
			} else if(!$eventOBj){
				$messages = 'event_id not found';
			    $status = 'Failure';
			}else {
				
			
			$uuid = MUUID::fetchUUID ();
			$tblComment = new \Application\Entity\Comment ();

			
				
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
 				$qb = $this->dbAdapter->createQueryBuilder ();
				$qb->select ( 'f.network,f.friend_id' );
				$qb->from ( 'Application\Entity\Friend', 'f' );
				$qb->join ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = f.friend_id AND ef.user_approve=1' );
				$qb->where ( 'ef.event_id = ?1 AND ef.friend_id != ?2' );
				$qb->setParameter ( 1, $event_id );
				$qb->setParameter ( 2, $user_id );

				

                 //Check if comment is made by owner or not
              	$eventRepo =  $this->dbAdapter->getRepository('Application\Entity\Event');
                $efusers = $qb->getQuery ()->getResult ();
                $nmessage = $userOBj->username . ' Has commented on !' . $eventOBj->name . ' event';

                    $cdata ['addNotification'] ['meta'] = $nmessage;

                    //add event owner in notifcation list
                    if($eventOBj->user_id != $user_id){
                    	 $efusers[] = array(
 										'network' => 'memreas',
										'friend_id' =>	$eventOBj->user_id
                    	 			) ;
                    	}
                    foreach ( $efusers as $ef ) {
 
                        $cdata = array (
                                    'addNotification' => array (
                                            'network_name' => $ef ['network'],
                                            'user_id' => $ef['friend_id'],
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
                            $friendUser = $eventRepo->getUser($ef['friend_id'],'row');
                            Email::$item['type'] ='user-comment';
                            Email::$item['name'] =$friendUser['username'];
                            Email::$item['email'] =$friendUser['email_address'];
                            Email::$item['message'] =$comment;
                            Email::collect();
                         } else {
                            $this->notification->addFriend ( $ef ['friend_id'] );
                        }
                        $this->AddNotification->exec ( $cdata );
                       
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