<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;
use \Exception;

class Registration {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function is_valid_email($email) {
        error_log("Inside is_valid_email...");
        $result = TRUE;
        if (!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email)) {
            $result = FALSE;
        }
        return $result;
    }

    public function exec() {
        error_log("Inside exec...");
        $user_id = UUID::getUUID($this->dbAdapter);
        error_log("Inside exec user_id is $user_id...");
error_log("_REQUEST----> " . print_r($_REQUEST, true) . PHP_EOL);

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

        error_log("Inside exec loaded data...");
        try {
            if (isset($email) && !empty($email) && isset($username) && !empty($username) && isset($password) && !empty($password)) {
                $checkvalidemail = $this->is_valid_email($email);

                if (!$checkvalidemail)
                    throw new \Exception('Your profile is not created successfully. Please enter valid email address.');


                error_log("Inside exec setting sql...");

                $sql = "SELECT u FROM Application\Entity\User u  where u.email_address = '$email' or u.username = '$username'";
error_log("Inside exec setting sql - sql ----> $sql" . PHP_EOL);

                //$statement = $this->dbAdapter->createStatement($sql);
                //$result = $statement->execute();
                //$row = $result->current();
                $statement = $this->dbAdapter->createQuery($sql);
//error_log("Inside exec setting sql - statement ----> $statement" . PHP_EOL);
                $result = $statement->getOneOrNullResult();
//error_log("Inside exec setting sql - result ----> $result" . PHP_EOL);


                error_log("Inside exec fetched sql...");
                if (!empty($result)) {
                    if (($result->email_address == $email) && ($result->username != $username)) {
                        throw new \Exception('Your profile is not created successfully. Email is already exist.');
                    } else if (($result->username == $username) && ($result->email_address != $email)) {
                        throw new \Exception('Your profile is not created successfully. User name is already exist.');
                    } else if (($result->username == $username) && ($result->email_address == $email)) {
                        throw new \Exception('Your profile is not created successfully. User name and email are already exist.');
                    }
                }


                //$result = mysql_query($query);
                //if (mysql_num_rows($result) > 0) {
                //	if ($row = mysql_fetch_array($result)) {
//...
                //}

                $passwrd = $password;
                $password = md5($password);
                $roleid = 2;
                $statusid = 0;
                $forgottoken = "";
                $created = strtotime(date('Y-m-d H:i:s'));
                $modified = strtotime(date('Y-m-d H:i:s'));

                error_log("Inside exec setting 2nd sql...");

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
                
                //	$sql = "insert into Application\Entity\User (user_id,`email_address`,`password`,`username`,`role`,`disable_account`,`create_date`,`update_time`) values ('$user_id','" . $email . "','" . $password . "','" . $username . "'," . $roleid . "," . $statusid . ",'" . $created . "','" . $modified . "')";
                //$statement = $this->dbAdapter->createStatement($sql);
                //$result = $statement->execute();
                //$row = $result->current();
                //  $statement = $this->dbAdapter->createQuery($sql);
                //$result = $statement->getResult();
                //$iresult = mysql_query($iquery) or die(mysql_error());
                //if (mysql_affected_rows() < 0)
                //	throw new Exception('Unable to add record.');
                error_log("Inside adding device...");
                echo '<pre>';

                if(!empty($device_token)&& !empty($device_type)){
					$device_id = UUID::getUUID($this->dbAdapter);
				 
					$tblDevice = new \Application\Entity\Device();
					$tblDevice->device_id = $device_id;
					$tblDevice->device_token = $device_token;
					$tblDevice->user_id = $user_id;
					$tblDevice->create_time = $created;
					$tblDevice->update_time = $modified;


					$this->dbAdapter->persist($tblDevice);
					$this->dbAdapter->flush();
                }
error_log("_FILES -----> " . print_r($_FILES['f']) . PHP_EOL);
error_log("__DIR__ -----> " . dirname(__DIR__) . PHP_EOL);
error_log(" cwd -----> " . getcwd() . PHP_EOL);
error_log("__DIR__ -----> " . dirname(__DIR__) . PHP_EOL);
                // upload profile image
                if (isset($_FILES['f']) && !empty($_FILES['f']['name'])) {
                    $s3file_name = time() . $_FILES['f']['name'];
                    $content_type = $_FILES['f']['type'];
                    //$dirPath = dirname(__DIR__) . "/data/media/";
                    $dirPath = getcwd() . MemreasConstants::DIR_PATH;
                    $file = $dirPath . $s3file_name;

                    if (strpos($_FILES['f']['type'], 'image') < 0)
                        throw new \Exception('Your profile is not created successfully. Please Upload Image.');

                    $move = move_uploaded_file($_FILES['f']['tmp_name'], $file);
                    if (!$move)
                        throw new \Exception('Please Upload Image.');
                    $media_id = UUID::getUUID($this->dbAdapter);

                    $aws_manager = new AWSManager($this->service_locator);
                    $s3url = $aws_manager->webserviceUpload($user_id, $s3file_name, $content_type);
                    $paths = $aws_manager->s3upload($user_id, $media_id, $s3file_name, $content_type, $file, $isVideo = false);
                    //$s3path = $user_id . '/image/';
                    $json_array = array(
                    				"S3_files" => array(
                    								"path" => $s3url, 
                    								"Full" => $s3url,
                    								"79x80" => $paths['79x80_Path'],
                    								"448x306" => $paths['448x306_Path'],
                    								"98x78" => $paths['98x78_Path'],
                    							),
                        			"local_filenames" => 
                        					array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
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
                    /* 	$q = "INSERT INTO Application\Entity\Media (media_id,
                      user_id ,
                      is_profile_pic,
                      metadata,
                      create_date,
                      update_date)
                      VALUES ('$media_id',
                      '$user_id',
                      '1',
                      '$json_str',
                      '$time', '$time')";
                     * 
                     */
                    //$query_result = mysql_query($q);
                    //$statement = $this->dbAdapter->createStatement($q);
                    //	$query_result = $statement->execute();
                    //$row = $result->current();
                    // $statement = $this->dbAdapter->createQuery($q);
                    //$query_result = $statement->getResult();



                    $q_update = "UPDATE Application\Entity\User u  SET u.profile_photo = '1' WHERE u.user_id ='$user_id'";
                    //$r = mysql_query($q_update);
                    //$statement = $this->dbAdapter->createStatement($q_update);
                    //$r = $statement->execute();
                    //$row = $r->current();
                    $statement = $this->dbAdapter->createQuery($q_update);
                    $r = $statement->getResult();




                    $message_data = array(
                        'user_id' => $user_id,
                        'media_id' => $media_id,
                        'content_type' => $content_type,
                        's3path' => $s3path,
                        's3file_name' => $s3file_name,
                        'isVideo' => 0,
                        'email' => $email
                    );

                    //Process Message here - 
                    //$aws_manager = new AWSManager();
                    //$response = $aws_manager->snsProcessMediaPublish($message_data);
                    //what should condition over here

                    $status = 'Success';
                    $message = "Media Successfully add";
                }

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

                $status = 'Success';
                $message = "Welcome to Event App. Your profile has been created.";
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
