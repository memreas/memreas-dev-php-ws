<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use \Exception;
use Application\memreas\AddNotification;
use Application\memreas\AWSManagerSender;
use Application\memreas\Email;
use Application\Model\MemreasConstants;
use Zend\View\Model\ViewModel;

class AddMediaEvent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
	protected $url_signer;
	protected $notification;
	protected $aws_manager;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->aws_manager = new AWSManagerSender ( $service_locator );
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
		$this->url_signer = new MemreasSignedURL ();
	}
	public function exec($redis = null) {
		$cm = __CLASS__ . __METHOD__;
		$is_audio = false;
		try {
			$media_id = '';
			Mlog::addone ( $cm .__LINE__.'$_POST [xml]', $_POST ['xml'] );
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
				$s3file_basename_prefix = pathinfo ( basename ( $s3file_name ) ) ['filename'];
				$location = ( string ) $data->addmediaevent->location;
				$copyright = isset ( $data->addmediaevent->copyright ) ? ( string ) $data->addmediaevent->copyright : '';
				// $applyCopyrightOnServer = isset ( $data->addmediaevent->applyCopyrightOnServer ) ? addslashes ( trim ( $data->addmediaevent->applyCopyrightOnServer ) ) : 0;
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
				$s3file_basename_prefix = pathinfo ( basename ( $s3file_name ) ) ['filename'];
				$s3url = isset ( $_POST ['s3url'] ) ? $_POST ['s3url'] : '';
				$location = isset ( $_POST ['location'] ) ? $_POST ['location'] : '';
				$copyright = isset ( $_POST ['copyright'] ) ? $_POST ['copyright'] : '';
				// $applyCopyrightOnServer = isset ( $_POST ['applyCopyrightOnServer'] ) ? $_POST ['applyCopyrightOnServer'] : 0;
			}
			$time = time ();
			
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$event_id', $event_id );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$media_id', $media_id );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$$is_profile_pic', $is_profile_pic );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$is_server_image', $is_server_image );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$content_type', $content_type );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$s3url', $s3url );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$s3file_name', $s3file_name );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$s3file_basename_prefix', $s3file_basename_prefix );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$location', $location );
			//Mlog::addone ( __CLASS__ . __METHOD__ . '$copyright', $copyright );
			
			// ////////////////////////////////////////////////////////////////////
			// dont upload file if server image just insert into event_media
			// table
			// ////////////////////////////////////////////////////////////////////
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::AddMediaEvent exec is_server_image::', $is_server_image );
			if ($is_server_image == 1) {
				if (! isset ( $media_id ) || empty ( $media_id )) {
					throw new \Exception ( 'Error : media_id is empty' );
				}
				if (! isset ( $event_id ) || empty ( $event_id )) {
					throw new \Exception ( 'Error : event_id is empty' );
				}
				$tblEventMedia = new \Application\Entity\EventMedia ();
				$tblEventMedia->media_id = $media_id;
				$tblEventMedia->event_id = $event_id;
				$this->dbAdapter->persist ( $tblEventMedia );
				$this->dbAdapter->flush ();
				$status = 'Success';
				$message = "Media Successfully add";
			} else {
				//
				// New Media - insert media
				//
				// Mlog::addone ( 'insert::', 'new media' );
				// Mlog::addone ( '$copyright', $copyright );
				
				//
				// media_id must be set
				//
				$s3path = $user_id . '/' . $media_id . '/';
				$is_video = 0;
				$is_audio = 0;
				
				// ///////////////////////////////////////////////
				// insert into user and media for profile pic
				// ///////////////////////////////////////////////
				if ($is_profile_pic) {
					//
					// refresh session profile pic and store the session
					//
					$_SESSION ['profile_pic'] = $this->url_signer->signArrayOfUrls ( $s3path . $s3file_name );
					
					//
					// Update user to set profile pic flag
					//
					$q = $this->dbAdapter->createQueryBuilder ()->update ( 'Application\Entity\User', 'u' )->set ( 'u.profile_photo', 1 )->where ( 'u.user_id = ?1' )->setParameter ( 1, $user_id )->getQuery ();
					$p = $q->execute ();
					
					//
					// Update media to set all rows to 0 for this user's profile pic
					//
					$q = $this->dbAdapter->createQueryBuilder ()->update ( 'Application\Entity\Media', 'm' )->set ( 'm.is_profile_pic', 0 )->where ( 'm.user_id = ?1' )->setParameter ( 1, $user_id )->getQuery ();
					$p = $q->execute ();
				}
				
				// ///////////////////////////////////////
				// create metadata based on content type
				// ///////////////////////////////////////
				$s3file = $s3path . $s3file_name;
				$s3bucket = MemreasConstants::S3BUCKET;
				$file_type = explode ( '/', $content_type );
				$json_array = array ();
				// Mlog::addone ( '$file_type', $file_type );
				// Mlog::addone ( '$file_type[0]', $file_type [0] );
				// Mlog::addone ( '$file_type[1]', $file_type [1] );
				if (strtolower ( $file_type [0] ) == "video") {
					$is_video = 1;
					$json_array ['S3_files'] ['is_video'] = 1;
				} else if (strtolower ( $file_type [0] ) == "audio") {
					$is_audio = 1;
					$json_array ['S3_files'] ['is_audio'] = 1;
				}
				$json_array ['S3_files'] ['s3file_name'] = $s3file_name;
				$json_array ['S3_files'] ['s3file_basename_prefix'] = $s3file_basename_prefix;
				$json_array ['S3_files'] ['copyright'] = json_decode ( $copyright );
				$json_array ['S3_files'] ['bucket'] = $s3bucket;
				$json_array ['S3_files'] ['path'] = $s3file;
				$json_array ['S3_files'] ['full'] = $s3file;
				$json_array ['S3_files'] ['location'] = json_decode ( ( string ) $location );
				$json_array ['S3_files'] ['devices'] ['device'] ['device_id'] = $device_id;
				$json_array ['S3_files'] ['devices'] ['device'] ['device_type'] = $device_type;
				$json_array ['S3_files'] ['devices'] ['device'] ['device_local_identifier'] = $s3file_name; // initial upload
				$json_array ['S3_files'] ['devices'] ['device'] ['origin'] = 1;
				$json_array ['S3_files'] ['file_type'] = $file_type [0];
				$json_array ['S3_files'] ['content_type'] = $content_type;
				$json_array ['S3_files'] ['type'] [strtolower ( $file_type [0] )] ['format'] = $file_type [1];
				$json_str = json_encode ( $json_array );
				
				/**
				 * -
				 * Check if object exists in S3 otherwise throw exception...
				 */
				$s3file = (isset ( $_POST ['s3file_name'] ) || isset ( $s3file_name )) ? $s3path . $s3file_name : $s3url;

				/* - Code to test failed upload
    				Mlog::addone($cm . __LINE__ , 'Checking $this->aws_manager->s3->doesObjectExist( MemreasConstants::S3BUCKET, $key )' );
    				$result = $this->aws_manager->s3->doesObjectExist( MemreasConstants::S3BUCKET, $s3file );
    				
    				if ($result) {
    					Mlog::addone($cm . __LINE__ . '$result --->', $result );
    				}
    				
    				// Test if exception with delete
    				Mlog::addone($cm . __LINE__ , '$this->aws_manager->s3->deleteObject...' );
    				$this->aws_manager->s3->deleteObject ( array (
    						'Bucket' => MemreasConstants::S3BUCKET,
    						'Key' => $s3file
    				) );

    				Mlog::addone($cm . __LINE__ , 'ReChecking $this->aws_manager->s3->doesObjectExist( MemreasConstants::S3BUCKET, $key )' );
    				*/
				
    				$result = $this->aws_manager->s3->doesObjectExist( MemreasConstants::S3BUCKET, $s3file );
				if (!$result) {
    					//Mlog::addone($cm . __LINE__ , 'throw new Exception ( media failed upload );' );
					throw new Exception ( 'current media failed upload' );
				}
				
				//
				// if copyright add id to media table
				//
				$copyright_id = '0';
				if (! empty ( $copyright )) {
					$copyright_array = json_decode ( $copyright, true );
					$copyright_id = $copyright_array ['copyright_id'];
				}
				
				// ///////////////////////////////////////
				// check media type and insert tables...
				// ///////////////////////////////////////
				$now = date ( 'Y-m-d H:i:s' );
				$tblMedia = new \Application\Entity\Media ();
				$tblMedia->media_id = $media_id;
				$tblMedia->user_id = $user_id;
				$tblMedia->copyright_id = $copyright_id;
				$tblMedia->is_profile_pic = $is_profile_pic;
				$tblMedia->metadata = $json_str;
				$tblMedia->create_date = $now;
				$tblMedia->update_date = $now;
				$this->dbAdapter->persist ( $tblMedia );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'addmediaevent media insert metadata--->', $json_str );
				
				/**
				 * Update copyright batch data and copyright table.
				 */
				$MediaDeviceTracker = new MediaDeviceTracker ( $this->message_data, $this->memreas_tables, $this->service_locator );
				$data = array ();
				$data ['media_id'] = $media_id;
				$data ['user_id'] = $user_id;
				$data ['device_type'] = $device_type;
				$data ['device_id'] = $device_id;
				$data ['device_local_identifier'] = $s3file_basename_prefix;
				$data ['task_identifier'] = (! empty ( $task_identifier )) ? $task_identifier : 0;
				$result = $MediaDeviceTracker->exec ( $data );
				
				/**
				 * Update copyright batch data and copyright table.
				 */
				if (! empty ( $copyright )) {
					Mlog::addone ( '!empty($copyright)', '*' . $copyright . '*' );
					// check if copyright is available
					// and fetch from copyright_batch table to update
					// and insert copyright table
					
					// $copyright_array = json_decode ( $copyright, true ); //decoded above for media table
					$copyright_batch_id = $copyright_array ['copyright_batch_id'];
					$copyright_batch = $this->dbAdapter->find ( 'Application\Entity\CopyrightBatch', $copyright_batch_id );
					if ($copyright_batch) {
						//
						// update used and add md5 and sha file checksums
						//
						$copyright_batch_json = $copyright_batch->__get ( 'metadata' );
						$copyright_batch_array = json_decode ( $copyright_batch_json, true );
						for($i = 0; $i < count ( $copyright_batch_array ); $i ++) {
							if ($copyright_array ['copyright_id'] == $copyright_batch_array [$i] ['copyright_id']) {
								//
								// update the entry
								//
								$copyright_batch_array [$i] ['used'] = 1;
								if (isset ( $copyright_batch_array [$i] ['fileCheckSumMD5'] )) {
									$copyright_batch_array [$i] ['fileCheckSumMD5'] = $copyright_array ['fileCheckSumMD5'];
								}
								if (isset ( $copyright_batch_array [$i] ['fileCheckSumMD5'] )) {
									$copyright_batch_array [$i] ['fileCheckSumSHA1'] = $copyright_array ['fileCheckSumSHA1'];
								}
								if (isset ( $copyright_batch_array [$i] ['fileCheckSumMD5'] )) {
									$copyright_batch_array [$i] ['fileCheckSumSHA256'] = $copyright_array ['fileCheckSumSHA256'];
								}
							}
						}
						$remaining = $copyright_batch->__get ( 'remaining' );
						$remaining -= 1;
						
						//
						// Update copyright_batch table
						//
						Mlog::addone ( ' json_encode($copyright_batch_array) :: ', json_encode ( $copyright_batch_array ) );
						$copyright_batch->__set ( 'metadata', json_encode ( $copyright_batch_array ) );
						$copyright_batch->__set ( 'remaining', $remaining );
						$this->dbAdapter->persist ( $copyright_batch );
						
						//
						// Insert copyright table
						//
						$now = date ( 'Y-m-d H:i:s' );
						$tblCopyright = new \Application\Entity\Copyright ();
						$tblCopyright->copyright_id = $copyright_array ['copyright_id'];
						$tblCopyright->copyright_batch_id = $copyright_array ['copyright_batch_id'];
						$tblCopyright->user_id = $user_id;
						$tblCopyright->media_id = $copyright_array ['media_id'];
						$tblCopyright->validated = true;
						$tblCopyright->metadata = $copyright;
						$tblCopyright->create_date = $now;
						$tblCopyright->update_time = $now;
						$this->dbAdapter->persist ( $tblCopyright );
					}
				}
				//
				// Now flush to db to update media, copyright_batch, and
				// copyright
				//
				$this->dbAdapter->flush ();
				Mlog::addone ( 'flushed to db to update media, copyright_batch, and copyright', '' );
				
				/*
				 * Send copyright email
				 */
				if (! empty ( $copyright )) {
					Mlog::addone ( $cm . __LINE__ . '::', $_SESSION );
					$email = $_SESSION ['email_address'];
					$username = $_SESSION ['username'];
					$to [] = $email;
					$viewVar = array (
							'email' => $email,
							'receiver_name' => $username,
							'copyright_array' => $copyright_array,
							'device_type' => $device_type 
					);
					$viewModel = new ViewModel ( $viewVar );
					$viewModel->setTemplate ( 'email/copyright_received' );
					$viewRender = $this->service_locator->get ( 'ViewRenderer' );
					$html = $viewRender->render ( $viewModel );
					$subject = 'memreas media copyright receipt';
					if (empty ( $aws_manager ))
						$aws_manager = new AWSManagerSender ( $this->service_locator );
					if (MemreasConstants::SEND_EMAIL) {
						$aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
					}
				}
				
				Mlog::addone ( '$is_profile_pic', $is_profile_pic );
				if ($is_profile_pic === 1) {
					// update profile image so we don't have media in S3 without database entry
					$update_old_profile = "UPDATE Application\Entity\Media m SET m.is_profile_pic = 0 WHERE m.is_profile_pic = 1 AND m.media_id <> '$media_id' AND m.user_id = '$user_id'";
					$remove_result = $this->dbAdapter->createQuery ( $remove_old_profile );
					$remove_result->getResult ();
					
					// if profile pic then update media
					$update_media = "UPDATE Application\Entity\Media m SET m.is_profile_pic = $is_profile_pic WHERE m.user_id ='$user_id' AND m.media_id='$media_id'";
					$statement = $this->dbAdapter->createQuery ( $update_media );
					$rs_is_profile = $statement->getResult ();
					
					// Update friend table profile image if this user is memreas
					// network
					$user_detail = $this->dbAdapter->createQueryBuilder ()->select ( 'u' )->from ( 'Application\Entity\User', 'u' )->where ( "u.user_id=?1" )->setParameter ( 1, $user_id )->getQuery ()->getResult ();
					
					$full_path = $s3file;
					$update_friend_photo = "Update Application\Entity\Friend f SET f.url_image = '{$full_path}' WHERE f.social_username = '{$user_detail[0]->username}' AND f.network = 'memreas'";
					$this->dbAdapter->createQuery ( $update_friend_photo )->getResult ();
				}
				
				//
				// Event media section...
				//
				if (isset ( $event_id ) && ! empty ( $event_id )) {
					$tblEventMedia = new \Application\Entity\EventMedia ();
					$tblEventMedia->media_id = $media_id;
					$tblEventMedia->event_id = $event_id;
					$this->dbAdapter->persist ( $tblEventMedia );
					$this->dbAdapter->flush ();
					
					/**
					 * Notifications - only for images and video
					 */
					if (! $is_audio) {
						$query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
						$qb = $this->dbAdapter->createQueryBuilder ();
						$qb->select ( 'f.network,f.friend_id' );
						$qb->from ( 'Application\Entity\EventFriend', 'ef' );
						$qb->join ( 'Application\Entity\Friend', 'f', 'WITH', 'ef.friend_id = f.friend_id AND ef.user_approve=1' );
						$qb->where ( 'ef.event_id = ?1 AND ef.friend_id != ?2' );
						$qb->setParameter ( 1, $event_id );
						$qb->setParameter ( 2, $user_id );
						
						Mlog::addone($cm.__LINE__.'::friend_query::', $qb->getQuery()->getSql());
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
							if (!empty($friendId)) {
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
								Mlog::add ( __CLASS__ . __METHOD__ . __LINE__.'::$data.addNotification...' );
								Mlog::add ( $data, 'j', 1 );
									
								// add notification in db.
								$result = $this->AddNotification->exec ( $data );
									
								$this->notification->add ( $friendId );
								$friendUser = $eventRepo->getUser ( $friendId, 'row' );
								Email::$item ['name'] = $friendUser ['username'];
								Email::$item ['email'] = $friendUser ['email_address'];
								Email::$item ['message'] = $ndata ['addNotification'] ['meta'];
								Email::collect ();
									
								Mlog::add ( __CLASS__ . __METHOD__ . __LINE__.'::$ndata.addNotification...' );
								Mlog::add ( $ndata, 'j', 1 );
									
								// save in db
								$this->AddNotification->exec ( $ndata );
							}
						}
						
						if (! empty ( $ndata ['addNotification'] ['meta'] )) {
							$this->notification->setMessage ( $ndata ['addNotification'] ['meta'] );
							$this->notification->type = \Application\Entity\Notification::ADD_MEDIA;
							$this->notification->event_id = $event_id;
							$this->notification->send ();
							Email::sendmail ( $this->service_locator );
						}
					} // end if (!is_audio)
				} // end if (isset ( $event_id ) && ! empty ( $event_id ))
				
				if (empty ( $is_server_image )) {
					$message_data = array ();
					$message_data ['user_id'] = $user_id;
					$message_data ['media_id'] = $media_id;
					$message_data ['content_type'] = $content_type;
					$message_data ['s3path'] = $s3path;
					$message_data ['s3file_name'] = $s3file_name;
					$message_data ['s3file_basename_prefix'] = $s3file_basename_prefix;
					$message_data ['is_video'] = $is_video;
					$message_data ['is_audio'] = $is_audio;
					if (! empty ( $copyright )) {
						$message_data ['copyright'] = $copyright;
					}
					Mlog::addone ( 's3path', $s3path );
					
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
		// header ( "Content-type: text/xml" );
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
