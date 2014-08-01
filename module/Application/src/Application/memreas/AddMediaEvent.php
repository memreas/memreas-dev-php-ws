<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\AddNotification;
use Application\memreas\MUUID;
use \Exception;

class AddMediaEvent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
	protected $notification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "AddMediaEvent __construct..." );
		error_log ( "AddMediaEvent __construct message_data..." . print_r ( $message_data, true ) . PHP_EOL );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
	}
	public function exec() {
		error_log ( "AddMediaEvent exec..." );
		error_log ( "AddMediaEvent _POST ----> " . print_r ( $_POST, true ) . PHP_EOL );
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
				$event_id = isset ( $data->addmediaevent->event_id ) ? addslashes ( trim ( $data->addmediaevent->event_id ) ) : '';
				$media_id = isset ( $data->addmediaevent->media_id ) ? addslashes ( trim ( $data->addmediaevent->media_id ) ) : '';
				$is_profile_pic = isset ( $data->addmediaevent->is_profile_pic ) ? addslashes ( trim ( $data->addmediaevent->is_profile_pic ) ) : 0;
				$is_server_image = addslashes ( trim ( $data->addmediaevent->is_server_image ) );
				$content_type = addslashes ( trim ( $data->addmediaevent->content_type ) );
				$s3url = addslashes ( trim ( $data->addmediaevent->s3url ) );
				$s3file_name = addslashes ( trim ( $data->addmediaevent->s3file_name ) );
				$location = json_decode ( $data->addmediaevent->location );
				$email = isset ( $data->addmediaevent->email ) ? addslashes ( trim ( $data->addmediaevent->email ) ) : '';
				error_log ( "location json ---> " . $data->addmediaevent->location . PHP_EOL );
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
				$email = isset ( $_POST ['email'] ) ? $_POST ['email'] : '';
				$s3url = isset ( $_POST ['s3url'] ) ? $_POST ['s3url'] : '';
				$location = isset ( $_POST ['location'] ) ? $_POST ['location'] : '';

			}
			$time = time ();

