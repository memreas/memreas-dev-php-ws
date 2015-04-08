<?php

namespace Application\memreas;

use Zend\Session\Container;
use Zend\View\Model\ViewModel;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Application\memreas\RmWorkDir;
use \Exception;
use Application\memreas\AddFriendtoevent;

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
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->addfriendtoevent = new AddFriendtoevent ( $message_data, $memreas_tables, $service_locator );
		$this->registerDevice = new RegisterDevice ( $message_data, $memreas_tables, $service_locator );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function is_valid_email($email) {
		$result = TRUE;
		if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
error_log("is_valid_email failed for $email".PHP_EOL);		
			$result = FALSE;
		}
		return $result;
	}
	public function exec() {
		error_log ( "Inside Registration.exec()..." . PHP_EOL );
		
		$user_id = MUUID::fetchUUID ();
		$invited_by = '';
		if (isset ( $_POST ['xml'] )) {
			error_log ( "Inside Registration xml request ----> " . $_POST ['xml'] . PHP_EOL );
			$data = simplexml_load_string ( $_POST ['xml'] );
			$username = trim ( $data->registration->username );
			$email = trim ( $data->registration->email );
			$email = strtolower ( $email );
			$password = trim ( $data->registration->password );
			$device_id = trim ( $data->registration->device_id );
			$device_type = trim ( $data->registration->device_type );
			$invited_by = trim ( $data->registration->invited_by );
			// $invited_by = $this->is_valid_email ( $invited_by ) ? $invited_by : '';
			$assign_event = trim ( $data->registration->event_id );
		} else {
			$username = trim ( $_REQUEST ['username'] );
			$email = trim ( $_REQUEST ['email'] );
			$email = strtolower ( $email );
			$password = trim ( $_REQUEST ['password'] );
			$device_id = trim ( $_REQUEST ['device_id'] );
			$device_type = trim ( $_REQUEST ['device_type'] );
			if (isset ( $_REQUEST ['invited_by'] ) && (! empty ( $_REQUEST ['invited_by'] ))) {
				$invited_by = $_REQUEST ['invited_by'];
			} else {
				$invited_by = null;
			}
		}

error_log("username--->".$username.PHP_EOL);		
error_log("email--->".$email.PHP_EOL);		
error_log("password--->".$password.PHP_EOL);		
error_log("device_id--->".$device_id.PHP_EOL);		
error_log("device_type--->".$device_type.PHP_EOL);		
error_log("invited_by--->".$invited_by.PHP_EOL);		
error_log("event_id--->".$event_id.PHP_EOL);		
		
		
		
		// $this->FunctionName($invited_by);exit;
		try {
			if (isset ( $email ) && ! empty ( $email ) && isset ( $username ) && ! empty ( $username ) && isset ( $password ) && ! empty ( $password )) {
				$checkvalidemail = $this->is_valid_email ( $email );
				
				if (! $checkvalidemail) {
					// throw new \Exception ( 'Your profile is not created successfully. Please enter valid email address.' );
					$status = 'failure';
					$message = 'email address is invalid';
				} else {
					
					/*
					 * TODO: Fix email check prior to go-beta...
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
							$message = 'Your profile is not created successfully. Email is already exist.';
						} else if (($result->username == $username) && ($result->email_address != $email)) {
							$message = 'Your profile is not created successfully. User name is already exist.';
						} else if (($result->username == $username) && ($result->email_address == $email)) {
							$message = 'Your profile is not created successfully. User name and email are already exist.';
						}
					} else {
						$status = 'Success';
						/*
						 * 22-SEP-2013 JM: added entry for email address verification
						 */
						$email_verification_id = MUUID::fetchUUID ();
						
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
						$statusid = 0;
						$forgottoken = "";
						$created = strtotime ( date ( 'Y-m-d H:i:s' ) );
						$modified = strtotime ( date ( 'Y-m-d H:i:s' ) );
						
						$tblUser = new \Application\Entity\User ();
						$tblUser->email_address = $email;
						$tblUser->password = $password;
						$tblUser->user_id = $user_id;
						$tblUser->username = $username;
						$tblUser->role = $roleid;
						$tblUser->metadata = $metadata;
						$tblUser->disable_account = $statusid;
						$tblUser->forgot_token = $forgottoken;
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
						$q_notification = "SELECT n FROM Application\Entity\Notification n  where n.short_code=:short_code AND n.notification_type = :notification_type";
						$statement = $this->dbAdapter->createQuery ( $q_notification );
						$statement->setParameter ( 'short_code', $invited_by );
						$statement->setParameter ( 'notification_type', 2 );
						$row_notification = $statement->getOneOrNullResult ();
						if (! empty ( $row_notification ))
							$ndata = json_decode ( $row_notification->links );
						else
							$ndata = null;
						if (! empty ( $ndata->from_id ) && ! empty ( $ndata->event_id )) {
							
							$xml_input = '<xml><addfriendtoevent>
									<user_id>' . $ndata->from_id . '</user_id>
									<event_id>' . $ndata->event_id . '</event_id>
									<friends>
											<friend>
											<network_name>memreas</network_name>
											<friend_name>' . $username . '</friend_name>
											<profile_pic_url><![CDATA[' . $url . ']]>' . '</profile_pic_url>
											</friend>
									</friends>
									</addfriendtoevent></xml>';
							
							// add frient to event
							$this->addfriendtoevent->exec ( $xml_input );
						}
						
						// Check if user has been assigned an event
						if ($assign_event) {
							$query = $this->dbAdapter->createQueryBuilder ();
							$query->select ( 'e' )->from ( '\Application\Entity\Event', 'e' )->where ( 'e.event_id = ?1' )->setParameter ( 1, $assign_event );
							$result = $query->getQuery ()->getResult ();
							
							$memreas_tables = new MemreasTables ( $this->service_locator );
							$message_data = '<xml><addfriendtoevent>
                                                    <user_id>' . $result [0]->user_id . '</user_id>
                                                    <event_id>' . $assign_event . '</event_id>
                                                    <emails><email></email></emails>
                                                    <friends>
                                                        <friend>
                                                            <network_name>memreas</network_name>
                                                            <friend_name>' . $username . '</friend_name>
                                                            <friend_id>' . $user_id . '</friend_id>
                                                            <profile_pic_url></profile_pic_url>
                                                        </friend>
                                                    </friends></addfriendtoevent></xml>';
							
							$MemreasEvent = new AddFriendtoevent ( $message_data, $memreas_tables, $this->service_locator );
							$MemreasEvent->exec ( $message_data );
						}
						
						// upload profile image
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
							$s3_data = $aws_manager->webserviceUpload ( $user_id, $dirPath, $s3file_name, $content_type );
							
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
						
						/*
						 * Check if the device is registered and update as needed
						 */
						$this->registerDevice->checkDevice ( $user_id, $device_id, $device_type );
						
						// error_log ( "About to email..." . PHP_EOL );
						// API Info
						// http://docs.aws.amazon.com/AWSSDKforPHP/latest/index.html#m=AmazonSES/send_email
						// Always set content-type when sending HTML email
						
						$to [] = $email;
						
						$viewVar = array (
								'email' => $email,
								'username' => $username,
								'email_verification_url' => $meta_arr ['user'] ['email_verification_url'] 
						);
						$viewModel = new ViewModel ( $viewVar );
						$viewModel->setTemplate ( 'email/register' );
						$viewRender = $this->service_locator->get ( 'ViewRenderer' );
						$html = $viewRender->render ( $viewModel );
						$subject = 'Welcome to memreas';
						if (empty ( $aws_manager ))
							$aws_manager = new AWSManagerSender ( $this->service_locator );
							/*
						 * 9-OCT-2014 debugging perf tester
						 */
						if (MemreasConstants::SEND_EMAIL) {
							$aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
						}
						$this->status = $status = 'Success';
						$message = "Welcome to memreas. Your profile has been created.  Please verify your email next";
						
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
			$xml_output .= "</registrationresponse>";
			$xml_output .= "</xml>";
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
		
		ob_clean ();
		echo $xml_output;
		// error_log("registration xml_output -------> *****" . $xml_output . "*****" . PHP_EOL);
		
		$this->username = $username;
		$this->user_id = $user_id;
		$filename = $s3_data ['s3path'] . $s3_data ['s3file_name'];
		$this->profile_photo = ! empty ( $filename ) ? $s3_data ['s3path'] . $s3_data ['s3file_name'] : '';
		// return array ('user_id' => $user_id, 'username' => $username, 'profile_photo' => $s3_data ['s3path'] . $s3_data ['s3file_name'] );
	} //end exec() 
	
	function createUserCache() {
error_log("Inside function createUserCache()".PHP_EOL); 
		$qb = $this->dbAdapter->createQueryBuilder ();
		$qb->select ( 'u.user_id', 'u.username', 'm.metadata' );
		$qb->from ( 'Application\Entity\User', 'u' );
		$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
		//$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id' );
//error_log("qb --->".$qb.PHP_EOL);
error_log("SQL Query --->".$qb->getQuery()->getSql().PHP_EOL);		
		
		// create index for catch;
		$userIndexArr = $qb->getQuery ()->getResult ();
		// $userIndexArr = $this->dbAdapter->createQuery ( 'SELECT u.user_id,u.username FROM Application\Entity\User u Where u.disable_account=0 ORDER BY u.username' );
		// AND u.username LIKE :username $userIndexSql->setParameter ( 'username', $username[0]."%");//'%'.$username[0]."%"
		// $userIndexSql->setMaxResults(30);
		// $userIndexArr = $qb->getResult();
		foreach ( $userIndexArr as $row ) {
error_log("Inside for loop --->".$row['username'].PHP_EOL);
			$json_array = json_decode ( $row ['metadata'], true );
			
			if (empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] )) {
				$url1 = MemreasConstants::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
			} else {
				$url1 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] );
			}
			$this->userIndex [$row ['username']] = array (
					'username' => $row ['username'],
					'user_id' => $row ['user_id'],
					'profile_photo' => $url1 
			);
		}
		
		return $this->userIndex;
	}
	public function FunctionName($invited_by = '') {
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
