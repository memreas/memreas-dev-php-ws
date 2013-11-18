<?php
namespace Application\memreas;
 

use Zend\Session\Container;

use Application\Model\MemreasConstants;

class Login {

	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;

	public function __construct($message_data, $memreas_tables, $service_locator) {
	   $this->message_data = $message_data;
	   $this->memreas_tables = $memreas_tables;
	   $this->service_locator = $service_locator;
	   $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');

	}


	public function is_valid_email($email) {
		$result = TRUE;
		if (!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email)) {
			$result = FALSE;
		}
		return $result;
	}

	public function exec() {
		$data = simplexml_load_string($_POST['xml']);
		//0 = not empty, 1 = empty
		$flagusername = 0;
		$flagpass = 0;
		$username = trim($data->login->username);
        $devicetoken = trim($data->login->devicetoken);
        $devicetype = trim($data->login->devicetype);
        $time = time();
		if (empty($username)) {    
			$flagusername = 1;
		}
		$password = $data->login->password;
		if (!empty($password)) {
			$password = md5($password);
		} else {
			$flagpass = 1;
		}
		
		header("Content-type: text/xml");
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<loginresponse>";
		if (isset($username) && !empty($username) && isset($password) && !empty($password)) {
			$username = strtolower($username);
			$checkvalidemail = $this->is_valid_email($username);
			if ($checkvalidemail == TRUE) {
				$sql = "SELECT u  FROM Application\Entity\User as u  where u.email_address = '" . $username . "' and u.password = '" . $password . "' and u.role= 2 and u.disable_account = 0";
			} else {
				$sql = "SELECT u FROM Application\Entity\User as u where u.username = '" . $username . "' and u.password = '" . $password . "' and u.role = 2 and u.disable_account = 0";
			}
			$statement = $this->dbAdapter->createQuery($sql);
			$row = $statement->getResult();
			if (!empty($row)) {
				if(!empty($devicetoken)&& !empty($devicetype)){            
					$qb = $this->dbAdapter->createQueryBuilder();
				  	$q = $qb->update('\Application\Entity\Device', 'd')
						->set('d.device_token', $qb->expr()->literal($devicetoken))
						->set('d.update_time', $qb->expr()->literal($time))
						->where('d.user_id = ?1 AND d.device_type = ?2')
						->setParameter(1, $row[0]->user_id)
						->setParameter(2, $devicetype)
						->getQuery();
					$p = $q->execute();
				}
				$user_id = trim($row[0]->user_id);
				$xml_output .= "<status>success</status>";
				$xml_output .= "<message>User logged in successfully.</message>";
				$xml_output .= "<userid>". $user_id ."</userid>";
			} else {
				$xml_output .= "<status>failure</status><message>Your Username and/or Password does not match our records.Please try again.</message>";
			}
		}else {
			$xml_output .= "<status>failure</status><message>Please checked that you have given all the data required for login.</message>";
		}
		$xml_output .= "</loginresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
error_log ("Login ---> xml_output ----> " . $xml_output . PHP_EOL);
	}
	
}
?>
