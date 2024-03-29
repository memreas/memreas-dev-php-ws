<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use \Exception;
use Application\memreas\AddFriendtoevent;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Application\memreas\RmWorkDir;
use Application\memreas\StripeWS\PaymentsProxy;
use Application\Model\MemreasConstants;
use Zend\View\Model\ViewModel;
use Application\Model\MemreasStringsWS;

class Registration {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $addfriendtoevent;
	public $status;
	public $userIndex = array ();
	public $username;
	public $user_id;
	public $profile_photo;
	protected $registerDevice;
	protected $sessHandler;
	public function __construct($sessHandler, $message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->sessHandler = $sessHandler;
		$this->addfriendtoevent = new AddFriendtoevent ( $message_data, $memreas_tables, $service_locator );
		$this->registerDevice = new RegisterDevice ( $message_data, $memreas_tables, $service_locator );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function is_valid_email($email) {
		$result = TRUE;
		if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
			error_log ( "is_valid_email failed for $email" . PHP_EOL );
			$result = FALSE;
		}
		return $result;
	}
	public function exec($clientIPAddress = '') {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::exit" );
		
		$invited_by = '';
		if (isset ( $_POST ['xml'] )) {
			error_log ( "Inside Registration xml request ----> " . $_POST ['xml'] . PHP_EOL );
			$data = simplexml_load_string ( $_POST ['xml'] );
			$username = trim ( $data->registration->username );
			$email = trim ( $data->registration->email );
			$email = strtolower ( $email );
			$password = trim ( $data->registration->password );
			$this->profile_photo = 0;
			if (isset ( $data->registration->profile_photo )) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::profile_photo value before -->", $this->profile_photo );
				if (($data->registration->profile_photo == "true") || ($data->registration->profile_photo == 1)) {
					$this->profile_photo = 1;
				}
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::profile_photo value after -->", $this->profile_photo );
			}
			$this->profile_photo = isset ( $data->registration->profile_photo ) ? $data->registration->profile_photo : 0;
			$device_id = trim ( $data->registration->device_id );
			$device_type = trim ( $data->registration->device_type );
			$secret = trim ( $data->registration->secret );
			$invited_by = trim ( $data->registration->invited_by );
			$mobile = trim ( $data->registration->mobile );
			// $invited_by = $this->is_valid_email ( $invited_by ) ? $invited_by : '';
			$assign_event = trim ( $data->registration->event_id );
		} else {
			$username = trim ( $_REQUEST ['username'] );
			$email = trim ( $_REQUEST ['email'] );
			$email = strtolower ( $email );
			$password = trim ( $_REQUEST ['password'] );
			$device_id = trim ( $_REQUEST ['device_id'] );
			$device_type = trim ( $_REQUEST ['device_type'] );
			$secret = trim ( $_REQUEST ['secret'] );
			if (isset ( $_REQUEST ['invited_by'] ) && (! empty ( $_REQUEST ['invited_by'] ))) {
				$invited_by = $_REQUEST ['invited_by'];
			} else {
				$invited_by = null;
			}
			$mobile = trim ( $_REQUEST ['mobile'] );
		}
		
		error_log ( "username--->" . $username . PHP_EOL );
		error_log ( "email--->" . $email . PHP_EOL );
		error_log ( "password--->" . $password . PHP_EOL );
		error_log ( "device_id--->" . $device_id . PHP_EOL );
		error_log ( "device_type--->" . $device_type . PHP_EOL );
		error_log ( "secret--->" . $secret . PHP_EOL );
		error_log ( "invited_by--->" . $invited_by . PHP_EOL );
		error_log ( "event_id--->" . $event_id . PHP_EOL );
		