error_log ( "event_id ---> " . $event_id . PHP_EOL );
error_log ( "media_id ---> " . $media_id . PHP_EOL );
error_log ( "is_profile_pic ---> " . $is_profile_pic . PHP_EOL );
error_log ( "is_server_image ---> " . $is_server_image . PHP_EOL );
error_log ( "content_type ---> " . $content_type . PHP_EOL );
error_log ( "s3file_name ---> " . $s3file_name . PHP_EOL );
error_log ( "s3url ---> " . $s3url . PHP_EOL );
error_log ( "email ---> " . $email . PHP_EOL );
error_log ( "location ---> " . $location . PHP_EOL );

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
				error_log ( "AddMediaEvent exec is_server_image == 1 else " . PHP_EOL );
				$is_video = 0;
				$is_audio = 0;
				$s3path = $user_id . '/';
				$media_id = MUUID::fetchUUID ();
				// ///////////////////////////////////////
				// create metadata based on content type
				// ///////////////////////////////////////
				$file_type = explode ( '/', $content_type );
				if (strcasecmp ( $file_type [0], 'image' ) == 0) {
					$s3path = $user_id . '/image/';
					$json_array = array ();
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset($s3file_name)) ? $s3path.$s3file_name : $s3url;
					//$s3file = $s3url;
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['unique_device_identifier1'] = $user_id . '_' . $device_id;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['type'] ['image'] ['format'] = $file_type [1];
					error_log ( "json_array ---> " . json_encode ( $json_array ) );
					/*
					 * $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,), "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $device_id,),), "type" => array("image" => array("format" => $file_type[1])) ); error_log("$json_array ---> " . json_encode($json_array));
					 */
				} else if (strcasecmp ( 'video', $file_type [0] ) == 0) {
					$is_video = 1;
					$s3path = $user_id . '/media/';
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset($s3file_name)) ? $s3path.$s3file_name : $s3url;
					$json_array = array ();
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['unique_device_identifier1'] = $user_id . '_' . $device_id;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['type'] ['video'] ['format'] = $file_type [1];
					error_log ( "json_array ---> " . json_encode ( $json_array ) );
					/*
					 * $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url), "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $device_id,),), "type" => array("video" => array("format" => $file_type[1],)) ); error_log("$json_array ---> " . json_encode($json_array));
					 */
				} else if (strcasecmp ( 'audio', $file_type [0] ) == 0) {
					$is_audio = true;
					$s3path = $user_id . '/media/';
					$s3file = (isset ( $_POST ['s3file_name'] ) || isset($s3file_name)) ? $s3path.$s3file_name : $s3url;
					$json_array = array ();
					$json_array ['S3_files'] ['path'] = $s3file;
					$json_array ['S3_files'] ['full'] = $s3file;
					$json_array ['S3_files'] ['location'] = $location;
					$json_array ['S3_files'] ['local_filenames'] ['device'] ['unique_device_identifier1'] = $user_id . '_' . $device_id;
					$json_array ['S3_files'] ['file_type'] = $file_type [0];
					$json_array ['S3_files'] ['content_type'] = $content_type;
					$json_array ['S3_files'] ['type'] ['audio'] ['format'] = $file_type [1];
					error_log ( "json_array ---> " . json_encode ( $json_array ) );
					/*
					 * $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,), "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $device_id,),), "type" => array("audio" => array("format" => $file_type[1],)) ); error_log("json_array ---> " . json_encode($json_array));
					 */
				}
				$json_str = json_encode ( $json_array );

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
				error_log ( "AddMediaEvent exec - just inserted Media " . PHP_EOL );

                if ($is_profile_pic) {
                    //Remove previous profile images
                    $remove_old_profile = "DELETE FROM Application\Entity\Media m WHERE m.is_profile_pic = 1 AND m.media_id <> '$media_id' AND m.user_id = '$user_id'";
                    $remove_result = $this->dbAdapter->createQuery($remove_old_profile);
                    $remove_result->getResult();

                    // if profile pic then update media
                    $update_media = "UPDATE Application\Entity\Media m SET m.is_profile_pic = $is_profile_pic WHERE m.user_id ='$user_id' AND m.media_id='$media_id'";
                    // $rs_is_profil = mysql_query($update_media);
                    // $statement3 = $this->dbAdapter->createStatement($update_media);
                    // $rs_is_profil = $statement3->execute();
                    // $row = $result->current();
                    $statement = $this->dbAdapter->createQuery ( $update_media );
                    $rs_is_profil = $statement->getResult ();

                    //Update friend table profile image if this user is memreas network
                    $user_detail = $this->dbAdapter->createQueryBuilder ()
                                            ->select ( 'u' )
                                            ->from ( 'Application\Entity\User', 'u' )
                                            ->where ( "u.user_id=?1" )
                                            ->setParameter(1, $user_id)
                                            ->getQuery ()->getResult ();

                    $full_path = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $s3file;
                    $update_friend_photo = "Update Application\Entity\Friend f SET f.url_image = '{$full_path}' WHERE f.social_username = '{$user_detail[0]->username}' AND f.network = 'memreas'";
                    $this->dbAdapter->createQuery($update_friend_photo)->getResult();

                    /*if (! $rs_is_profil)
                        throw new Exception ( 'Error : ' . mysql_error () );*/

                    error_log ( "AddMediaEvent exec - just udpated Media " . PHP_EOL );
                }

				// $event_id = isset($_POST['event_id']) ? trim($_POST['event_id']) : null;
				if (isset ( $event_id ) && ! empty ( $event_id )) {
					$tblEventMedia = new \Application\Entity\EventMedia ();
					$tblEventMedia->media_id = $media_id;
					$tblEventMedia->event_id = $event_id;
					$this->dbAdapter->persist ( $tblEventMedia );
					$this->dbAdapter->flush ();
					error_log ( "AddMediaEvent exec - just inserted EventMedia " . PHP_EOL );
					/*
					 * @todo send to all particiepent
					 */
					$query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
					$qb = $this->dbAdapter->createQueryBuilder ();
					$qb->select ( 'f.network,f.friend_id' );
					$qb->from ( 'Application\Entity\EventFriend', 'ef' );
					$qb->join ( 'Application\Entity\Friend', 'f', 'WITH', 'ef.friend_id = f.friend_id' );
					$qb->where ( 'ef.event_id = ?1' );
					$qb->setParameter ( 1, $event_id );

					$efusers = $qb->getQuery ()->getResult ();
					$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
					$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $event_id );
					$nmessage = '<b>'.$userOBj->username . '</b> Added Media to  <u><b>' . $eventOBj->name . '</b></u> event';
					$ndata ['addNotification'] ['meta'] = $nmessage;
					foreach ( $efusers as $ef ) {
						$friendId = $ef ['friend_id'];
						$ndata = array (
									'addNotification' => array (
											'network_name' => $ef ['network'],
											'user_id' => $friendId,
											'meta' => $nmessage,
											'notification_type' => \Application\Entity\Notification::ADD_MEDIA,
											'links' => json_encode ( array (
													'event_id' => $event_id,
													'from_id' => $user_id,
													'friend_id' => $friendId
											) )
									)
							);
						if ($ef ['network'] == 'memreas') {
							$this->notification->add ( $friendId );
						} else {
							$this->notification->addFriend ( $ef ['friend_id'] );
						}
						$this->AddNotification->exec ( $ndata );
					}

					if (! empty ( $ndata ['addNotification'] ['meta'] )) {
						$this->notification->setMessage ( $ndata ['addNotification'] ['meta'] );
						$this->notification->type = \Application\Entity\Notification::ADD_MEDIA;
						$this->notification->event_id = $event_id;

						$this->notification->send ();
					}
				}

				// if (!$is_audio) {
				if (! $is_server_image) {
					$message_data = array (
							'user_id' => $user_id,
							'media_id' => $media_id,
							'content_type' => $content_type,
							's3path' => $s3path,
							's3file_name' => $s3file_name,
							'is_video' => $is_video,
							'is_audio' => $is_audio,
							'email' => $email
					);

					$aws_manager = new AWSManagerSender ( $this->service_locator );
					$response = $aws_manager->snsProcessMediaPublish ( $message_data );

					if ($response == 1) {
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
		error_log ( $xml_output, 0 );
		error_log ( "EXIT addmediaevent.php..." );
	}
}

?>
