<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\AddNotification;
use Application\memreas\MUUID;
use \Exception;
use Application\memreas\Email;

class AddMediaEvent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
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
		$this->url_signer = new MemreasSignedURL ();
	}
	public function exec() {
		$is_audio = false;
		try {
			$media_id = '';
			if (isset ( $_POST ['xml'] ) && ! empty ( $_POST ['xml'] )) {
				error_log ( "AddMediaEvent _POST ['xml'] ----> " . $_POST ['xml'] . PHP_EOL );
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (isset ( $data->addmediaevent->user_id )) {
					$user_id = addslashes ( trim ( $data->addmediaevent->user_id ) );
				} else {
					throw new \Exception ( 'Error : User ID is empty' );
				}
				if (isset ( $data->addmediaevent->device_id )) {
					$device_id = addslashes ( trim ( $data->addmediaevent->device_id ) );
				} else {
					throw new \Exception ( 'Error : device id is empty' );
				}
				if (isset ( $data->addmediaevent->device_type )) {
					$device_type = addslashes ( trim ( $data->addmediaevent->device_type ) );
				} else {
					throw new \Exception ( 'Error : device type is empty' );
				}
				$event_id = isset ( $data->addmediaevent->event_id ) ? addslashes ( trim ( $data->addmediaevent->event_id ) ) : '';
				$media_id = isset ( $data->addmediaevent->media_id ) ? addslashes ( trim ( $data->addmediaevent->media_id ) ) : '';
				$is_profile_pic = isset ( $data->addmediaevent->is_profile_pic ) ? addslashes ( trim ( $data->addmediaevent->is_profile_pic ) ) : 0;
				$is_server_image = addslashes ( trim ( $data->addmediaevent->is_server_image ) );
				$content_type = addslashes ( trim ( $data->addmediaevent->content_type ) );
				$s3url = addslashes ( trim ( $data->addmediaevent->s3url ) );
				$s3file_name = addslashes ( trim ( $data->addmediaevent->s3file_name ) );
				$s3file_basename_prefix = pathinfo ( basename ( $s3file_name ) )['filename'];
				$location = json_decode ( $data->addmediaevent->location );
				$email = isset ( $data->addmediaevent->email ) ? addslashes ( trim ( $data->addmediaevent->email ) ) : '';
			} else {
				// Old code uses POST
				// Fetch user_id
				if (isset ( $_POST ['user_id'] )) {
					$user_id = addslashes ( trim ( $_POST ['user_id'] ) );
				} else {
					throw new \Exception ( 'Error : User ID is empty' );
				}
				if (isset ( $_POST ['device_id'] )) {
					$device_id = addslashes ( trim ( $_POST ['device_id'] ) );
				} else {
					throw new \Exception ( 'Error : Device ID is empty' );
				}
				$event_id = (isset ( $_POST ['event_id'] )) ? trim ( $_POST ['event_id'] ) : '';
				$media_id = (isset ( $_POST ['media_id'] )) ? trim ( $_POST ['media_id'] ) : '';
				$is_profile_pic = isset ( $_POST ['is_profile_pic'] ) ? trim ( $_POST ['is_profile_pic'] ) : 0;
				$is_server_image = isset ( $_POST ['is_server_image'] ) ? $_POST ['is_server_image'] : 0;
				$content_type = isset ( $_POST ['content_type'] ) ? $_POST ['content_type'] : '';
				$s3file_name = isset ( $_POST ['s3file_name'] ) ? $_POST ['s3file_name'] : '';
				$s3file_basename_prefix = pathinfo ( basename ( $s3file_name ) )['filename'];
				$email = isset ( $_POST ['email'] ) ? $_POST ['email'] : '';
				$s3url = isset ( $_POST ['s3url'] ) ? $_POST ['s3url'] : '';
				$location = isset ( $_POST ['location'] ) ? $_POST ['location'] : '';
				
				error_log ( "event_id ---> " . $event_id . PHP_EOL );
				error_log ( "media_id ---> " . $media_id . PHP_EOL );
				error_log ( "is_profile_pic ---> " . $is_profile_pic . PHP_EOL );
				error_log ( "is_server_image ---> " . $is_server_image . PHP_EOL );
				error_log ( "content_type ---> " . $content_type . PHP_EOL );
				error_log ( "s3file_name ---> " . $s3file_name . PHP_EOL );
				error_log ( "s3url ---> " . $s3url . PHP_EOL );
				error_log ( "email ---> " . $email . PHP_EOL );
				// error_log ( "location ---> " . $location . PHP_EOL ); // object json..
			}
			$time = time ();
			
			// ////////////////////////////////////////////////////////////////////
			// dont upload file if server image just insert into event_media table
			// ////////////////////////////////////////////////////////////////////
			if ($is_server_image == 1) {
				error_log ( "AddMediaEvent exec is_server_image == 1 " . PHP_EOL );
				if (! isset ( $media_id ) || empty ( $media_id )) {
					throw new \Exception ( 'Error : media_id is empty' );
				}
				$tblEventMedia = new \Application\Entity\EventMedia ();
				$tblEventMedia->media_id = $media_id;
				$tblEventMedia->event_id = $event_id;
				$this->dbAdapter->persist ( $tblEventMedia );
				$this->dbAdapter->flush ();
				$status = 'Success';
				$message = "Media Successfully add";
			} else {
				// ///////////////////////////////////////////////
				// insert into media and event media
				// ///////////////////////////////////////////////
				// error_log ( "AddMediaEvent exec is_server_image == 1 else " . PHP_EOL );
				$is_video = 0;
				$is_audio = 0;
				$s3path = $user_id . '/';
				$media_id = MUUID::fetchUUID ();
				// ///////////////////////////////////////
				// create metadata based on content type
				// ///////////////////////////////////////
				$file_type = explode ( '/', $content_type );
				
				if (strcasecmp ( $file_type [0], 'image' ) == 0) {					
					$s3path = $user_id . '/';
					$json_array = array ();
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset ( $s3file_name )) ? $s3path . $s3file_name : $s3url;
					$json_array ['S3_files'] ['s3file_name'] = $s3file_name;
					$json_array ['S3_files'] ['s3file_basename_prefix'] = $s3file_basename_prefix;
					$json_array ['S3_files'] ['bucket'] = S3BUCKET;
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_id'] = $device_id;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_type'] = $device_type;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['type'] ['image'] ['format'] = $file_type [1];
				} else if (strcasecmp ( 'video', $file_type [0] ) == 0) {
					$is_video = 1;
					$s3path = $user_id . '/';
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset ( $s3file_name )) ? $s3path . $s3file_name : $s3url;
					$json_array = array ();
					$json_array ['S3_files'] ['s3file_name'] = $s3file_name;
					$json_array ['S3_files'] ['s3file_basename_prefix'] = $s3file_basename_prefix;
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['bucket'] = S3BUCKET;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_id'] = $device_id;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_type'] = $device_type;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['is_video'] = $is_video;
					$json_array ['S3_files'] ['type'] ['video'] ['format'] = $file_type [1];
				} else if (strcasecmp ( 'audio', $file_type [0] ) == 0) {
					$is_audio = 1;
					$s3path = $user_id . '/';
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset ( $s3file_name )) ? $s3path . $s3file_name : $s3url;
					$json_array = array ();
					$json_array ['S3_files'] ['s3file_name'] = $s3file_name;
					$json_array ['S3_files'] ['s3file_basename_prefix'] = $s3file_basename_prefix;
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['bucket'] = S3BUCKET;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_id'] = $device_id;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['device_type'] = $device_type;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['is_audio'] = $is_audio;
					$json_array ['S3_files'] ['type'] ['audio'] ['format'] = $file_type [1];
				}
				$json_str = json_encode ( $json_array );
				error_log ( "json_str ---> " . $json_str );
				
				// ///////////////////////////////////////
				// check media type and update tables...
				// ///////////////////////////////////////
				// insert into media table
				$now = date ( 'Y-m-d H:i:s' );
				$tblMedia = new \Application\Entity\Media ();
				$tblMedia->media_id = $media_id;
				$tblMedia->user_id = $user_id;
				$tblMedia->is_profile_pic = $is_profile_pic;
				$tblMedia->metadata = $json_str;
				$tblMedia->create_date = $now;
				$tblMedia->update_date = $now;
				$this->dbAdapter->persist ( $tblMedia );
				$this->dbAdapter->flush ();
				// error_log ( "AddMediaEvent exec - just inserted Media " . PHP_EOL );
				
				if ($is_profile_pic) {
					// Remove previous profile images
					$remove_old_profile = "DELETE FROM Application\Entity\Media m WHERE m.is_profile_pic = 1 AND m.media_id <> '$media_id' AND m.user_id = '$user_id'";
					$remove_result = $this->dbAdapter->createQuery ( $remove_old_profile );
					$remove_result->getResult ();
					
					// if profile pic then update media
					$update_media = "UPDATE Application\Entity\Media m SET m.is_profile_pic = $is_profile_pic WHERE m.user_id ='$user_id' AND m.media_id='$media_id'";
					$statement = $this->dbAdapter->createQuery ( $update_media );
					$rs_is_profil = $statement->getResult ();
					
					// Update friend table profile image if this user is memreas network
					$user_detail = $this->dbAdapter->createQueryBuilder ()->select ( 'u' )->from ( 'Application\Entity\User', 'u' )->where ( "u.user_id=?1" )->setParameter ( 1, $user_id )->getQuery ()->getResult ();
					
					$full_path = $s3file;
					$update_friend_photo = "Update Application\Entity\Friend f SET f.url_image = '{$full_path}' WHERE f.social_username = '{$user_detail[0]->username}' AND f.network = 'memreas'";
					$this->dbAdapter->createQuery ( $update_friend_photo )->getResult ();
				}
				
				if (isset ( $event_id ) && ! empty ( $event_id )) {
					$tblEventMedia = new \Application\Entity\EventMedia ();
					$tblEventMedia->media_id = $media_id;
					$tblEventMedia->event_id = $event_id;
					$this->dbAdapter->persist ( $tblEventMedia );
					$this->dbAdapter->flush ();
					
					/**
					 * Notifications - only for images and video
					 */
					if (!is_audio) {
						$query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
						$qb = $this->dbAdapter->createQueryBuilder ();
						$qb->select ( 'f.network,f.friend_id' );
						$qb->from ( 'Application\Entity\EventFriend', 'ef' );
						$qb->join ( 'Application\Entity\Friend', 'f', 'WITH', 'ef.friend_id = f.friend_id AND ef.user_approve=1' );
						$qb->where ( 'ef.event_id = ?1 AND ef.friend_id != ?2' );
						$qb->setParameter ( 1, $event_id );
						$qb->setParameter ( 2, $user_id );
						
						$efusers = $qb->getQuery ()->getResult ();
						$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
						$eventRepo = $this->dbAdapter->getRepository ( 'Application\Entity\Event' );
						
						$eventOBj = $eventRepo->findOneBy ( array (
								'event_id' => $event_id 
						) );
						$nmessage = $userOBj->username . ' Added Media to  ' . $eventOBj->name . ' event';
						$ndata ['addNotification'] ['meta'] = $nmessage;
						
						// add event owner in notifcation list
						if ($eventOBj->user_id != $user_id) {
							$efusers [] = array (
									'network' => 'memreas',
									'friend_id' => $eventOBj->user_id 
							);
						}
						foreach ( $efusers as $ef ) {
							$friendId = $ef ['friend_id'];
							/**
							 * Build array and send notifications...
							 */
							
							$data = array ();
							$data ['addNotification'] ['sender_uid'] = $user_id;
							$data ['addNotification'] ['receiver_uid'] = $friendId;
							$data ['addNotification'] ['notification_type'] = \Application\Entity\Notification::ADD_MEDIA;
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
							
							$this->notification->add ( $friendId );
							$friendUser = $eventRepo->getUser ( $friendId, 'row' );
							Email::$item ['name'] = $friendUser ['username'];
							Email::$item ['email'] = $friendUser ['email_address'];
							Email::$item ['message'] = $ndata ['addNotification'] ['meta'];
							Email::collect ();
							
							// save in db
							$this->AddNotification->exec ( $ndata );
						}
						
						if (! empty ( $ndata ['addNotification'] ['meta'] )) {
							$this->notification->setMessage ( $ndata ['addNotification'] ['meta'] );
							$this->notification->type = \Application\Entity\Notification::ADD_MEDIA;
							$this->notification->event_id = $event_id;
							$this->notification->send ();
							Email::sendmail ( $this->service_locator );
						}
					} // end if (!is_audio)
				} //end if (isset ( $event_id ) && ! empty ( $event_id )) 
				
				if (! $is_server_image) {
					$message_data = array (
							'user_id' => $user_id,
							'media_id' => $media_id,
							'content_type' => $content_type,
							's3path' => $s3path,
							's3file_name' => $s3file_name,
							's3file_basename_prefix' => $s3file_basename_prefix,
							'is_video' => $is_video,
							'is_audio' => $is_audio,
							'email' => $email 
					);
					
					$aws_manager = new AWSManagerSender ( $this->service_locator );
					$response = $aws_manager->snsProcessMediaPublish ( $message_data );
					
					if ($response) {
						$status = 'Success';
						$message = "Media Successfully add";
					} else
						throw new \Exception ( 'Error In snsProcessMediaPublish' );
				} else {
					$status = 'Success';
					$message = "Media Successfully add";
				}
			}
		} catch ( \Exception $exc ) {
			$status = 'Failure';
			$message = $exc->getMessage ();
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<addmediaeventresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "<media_id>$media_id</media_id>";
		$xml_output .= "</addmediaeventresponse>";
		$xml_output .= "</xml>";
		ob_clean ();
		echo $xml_output;
		error_log ( "output::" . $xml_output . PHP_EOL );
	}
}

?>