		// $this->processInvitedBy($invited_by);exit;
		try {
			if (isset ( $email ) && ! empty ( $email ) && isset ( $username ) && ! empty ( $username ) && isset ( $password ) && ! empty ( $password )) {
				$checkvalidemail = $this->is_valid_email ( $email );
				
				if (! $checkvalidemail) {
					// throw new \Exception ( 'Your profile is not created successfully. Please enter valid email address.' );
					$status = 'failure';
					$message = 'email address is invalid';
				} else {
					
					/*
					 * FOR TESTING ONLY - CHANGE FLAG IN PROD
					 * email check working...
					 */
					
					if (MemreasConstants::ALLOW_DUPLICATE_EMAIL_FOR_TESTING == 1) {
						$sql = "SELECT u FROM Application\Entity\User u where u.username = '" . $username . "'"; // " or u.email_address = '".$email_address."'";
					} else {
						$sql = "SELECT u FROM Application\Entity\User u where u.username = '" . $username . "' OR u.email_address = '" . $email . "'";
					}
					$statement = $this->dbAdapter->createQuery ( $sql );
					$result = $statement->getResult ();
					
					if (! empty ( $result )) {
						$result = $result [0];
						$status = 'Failure';
						if (($result->email_address == $email) && ($result->username != $username)) {
							$message = MemreasStringsWS::EMAIL_ALREADY_EXISTS;
						} else if (($result->username == $username) && ($result->email_address != $email)) {
							$message = MemreasStringsWS::USERNAME_ALREADY_EXISTS;
						} else if (($result->username == $username) && ($result->email_address == $email)) {
							$message = MemreasStringsWS::USERNAME_EMAIL_ALREADY_EXISTS;
						}
					} else {
						/*
						 * added entry for email address verification
						 */
						$user_id = MUUID::fetchUUID ();
						$email_verification_id = MUUID::fetchUUID ();
						error_log ( "user_id--->" . $user_id . PHP_EOL );
						error_log ( "email_verification_id--->" . $email_verification_id . PHP_EOL );
						
						/*
						 * Set user metadata
						 */
						$meta_arr = Array ();
						$meta_arr ['user'] ['username'] = $username;
						$meta_arr ['user'] ['user_id'] = $user_id;
						$meta_arr ['user'] ['email_verification_id'] = $email_verification_id;
						$email_verification_url = MemreasConstants::ORIGINAL_URL . 'index?action=verifyemailaddress&email_verification_id=' . $email_verification_id . '&user_id=' . $user_id;
						$meta_arr ['user'] ['email_verification_url'] = $email_verification_url;
						$meta_arr ['user'] ['email_verified'] = "0";
						$metadata = json_encode ( $meta_arr );
						
						/*
						 * MD5 Encrypting password
						 */
						$passwrd = $password;
						$password = md5 ( $password );
						$roleid = 2;
						$created = strtotime ( date ( 'Y-m-d H:i:s' ) );
						$modified = strtotime ( date ( 'Y-m-d H:i:s' ) );
						
						//
						// Create User
						//
						$tblUser = new \Application\Entity\User ();
						$tblUser->user_id = $user_id;
						$tblUser->username = $username;
						$tblUser->password = $password;
						$tblUser->email_address = $email;
						$tblUser->role = $roleid;
						$tblUser->metadata = $metadata;
						$tblUser->create_date = $created;
						$tblUser->update_time = $modified;
						$tblUser->invited_by = $invited_by;
						
						$this->dbAdapter->persist ( $tblUser );
						$this->dbAdapter->flush ();
						
						/*
						 * 5-APR-2015 - needs to be corrected for Redis...
						 */
						// create cache
						// $this->createUserCache();
						
						// invite by code
						// $q_notification = "SELECT n FROM Application\Entity\Notification n where n.short_code=:short_code AND n.notification_type = :notification_type";
						// $statement = $this->dbAdapter->createQuery ( $q_notification );
						// $statement->setParameter ( 'short_code', $invited_by );
						// $statement->setParameter ( 'notification_type', 2 );
						// $row_notification = $statement->getOneOrNullResult ();
						// if (! empty ( $row_notification ))
						// $ndata = json_decode ( $row_notification->links );
						// else
						// $ndata = null;
						// if (! empty ( $ndata->from_id ) && ! empty ( $ndata->event_id )) {
						
						// $xml_input = '<xml><addfriendtoevent>
						// <user_id>' . $ndata->from_id . '</user_id>
						// <event_id>' . $ndata->event_id . '</event_id>
						// <friends>
						// <friend>
						// <network_name>memreas</network_name>
						// <friend_name>' . $username . '</friend_name>
						// <profile_pic_url><![CDATA[' . $url . ']]>' . '</profile_pic_url>
						// </friend>
						// </friends>
						// </addfriendtoevent></xml>';
						
						// // add frient to event
						// $this->addfriendtoevent->exec ( $xml_input );
						// }
						
						// // Check if user has been assigned an event
						// if ($assign_event) {
						// $query = $this->dbAdapter->createQueryBuilder ();
						// $query->select ( 'e' )->from ( '\Application\Entity\Event', 'e' )->where ( 'e.event_id = ?1' )->setParameter ( 1, $assign_event );
						// $result = $query->getQuery ()->getResult ();
						
						// $memreas_tables = new MemreasTables ( $this->service_locator );
						// $message_data = '<xml><addfriendtoevent>
						// <user_id>' . $result [0]->user_id . '</user_id>
						// <event_id>' . $assign_event . '</event_id>
						// <emails><email></email></emails>
						// <friends>
						// <friend>
						// <network_name>memreas</network_name>
						// <friend_name>' . $username . '</friend_name>
						// <friend_id>' . $user_id . '</friend_id>
						// <profile_pic_url></profile_pic_url>
						// </friend>
						// </friends></addfriendtoevent></xml>';
						
						// $MemreasEvent = new AddFriendtoevent ( $message_data, $memreas_tables, $this->service_locator );
						// $MemreasEvent->exec ( $message_data );
						// }
						
						//
						// upload profile image
						//
						if (isset ( $_FILES ['f'] ) && ! empty ( $_FILES ['f'] ['name'] )) {
							$s3file_name = time () . $_FILES ['f'] ['name'];
							$content_type = $_FILES ['f'] ['type'];
							
							// dirPath = /data/temp_uuid/media/userimage/
							$temp_job_uuid_dir = MUUID::fetchUUID ();
							$dirPath = getcwd () . MemreasConstants::DATA_PATH . $temp_job_uuid_dir . MemreasConstants::IMAGES_PATH;
							if (! file_exists ( $dirPath )) {
								mkdir ( $dirPath, 0755, true );
							}
							$file = $dirPath . $s3file_name;
							if (strpos ( $_FILES ['f'] ['type'], 'image' ) < 0)
								throw new \Exception ( 'Your profile is not created successfully. Please Upload Image.' );
							
							$move = move_uploaded_file ( $_FILES ['f'] ['tmp_name'], $file );
							if (! $move)
								throw new \Exception ( 'Please Upload Image.' );
								
								// Upload to S3 here
							$media_id = MUUID::fetchUUID ();
							$aws_manager = new AWSManagerSender ( $this->service_locator );
							$s3_data = $aws_manager->webserviceUpload ( $user_id, $media_id, $dirPath, $s3file_name, $content_type );
							
							$file_type = explode ( '/', $content_type );
							$json_array = array (
									"S3_files" => array (
											"path" => $s3_data ['s3path'] . $s3_data ['s3file_name'],
											"Full" => $s3_data ['s3path'] . $s3_data ['s3file_name'] 
									),
									"local_filenames" => array (
											"device" => array (
													"device_id" => $device_id,
													"device_type" => $device_type 
											) 
									),
									"type" => array (
											"image" => array (
													"format" => $file_type [1] 
											) 
									) 
							);
							$json_str = json_encode ( $json_array );
							
							$time = time ();
							$tblMedia = new \Application\Entity\Media ();
							$tblMedia->media_id = $media_id;
							$tblMedia->user_id = $user_id;
							$tblMedia->is_profile_pic = '1';
							$tblMedia->metadata = $json_str;
							$tblMedia->create_date = $time;
							$tblMedia->update_date = $time;
							$this->dbAdapter->persist ( $tblMedia );
							$this->dbAdapter->flush ();
							
							$q_update = "UPDATE Application\Entity\User u  SET u.profile_photo = '1' WHERE u.user_id ='$user_id'";
							$statement = $this->dbAdapter->createQuery ( $q_update );
							$r = $statement->getResult ();
							
							// Now publish the message so any photo is thumbnailed.
							$message_data = array (
									'user_id' => $user_id,
									'media_id' => $media_id,
									'content_type' => $content_type,
									's3path' => $s3_data ['s3path'],
									's3file_name' => $s3_data ['s3file_name'],
									'isVideo' => 0,
									'email' => $email 
							);
							
							$aws_manager = new AWSManagerSender ( $this->service_locator );
							$response = $aws_manager->snsProcessMediaPublish ( $message_data );
							
							// Remove the work directory
							$dir = getcwd () . MemreasConstants::DATA_PATH . $temp_job_uuid_dir;
							$dirRemoved = new RmWorkDir ( $dir );
							
							$status = 'Success';
							$message = "Media Successfully add";
						}
						
						/**
						 * TODO:
						 * 	Check if the device is registered and update as needed
						 *  	- need device token...
						 */
						//$this->registerDevice->checkDevice ( $user_id, $device_id, $device_type );
						
						//
						// Start session so we can create plan
						//
						$data = [];
						$data['user_id'] = $user_id;
						$data['username'] = $username;
						$data = (object) $data;
						$this->sessHandler->startSessionWithUID ( $data );
						
						/*
						 * setup new user with free plan
						 */
						$PaymentsProxy = new PaymentsProxy ( $this->service_locator );
						$message_data ['sid'] = $_SESSION ['sid'];
						$message_data ['user_id'] = $user_id;
						$message_data ['username'] = $username;
						$message_data ['email'] = $email;
						$message_data ['description'] = "new registered user associated with email: " . $email;
						$message_data ['metadata'] = array (
								'user_id' => $user_id 
						);
						$result = $PaymentsProxy->exec ( "stripe_createCustomer", $message_data );
						
						/*
						 * Send user email
						 */
						$to [] = $email;
						$viewVar = array (
								'email' => $email,
								'receiver_name' => $username,
								'email_verification_url' => $meta_arr ['user'] ['email_verification_url'] 
						);
						$viewModel = new ViewModel ( $viewVar );
						$viewModel->setTemplate ( 'email/register' );
						$viewRender = $this->service_locator->get ( 'ViewRenderer' );
						$html = $viewRender->render ( $viewModel );
						$subject = 'Welcome to memreas';
						if (empty ( $aws_manager ))
							$aws_manager = new AWSManagerSender ( $this->service_locator );
						if (MemreasConstants::SEND_EMAIL) {
							$aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
						}
						$this->status = $status = 'Success';
						$message = "Welcome to memreas. Your profile has been created.  Please verify your email next";
						
						//$this->sessHandler->startSessionWithUID ( $user_id, $username );
						error_log ( "Finished..." . PHP_EOL );
					}
				}
			} else {
				throw new \Exception ( 'Your profile is not created successfully. Please check all data you have inserted are proper.' );
			}
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registrationresponse>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= "<userid>" . $user_id . "</userid>";
			$xml_output .= "<email_verification_url><![CDATA[" . $meta_arr ['user'] ['email_verification_url'] . "]]></email_verification_url>";
			if ($mobile) {
				$xml_output .= "<mobile>1</mobile>";
			}
			$xml_output .= "<sid>" . session_id () . "</sid>";
			$xml_output .= "</registrationresponse>";
			$xml_output .= "</xml>";
			Mlog::addone ( '$xml_output', $xml_output );
		} catch ( \Exception $exc ) {
			$status = 'failure';
			$message = $exc->getMessage ();
			error_log ( "error message ----> $message" . PHP_EOL );
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registrationresponse>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= "</registrationresponse>";
			$xml_output .= "</xml>";
		}
		
