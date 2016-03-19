<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\SessionManager;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MemreasSignedCookie;

class Login {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $registerDevice;
	protected $username;
	protected $password;
	protected $device_id;
	protected $device_type;
	protected $memreascookie;
	protected $clientIPAddress;
	public $isWeb;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->registerDevice = new RegisterDevice ( $message_data, $memreas_tables, $service_locator );
	}
	public function is_valid_email($email) {
		$result = TRUE;
		if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
			$result = FALSE;
		}
		return $result;
	}
	public function exec($sessHandler, $ipAddress = '') {
		try {
			//Mlog::addone(__CLASS__.__METHOD__, __LINE__);
			
			$data = simplexml_load_string ( $_POST ['xml'] );
			// error_log ( "Login.exec() inbound xml--->" . $_POST ['xml'] . PHP_EOL );
			// 0 = not empty, 1 = empty
			$flagusername = 0;
			$flagpass = 0;
			$this->username = trim ( $data->login->username );
			$this->device_id = (! empty ( $data->login->device_id )) ? trim ( $data->login->device_id ) : '';
			$this->device_type = (! empty ( $data->login->device_type )) ? trim ( $data->login->device_type ) : '';
			$this->memreascookie = (! empty ( $data->memreascookie )) ? trim ( $data->memreascookie ) : '';
			$this->isWeb = (! empty ( $data->memreascookie )) ? true : false;
			$this->clientIPAddress = $ipAddress;
			$cm = __CLASS__ . __METHOD__;
			//Mlog::addone ( $cm . '::$this->username', $this->username );
			//Mlog::addone ( $cm . '::$this->device_id', $this->device_id );
			//Mlog::addone ( $cm . '::$this->device_type', $this->device_type );
			//Mlog::addone ( $cm . '::$this->memreascookie', $this->memreascookie );
			//Mlog::addone ( $cm . '::$this->isWeb', $this->isWeb );
			//Mlog::addone ( $cm . '::$this->clientIPAddress', $this->clientIPAddress );
			
			$time = time ();
			if (empty ( $this->username )) {
				$flagusername = 1;
			}
			$this->password = $data->login->password;
			if (! empty ( $this->password )) {
				$this->password = md5 ( $this->password );
			} else {
				$flagpass = 1;
			}
			
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<loginresponse>";
			if (isset ( $this->username ) && ! empty ( $this->username ) && isset ( $this->password ) && ! empty ( $this->password )) {
				$this->username = strtolower ( $this->username );
				$checkvalidemail = $this->is_valid_email ( $this->username );
				if ($checkvalidemail == TRUE) {
					$sql = "SELECT u  FROM Application\Entity\User as u  where u.email_address = '" . $this->username . "' and u.password = '" . $this->password . "'  and u.disable_account = 0";
				} else {
					$sql = "SELECT u FROM Application\Entity\User as u where u.username = '" . $this->username . "' and u.password = '" . $this->password . "'  and u.disable_account = 0";
				}
				
				Mlog::addone ( $cm . '::$sql', $sql );
				$statement = $this->dbAdapter->createQuery ( $sql );
				$row = $statement->getResult ();
				
				if (! empty ( $row )) {
					/*
					 * Set the session for the user data...
					 */
					$sessHandler->setSession ( $row [0], $this->device_id, $this->device_type, $this->memreascookie, $this->clientIPAddress );
					Mlog::addone(__CLASS__.__METHOD__, __LINE__);
						
					/*
					 * Check if the device is registered and update as needed
					 */
					$device_token = '';
					if (! empty ( $this->device_type )) {
						$device_token = $this->registerDevice->checkDevice ( $row [0]->user_id, $this->device_id, $this->device_type );
					}
					Mlog::addone(__CLASS__.__METHOD__, __LINE__);
						
					/*
					 * check if email is verified
					 */
					$user_metadata = json_decode ( $row [0]->metadata, true );
					$verified_email = isset ( $user_metadata ['user'] ['email_verified'] ) ? $user_metadata ['user'] ['email_verified'] : "0";
					
					if ($verified_email) {
						//
						// success -> xml output
						//
						$user_id = trim ( $row [0]->user_id );
						$username = $row [0]->username;
						$xml_output .= "<status>success</status>";
						$xml_output .= "<message>User logged in successfully.</message>";
						$xml_output .= "<user_id>" . $user_id . "</user_id>";
						$xml_output .= "<username>" . $username . "</username>";
						$xml_output .= "<sid>" . session_id () . "</sid>";
						$xml_output .= "<device_token><![CDATA[" . $device_token . "]]></device_token>";
					} else {
						$xml_output .= "<status>failure</status><message>Please verify your email address then try again.</message>";
					}
				} else {
					$xml_output .= "<status>failure</status><message>Your Username and/or Password does not match our records.Please try again.</message>";
				}
			} else {
				$xml_output .= "<status>failure</status><message>Please checked that you have provided all the data required for login.</message>";
			}
			$xml_output .= "</loginresponse>";
			$xml_output .= "</xml>";
		} catch ( \Exception $e ) {
			$xml_output = '<?xml version="1.0"  encoding="utf-8" ?>';
			$xml_output .= "<xml>";
			$xml_output .= "<loginresponse>";
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>" . $e->getMessage () . "</message>";
			$xml_output .= "</loginresponse>";
			$xml_output .= "</xml>";
			Mlog::addone(__CLASS__.__METHOD__, __LINE__);
		}
		
		header ( "Content-type: text/xml" );
		echo $xml_output;
		//error_log ( "Login ---> xml_output ----> ******" . $xml_output . "******" . PHP_EOL );
	}
}
?>
