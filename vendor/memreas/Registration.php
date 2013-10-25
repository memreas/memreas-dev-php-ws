<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\UUID;
use memreas\AWSManagerSender;
use memreas\RmWorkDir;
use \Exception;

class Registration {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function is_valid_email($email) {
        $result = TRUE;
        if (!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email)) {
            $result = FALSE;
        }
        return $result;
    }

    public function exec() {
        $user_id = UUID::getUUID($this->dbAdapter);

		if (isset($_POST['xml'])) {
			$data = simplexml_load_string($_POST['xml']);
			$username = trim($data->registration->username);
			$email = trim($data->registration->email);
			$email = strtolower($email);
			$password = trim($data->registration->password);
			$device_token = trim($data->registration->device_token);
			$device_type = trim($data->registration->device_type);
		} else {
			$username = trim($_REQUEST['username']);
			$email = trim($_REQUEST['email']);
			$email = strtolower($email);
			$password = trim($_REQUEST['password']);
			$device_token = trim($_REQUEST['device_token']);
			$device_type = trim($_REQUEST['device_type']);
		}

        try {
            if (isset($email) && !empty($email) && isset($username) && !empty($username) && isset($password) && !empty($password)) {
                $checkvalidemail = $this->is_valid_email($email);

                if (!$checkvalidemail)
                    throw new \Exception('Your profile is not created successfully. Please enter valid email address.');

                $sql = "SELECT u FROM Application\Entity\User u  where u.email_address = '$email' or u.username = '$username'";
                $statement = $this->dbAdapter->createQuery($sql);
                $result = $statement->getOneOrNullResult();

                if (!empty($result)) {
                    if (($result->email_address == $email) && ($result->username != $username)) {
                        throw new \Exception('Your profile is not created successfully. Email is already exist.');
                    } else if (($result->username == $username) && ($result->email_address != $email)) {
                        throw new \Exception('Your profile is not created successfully. User name is already exist.');
                    } else if (($result->username == $username) && ($result->email_address == $email)) {
                        throw new \Exception('Your profile is not created successfully. User name and email are already exist.');
                    }
                }

                $passwrd = $password;
                $password = md5($password);
                $roleid = 2;
                $statusid = 0;
                $forgottoken = "";
                $created = strtotime(date('Y-m-d H:i:s'));
                $modified = strtotime(date('Y-m-d H:i:s'));

                $tblUser = new \Application\Entity\User();
                $tblUser->email_address = $email;
                $tblUser->password = $password;
                $tblUser->user_id = $user_id;
                $tblUser->username = $username;
                $tblUser->role = $roleid;
                $tblUser->disable_account = $statusid;
                $tblUser->forgot_token = $forgottoken;
                $tblUser->create_date = $created;
                $tblUser->update_time = $modified;

                $this->dbAdapter->persist($tblUser);
                $this->dbAdapter->flush();
                
                echo '<pre>';

                if(!empty($device_token)&& !empty($device_type)){
					$device_id = UUID::getUUID($this->dbAdapter);
				 
					$tblDevice = new \Application\Entity\Device();
					$tblDevice->device_id = $device_id;
					$tblDevice->device_token = $device_token;
					$tblDevice->user_id = $user_id;
                    $tblDevice->device_type = $device_type;
					$tblDevice->create_time = $created;
					$tblDevice->update_time = $modified;
					$this->dbAdapter->persist($tblDevice);
					$this->dbAdapter->flush();
                }

                // upload profile image
                if (isset($_FILES['f']) && !empty($_FILES['f']['name'])) {
                    $s3file_name = time() . $_FILES['f']['name'];
                    $content_type = $_FILES['f']['type'];

                    // dirPath = /data/temp_uuid/media/userimage/
					$temp_job_uuid_dir = UUID::getUUID($this->dbAdapter);
                    $dirPath = getcwd() . MemreasConstants::DATA_PATH . $temp_job_uuid_dir . MemreasConstants::IMAGES_PATH;
					if (!file_exists($dirPath)) {
						mkdir($dirPath, 0755, true);
					}
                    $file = $dirPath . $s3file_name;
                    if (strpos($_FILES['f']['type'], 'image') < 0)
                        throw new \Exception('Your profile is not created successfully. Please Upload Image.');

                    $move = move_uploaded_file($_FILES['f']['tmp_name'], $file);
                    if (!$move)
                        throw new \Exception('Please Upload Image.');


error_log("About to upload to S3" . PHP_EOL);
					//Upload to S3 here
                    $media_id = UUID::getUUID($this->dbAdapter);
                    $aws_manager = new AWSManagerSender($this->service_locator);
                    $s3_data = $aws_manager->webserviceUpload($user_id, $dirPath, $s3file_name, $content_type);

error_log("s3_data['s3path']----> " . $s3_data['s3path'] . PHP_EOL);
error_log("s3_data['s3file_name'] ----> " . $s3_data['s3file_name'] . PHP_EOL);

                    //Set the metatdata
                    //$s3path = $user_id . '/image/';
                    $json_array = array(
						"S3_files" => array(
							"path" => $s3_data['s3path'].$s3_data['s3file_name'],
							"Full" => $s3_data['s3path'].$s3_data['s3file_name'],
						),
						"local_filenames" => 
								array("device" => 
									array("unique_device_identifier1" => $user_id . '_' . $divice_id,),
								),
						"type" => 
							array("image" => array("format" => $file_type[1]))
                    );
                    $json_str = json_encode($json_array);
                    $time = time();
                    $tblMedia = new \Application\Entity\Media();

                    $tblMedia->media_id = $media_id;
                    $tblMedia->user_id = $user_id;
                    $tblMedia->is_profile_pic = '1';
                    $tblMedia->metadata = $json_str;
                    $tblMedia->create_date = $time;
                    $tblMedia->update_date = $time;

                    $this->dbAdapter->persist($tblMedia);
                    $this->dbAdapter->flush();

                    $q_update = "UPDATE Application\Entity\User u  SET u.profile_photo = '1' WHERE u.user_id ='$user_id'";
                    $statement = $this->dbAdapter->createQuery($q_update);
                    $r = $statement->getResult();
error_log("statement->getResult() ----> " . print_r($r,true) . PHP_EOL);
error_log("*************************************************************" . PHP_EOL);
error_log("message_data ----> " . print_r($message_data,true) . PHP_EOL);

					//Now publish the message so any photo is thumbnailed.
                    $message_data = array(
                        'user_id' => $user_id,
                        'media_id' => $media_id,
                        'content_type' => $content_type,
                        's3path' => $s3_data['s3path'],
                        's3file_name' => $s3_data['s3file_name'],
                        'isVideo' => 0,
                        'email' => $email
                    );

                    $aws_manager = new AWSManagerSender($this->service_locator);
                    $response = $aws_manager->snsProcessMediaPublish($message_data);
                    
					//Remove the work directory
					$dir = getcwd() . MemreasConstants::DATA_PATH . $temp_job_uuid_dir;
					$dirRemoved = new RmWorkDir($dir);

                    $status = 'Success';
                    $message = "Media Successfully add";
                }

				/*
error_log("About to email...". PHP_EOL);
//API Info
//http://docs.aws.amazon.com/AWSSDKforPHP/latest/index.html#m=AmazonSES/send_email

$m = new SimpleEmailServiceMessage();
$m->addTo('johnmeah@memreas.com');
$m->setFrom('johnmeah@memreas.com');
$m->setSubject('Hello, world!');
$m->setMessageFromString('This is the message body.');

error_log(print_r($ses->sendEmail($m)));

                // Always set content-type when sending HTML email
                $to = $email;
                $subject = "Welcome to Event App";
                $message = "<p>Hello " . $username . ",</p>";
                $message .= "<p>Welcome to Event App</p>";
                $message .= "<p>Your username is: " . $email . "</p>";
                $message .= "<p>Your Password is: " . $passwrd . "</p>";
                $message .= "<p>Thanks and Regards,</p>";
                $message .= "<p><b>Event App Team</b></p>";
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
                $headers .= 'From: <admin@eventapp.com>' . "\r\n";
                mail($to, $subject, $message, $headers);
                */

                $status = 'Success';
                $message = "Welcome to Event App. Your profile has been created.";
error_log("Finished...". PHP_EOL);
            } else {
                throw new \Exception('Your profile is not created successfully. Please check all data you have inserted are proper.');
            }
        } catch (\Exception $exc) {
            $status = 'Failure';
			$message = $exc->getMessage();
            error_log("error message ----> $message" . PHP_EOL);
        }

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<registrationresponse>";
        $xml_output .= "<status>$status</status>";
        $xml_output .= "<message>$message</message>";
        $xml_output .= "<userid>" . $user_id . "</userid>";
        $xml_output .= "</registrationresponse>";
        $xml_output .= "</xml>";
        ob_clean();
        echo $xml_output;
    }

}

?>
