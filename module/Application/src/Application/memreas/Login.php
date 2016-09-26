<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;


use Application\Model\MemreasConstants;

class Login {
	protected $message_data;
	protected $service_locator;
	protected $dbAdapter;
	protected $clientIPAddress;
	public $isWeb;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec($ipAddress = '') {
		try {
			$cm = __CLASS__ . __METHOD__;
			$data = simplexml_load_string ( $_POST ['xml'] );
				
			if (! empty ( $data->clientIPAddress )) {
				Mlog::addone ( $cm . __LINE__ . '::$data->clientIPAddress---->', ( string ) $data->clientIPAddress );
				$this->clientIPAddress = ( string ) $data->clientIPAddress;
			}
				
			//
			// fetch parameters
			//
			$this->username = trim ( $data->login->username );
			$this->device_id = (! empty ( $data->login->device_id )) ? trim ( $data->login->device_id ) : '';
			$this->device_type = (! empty ( $data->login->device_type )) ? trim ( $data->login->device_type ) : '';
			$this->device_token = (! empty ( $data->login->device_token )) ? trim ( $data->login->device_token ) : '';
			$this->memreascookie = (! empty ( $data->memreascookie )) ? trim ( $data->memreascookie ) : '';
			$this->isWeb = (! empty ( $data->memreascookie )) ? true : false;
			$this->clientIPAddress = $ipAddress;
			Mlog::addone ( $cm . '::$this->username', $this->username );
			Mlog::addone ( $cm . '::$this->device_id', $this->device_id );
			Mlog::addone ( $cm . '::$this->device_type', $this->device_type );
			Mlog::addone ( $cm . '::$this->device_token', $this->device_token );
			
			//
			// 
			//
				
			
			
			// set success status if no exception...
			$output['status'] = 'success';
			$output['message'] = 'success';
		} catch ( \Exception $e ) {
			$output['status'] = 'failure';
			$output['message'] = $e -> getMessage();
		
			Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . '$e->getMessage ()--->', $e -> getMessage());
			Mlog::addone(__CLASS__ . __METHOD__, __LINE__);
		}
		// return an array and encode in case we need to check status
		return $output;
		
		
		try {
			$cm = __CLASS__ . __METHOD__;
			
			// Mlog::addone ( $cm . '::$this->memreascookie', $this->memreascookie );
			// Mlog::addone ( $cm . '::$this->isWeb', $this->isWeb );
			// Mlog::addone ( $cm . '::$this->clientIPAddress', $this->clientIPAddress );
			
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
					Mlog::addone ( __CLASS__ . __METHOD__, __LINE__ );
					
					/*
					 * Check if the device is registered and update as needed
					 */
					try {
						if (! empty ( $this->device_type )) {
							$device_token = $this->registerDevice->checkDevice ( $row [0]->user_id, $this->device_id, $this->device_type, $this->device_token );
						}
					} catch ( Exception $e ) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$this->registerDevice->checkDevice  returned error --> ' . $e->getMessage () );
					}
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$row [0]->user_id--->' . $row [0]->user_id );
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$this->device_id--->' . $this->device_id );
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$this->device_type---->' . $this->device_type );
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$this->device_token---->' . $this->device_token );
					
					/*
					 * check if email is verified
					 */
					$user_metadata = json_decode ( $row [0]->metadata, true );
					$email = $row [0]->email_address;
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
						$xml_output .= "<email>" . $email . "</email>";
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
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$e->getMessage ()--->', $e->getMessage () );
			Mlog::addone ( __CLASS__ . __METHOD__, __LINE__ );
		}
		
		header ( "Content-type: text/xml" );
		echo $xml_output;
	}
}
?>
