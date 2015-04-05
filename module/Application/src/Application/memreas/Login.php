<?php

namespace Application\memreas;

use Zend\Session\SessionManager;
use Zend\Session\Container;
use Application\Model\MemreasConstants;

class Login {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $registerDevice;
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
	public function exec() {
		try {
			$data = simplexml_load_string ( $_POST ['xml'] );
			error_log ( "Login.exec() inbound xml--->" . $_POST ['xml'] . PHP_EOL );
			// 0 = not empty, 1 = empty
			$flagusername = 0;
			$flagpass = 0;
			$username = trim ( $data->login->username );
			$device_id = trim ( $data->login->device_id );
			$device_type = trim ( $data->login->device_type );
			
			$time = time ();
			if (empty ( $username )) {
				$flagusername = 1;
			}
			$password = $data->login->password;
			if (! empty ( $password )) {
				$password = md5 ( $password );
			} else {
				$flagpass = 1;
			}
			
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<loginresponse>";
			if (isset ( $username ) && ! empty ( $username ) && isset ( $password ) && ! empty ( $password )) {
				$username = strtolower ( $username );
				$checkvalidemail = $this->is_valid_email ( $username );
				if ($checkvalidemail == TRUE) {
					$sql = "SELECT u  FROM Application\Entity\User as u  where u.email_address = '" . $username . "' and u.password = '" . $password . "'  and u.disable_account = 0";
				} else {
					$sql = "SELECT u FROM Application\Entity\User as u where u.username = '" . $username . "' and u.password = '" . $password . "'  and u.disable_account = 0";
				}
				$statement = $this->dbAdapter->createQuery ( $sql );
				
error_log ( "statement----->" . $statement->getSql() . PHP_EOL );
				$row = $statement->getResult ();
				
				if (! empty ( $row )) {
					
					$this->setSession ( $row [0] );
					if (! empty ( $device_id ) && ! empty ( $device_type )) {
						error_log ( "Inside ! empty ( device_id ) && ! empty ( device_type )" . PHP_EOL );
						/*
						 * Check if device is inserted and has registration id for type...
						 * If the device exists use the regId else return empty reg id and let device obtain reg id and call register device...
						 */
						$q_checkdevice = "SELECT device" . " from Application\Entity\Device device" . " WHERE device.user_id=?1" . 
						// " AND device.device_id=?2" .
						" AND device.device_type=?2";
						
						error_log ( "q_checkdevice--->" . $q_checkdevice . PHP_EOL );
						error_log ( "row [0]->user_id--->" . $row [0]->user_id . PHP_EOL );
						error_log ( "device_id--->" . $device_id . PHP_EOL );
						error_log ( "device_type--->" . $device_type . PHP_EOL );
						
						$checkdevice_query = $this->dbAdapter->createQuery ( $q_checkdevice );
						$checkdevice_query->setParameter ( 1, $row [0]->user_id );
						// $checkdevice_query->setParameter ( 2, $device_id );
						$checkdevice_query->setParameter ( 2, $device_type );
						$device_found_result = $checkdevice_query->getResult ();
						error_log ( "checkdevice_query->getSql()--->" . $checkdevice_query->getSql () . PHP_EOL );
						
						if (empty ( $device_found_result )) {
							error_log ( "checkdevice_query empty" . PHP_EOL );
							$device_token = '';
						} else {
							// error_log ( "checkdevice_query NOT empty" . PHP_EOL );
							// $device_token = $device_found_result [0] ->device_token;
							// error_log ( "checkdevice_query device_token----->" . $device_token . PHP_EOL );
							$device_found = false;
							$device_token = '';
							foreach ( $device_found_result as $device ) {
								$device_token = $device->device_token;
								if ($device->device_id == $device_id) {
									$device_found = true;
error_log ( "found device  device_id----->" . $device_id . PHP_EOL );
error_log ( "found device  device_token----->" . $device_token . PHP_EOL );
									break;
								}
							}
							if (! $device_found) {
error_log ( "device  not found" . PHP_EOL );
								$device_array = array (
										'registerdevice' => array (
												'user_id' => $row [0]->user_id,
												'device_id' => $device_id,
												'device_token' => $device_token,
												'device_type' => $device_type 
										) 
								);
								$this->registerDevice->exec ( true, json_encode($device_array) );
							}
						}
					}
					
					/*
					 * 30-SEP-2014 code to check if email is verified
					 */
					$user_metadata = json_decode ( $row [0]->metadata, true );
					$verified_email = isset ( $user_metadata ['user'] ['email_verified'] ) ? $user_metadata ['user'] ['email_verified'] : "0";
					
					if ($verified_email) {
						$user_id = trim ( $row [0]->user_id );
						$xml_output .= "<status>success</status>";
						$xml_output .= "<message>User logged in successfully.</message>";
						$xml_output .= "<userid>" . $user_id . "</userid>";
						$xml_output .= "<sid>" . session_id () . "</sid>";
						$xml_output .= "<device_token>" . $device_token . "</device_token>";
					} else {
						$xml_output .= "<status>failure</status><message>Please verify your email address then try again.</message>";
					}
				} else {
					$xml_output .= "<status>failure</status><message>Your Username and/or Password does not match our records.Please try again.</message>";
				}
			} else {
				$xml_output .= "<status>failure</status><message>Please checked that you have given all the data required for login.</message>";
			}
			$xml_output .= "</loginresponse>";
			$xml_output .= "</xml>";
		} catch ( \Exception $e ) {
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<loginresponse>";
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>" . $e->getMessage () . "</message>";
			$xml_output .= "</loginresponse>";
			$xml_output .= "</xml>";
		}
		
		echo $xml_output;
		error_log ( "Login ---> xml_output ----> ******" . $xml_output . "******" . PHP_EOL );
	}
	public function setSession($user) {
		$user->password = '';
		$user->disable_account = '';
		$user->create_date = '';
		$user->update_time = '';
		
		// $this->service_locator->get ( 'Zend\Session\SessionManager' )->regenerateId ();
		
		error_log ( "Inside setSession got new Container..." );
		$session = new Container ( 'user' );
		$session->offsetSet ( 'user_id', $user->user_id );
		$session->offsetSet ( 'username', $user->username );
		$session->offsetSet ( 'sid', session_id () );
		$session->offsetSet ( 'user', json_encode ( $user ) );
		error_log ( "Inside setSession set user data - just set session id ---> " . $session->offsetGet ( 'sid' ) . PHP_EOL );
		
		/*
		 * TODO: Session storage isn't working properly if I set session id after but works here.
		 */
		// error_log ( "Inside setSession3..." . PHP_EOL );
		// $_SESSION ['user'] ['user_id'] = $user->user_id;
		// $_SESSION ['user'] ['username'] = $user->username;
		// $_SESSION ['user'] ['user'] = json_encode ( $user );
		// $_SESSION ['user'] ['sid'] = session_id();
		// error_log ( "Inside setSession set user data - just set session id ---> " . $_SESSION ['user'] ['sid'] . PHP_EOL );
	}
}
?>
