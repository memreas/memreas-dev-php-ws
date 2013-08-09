<?php
namespace memreas;

use Zend\Session\Container;

use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

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
	   $this->dbAdapter = $service_locator->get('memreasdevdb');
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

		$data = simplexml_load_string($_POST['xml']);
		$username = trim($data->registration->username);
		$email = trim($data->registration->email);
		$email = strtolower($email);
		$password = trim($data->registration->password);
		$device_id = trim($data->registration->device_id);
		//$username = trim($_POST['username']);
		//$email = trim($_POST['email']);
		//$password = trim($_POST['password']);
		//$device_id = trim($_POST['device_id']);

error_log("Inside exec loaded data...");
		try {
			if (isset($email) && !empty($email) && isset($username) && !empty($username) && isset($password) && !empty($password)) {
				$checkvalidemail = $this->is_valid_email($email);

				if (!$checkvalidemail)
					throw new Exception('Your profile is not created successfully. Please enter valid email address.');


error_log("Inside exec setting sql...");

				$sql = "SELECT * FROM user where email_address = '$email' or username = '$username'";

				$statement = $this->dbAdapter->createStatement($sql);
				$result = $statement->execute();
				$row = $result->current();

error_log("Inside exec fetched sql...");
				if (!empty($row)) {
					if (($row['email_address'] == $email) && ($row['username'] != $username)) {
						throw new \Exception('Your profile is not created successfully. Email is already exist.');
					} else if (($row['username'] == $username) && ($row['email_address'] != $email)) {
						throw new \Exception('Your profile is not created successfully. User name is already exist.');
					} else if (($row['username'] == $username) && ($row['email_address'] == $email)) {
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
				$created = strtotime(date('Y-m-d H:i:s'));
				$modified = strtotime(date('Y-m-d H:i:s'));

error_log("Inside exec setting 2nd sql...");
				$sql = "insert into user (user_id,`email_address`,`password`,`username`,`role`,`disable_account`,`create_date`,`update_time`) values ('$user_id','" . $email . "','" . $password . "','" . $username . "'," . $roleid . "," . $statusid . ",'" . $created . "','" . $modified . "')";

				$statement = $this->dbAdapter->createStatement($sql);
				$result = $statement->execute();
				$row = $result->current();

				if (empty($row)) {
					throw new Exception('Unable to add record.');
				}

				//$iresult = mysql_query($iquery) or die(mysql_error());
				//if (mysql_affected_rows() < 0)
				//	throw new Exception('Unable to add record.');


error_log("Inside exec setting 2nd sql...");
		// upload profile image
				if (isset($_FILES['f']) && !empty($_FILES['f']['name'])) {
					$s3file_name = time() . $_FILES['f']['name'];
					$content_type = $_FILES['f']['type'];
					$dirPath = dirname(__DIR__) . "/media/";
					$file = $dirPath . $s3file_name;


					if (strpos($_FILES['f']['type'], 'image') < 0)
						throw new Exception('Your profile is not created successfully. Please Upload Image.');

					$move = move_uploaded_file($_FILES['f']['tmp_name'], $file);
					if (!$move)
						throw new Exception('Please Upload Image.');
					$media_id = getUUID();

					$aws_manager = new AWSManager();
					$s3url = $aws_manager->webserviceUpload($user_id, $s3file_name, $content_type);
					$s3path = $user_id . '/image/';
					$json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,),
						"local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
						"type" => array("image" => array("format" => $file_type[1]))
					);
					$json_str = json_encode($json_array);
					$time = time();
					$q = "INSERT INTO media(media_id,
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
					$query_result = mysql_query($q);
					if (!$query_result)
						throw new Exception('Error : ' . mysql_error());

					 $q_update = "UPDATE user SET profile_photo = '1' WHERE user_id ='$user_id'";
					$r = mysql_query($q_update);
					if (!$r)
						throw new Exception('Error : ' . mysql_error());


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
					if ($response == 1) {
						$status = 'Success';
						$message = "Media Successfully add";
					}
					else
						throw new Exception('your Profile hase been created but Error In snsProcessMediaPublish');
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
				throw new Exception('Your profile is not created successfully. Please check all data you have inserted are proper.');
			}
		} catch (Exception $exc) {
			$status = 'Failure';
			$message = $exc->getMessage();
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