		echo $xml_output;
		
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::exit" );
		
		error_log ( "registration xml_output -------> *****" . $xml_output . "*****" . PHP_EOL );
		
		$this->username = $username;
		$this->user_id = $user_id;
		$filename = $s3_data ['s3path'] . $s3_data ['s3file_name'];
		$this->profile_photo = ! empty ( $filename ) ? $s3_data ['s3path'] . $s3_data ['s3file_name'] : '';
		
		$result = array (
				'user_id' => $user_id,
				'username' => $username
				//'profile_photo' => $s3_data ['s3path'] . $s3_data ['s3file_name'] 
		);
		
		return $result;
	} // end exec()
	function createUserCache() {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::enter" );
		
		$qb = $this->dbAdapter->createQueryBuilder ();
		$qb->select ( 'u.user_id', 'u.username', 'm.metadata' );
		$qb->from ( 'Application\Entity\User', 'u' );
		$qb->where ( 'u.disable_account = 0' );
		$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
		// error_log("qb --->".$qb.PHP_EOL);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::SQL Query --->" . $qb->getQuery ()->getSql () );
		
		// create index for cache
		$userIndexArr = $qb->getQuery ()->getResult ();
		// $userIndexArr = $this->dbAdapter->createQuery ( 'SELECT u.user_id,u.username FROM Application\Entity\User u Where u.disable_account=0 ORDER BY u.username' );
		// AND u.username LIKE :username $userIndexSql->setParameter ( 'username', $username[0]."%");//'%'.$username[0]."%"
		// $userIndexSql->setMaxResults(30);
		// $userIndexArr = $qb->getResult();
		foreach ( $userIndexArr as $row ) {
			error_log ( "Inside for loop --->" . $row ['username'] . PHP_EOL );
			$json_array = json_decode ( $row ['metadata'], true );
			
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) {
				$pic_79x80 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] );
			} else {
				$pic_79x80 = $this->url_signer->signArrayOfUrls ( null );
			}
			if (! empty ( $json_array ['S3_files'] ['path'] )) {
				$pic_full = $json_array ['S3_files'] ['path'];
			} else {
				$pic_full = $this->url_signer->signArrayOfUrls ( null );
			}
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] )) {
				$pic_448x306 = $json_array ['S3_files'] ['thumbnails'] ['448x306'];
			} else {
				$pic_448x306 = $this->url_signer->signArrayOfUrls ( null );
			}
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] )) {
				$pic_98x78 = $json_array ['S3_files'] ['thumbnails'] ['98x78'];
			} else {
				$pic_98x78 = $this->url_signer->signArrayOfUrls ( null );
			}
			$this->userIndex [$row ['username']] = array (
					'username' => $row ['username'],
					'user_id' => $row ['user_id'],
					'profile_photo' => $pic_79x80,
					'profile_photo_79x80' => $pic_79x80,
					'profile_photo_448x306' => $pic_448x306,
					'profile_photo_98x78' => $pic_98x78,
					'profile_photo_full' => $pic_full 
			);
		}
		
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::exit" );
		return $this->userIndex;
	}
	public function processInvitedBy($invited_by = '') {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "::enter" );
		$q_notification = "SELECT n FROM Application\Entity\Notification n  where n.short_code=:short_code AND n.notification_type = :notification_type";
		$statement = $this->dbAdapter->createQuery ( $q_notification );
		$statement->setParameter ( 'short_code', $invited_by );
		$statement->setParameter ( 'notification_type', 2 );
		$row_notification = $statement->getOneOrNullResult ();
		$ndata = json_decode ( $row_notification->links );
		print_r ( $ndata );
		exit ();
		if (! empty ( $ndata->from_id ) && ! empty ( $ndata->event_id )) {
			
			$xml_input = "<xml><addfriendtoevent>
									<user_id>{$ndata->from_id}</user_id>
									<event_id>{$ndata->event_id}</event_id>
									<friends><friend>
									<network_name>memreas</network_name>
									<friend_name>$username</friend_name>
									<profile_pic_url><![CDATA[url]]>
									</profile_pic_url> </friend> </friends> </addfriendtoevent></xml>";
			// add frient to event
			$this->addfriendtoevent->exec ( $xml_input );
		}
	}
}

?>
