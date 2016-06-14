<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

class RegisterDevice {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function checkDevice($user_id, $device_id, $device_type, $device_token_login = '') {
		/*
		 * Check if device is inserted and has registration id for type...
		 * If the device exists use the regId else return empty reg id and let device obtain reg id and call register device...
		 */
		$q_checkdevice = "SELECT device
				from Application\Entity\Device device
				WHERE device.user_id='$user_id'
				AND device.device_type='$device_type'";
		
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "q_checkdevice--->" . $q_checkdevice );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "user_id--->" . $user_id );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "device_id--->" . $device_id );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "device_type--->" . $device_type );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "device_type--->" . $device_token_login );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "q_checkdevice--->" . $q_checkdevice );
		
		$checkdevice_query = $this->dbAdapter->createQuery ( $q_checkdevice );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "checkdevice_query->getSql()--->" . $checkdevice_query->getSql () );
		$device_found_result = $checkdevice_query->getResult ();
		
		if (empty ( $device_found_result ) && (empty ( $device_token_login ))) {
			$device_token = '';
		} else {
			$device_found = false;
			$device_token = '';
			foreach ( $device_found_result as $device ) {
				$device_token = $device->device_token;
				if ($device->device_id == $device_id) {
					$device_found = true;
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "found device device_id----->" . $device_id );
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "found device device_token----->" . $device_token );
					break;
				}
			}
			if (! $device_found) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "device not found registering $device_type $device_token_login" );
				$device_array = array (
						'registerdevice' => array (
								'user_id' => $user_id,
								'device_id' => $device_id,
								'device_token' => $device_token_login,
								'device_type' => $device_type 
						) 
				);
				$this->exec ( true, json_encode ( $device_array ) );
			}
		}
		return $device_token;
	}
	// end checkDevice
	public function exec($isInternalJSON = false, $internaJSON = '') {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()' );
			if ($isInternalJSON) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()-> internaJSON' . $internaJSON );
				$data = json_decode ( $internaJSON );
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()-> data' . print_r ( $data, true ) );
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()-> _POST [xml]' . $_POST ['xml'] );
				$data = simplexml_load_string ( $_POST ['xml'] );
			}
			
			$user_id = trim ( $data->registerdevice->user_id );
			$device_id = trim ( $data->registerdevice->device_id );
			$device_token = trim ( $data->registerdevice->device_token );
			$device_type = trim ( $data->registerdevice->device_type );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->user_id-->' . $user_id );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->device_id-->' . $device_id );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->device_token-->' . $device_token );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->device_type-->' . $device_type );
			
			$time = time ();
			if (! empty ( $user_id ) && ! empty ( $device_token ) && ! empty ( $device_type )) {
				
				/**
				 * Lookup device and insert/update based on device_id and device_type...
				 */
				$device_sql = "SELECT d FROM Application\Entity\Device d
												where  d.device_id = '$device_id'
												and d.device_type = '$device_type'";
				$device_query = $this->dbAdapter->createQuery ( $device_sql );
				$device_exists = $device_query->getOneOrNullResult ();
				
				/**
				 * Check if user has other devices of same type
				 */
				$user_device_type_sql = "SELECT count(d) FROM Application\Entity\Device d
												where  d.user_id = '$user_id'
												and d.device_type = '$device_type'";
				$user_device_type_query = $this->dbAdapter->createQuery ( $user_device_type_sql );
				$devicetype_count = $user_device_type_query->getSingleScalarResult ();
				
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$devicetype_count---->' . $devicetype_count );
				if ($devicetype_count > 0) {
					$devicetype_lastused_update_sql = "UPDATE Application\Entity\Device d
							SET d.last_used = 0
							WHERE d.user_id = '$user_id'
							AND d.device_type = '$device_type'";
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$devicetype_lastused_update_sql---->' . $devicetype_lastused_update_sql );
					$devicetype_lastused_update_query = $this->dbAdapter->createQuery ( $devicetype_lastused_update_sql );
					$devicetype_lastused_update_result = $devicetype_lastused_update_query->getResult ();
				}
				
				if (! $device_exists) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->inside !$device_exists' );
					try {
						$tblDevice = new \Application\Entity\Device();
						$tblDevice->device_id = $device_id;
						$tblDevice->user_id = $user_id;
						$tblDevice->device_token = $device_token;
						$tblDevice->device_type = $device_type;
						$tblDevice->last_used = '1';
						$tblDevice->create_time = strval($time);
						$tblDevice->update_time = strval($time);
						
						//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$tblDevice', $tblDevice);
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$device_id---->' . $tblDevice->device_id . ' of type --> ' . gettype($tblDevice->device_id));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$device_token---->' . $tblDevice->device_token  . ' of type --> ' . gettype($tblDevice->device_token));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$user_id---->' . $tblDevice->user_id  . ' of type --> ' . gettype($tblDevice->user_id));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$device_type---->' . $tblDevice->device_type  . ' of type --> ' . gettype($tblDevice->device_type));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$last_used---->'. $tblDevice->last_used  . ' of type --> ' . gettype($tblDevice->last_used));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$create_time---->' . $tblDevice->create_time  . ' of type --> ' . gettype($tblDevice->create_time));
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'tblDevice->$update_time---->' . $tblDevice->update_time  . ' of type --> ' . gettype($tblDevice->update_time));
						if ($this->dbAdapter) {
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->inside !$device_exists - $this->dbAdapter is valid' );
						} else {
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->inside !$device_exists - $this->dbAdapter is NOT VALID' );
						}
						$this->dbAdapter->persist ( $tblDevice );
						$this->dbAdapter->flush ();
					} catch(\Doctrine\DBAL\DBALException $e) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, $e->getErrorCode());
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, $e->getMessage());
					}
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->executed insert' );
				} else {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->inside update' );
					
					/**
					 * Update current user login as last used on this device
					 */
					$deviceexists_update_sql = "UPDATE Application\Entity\Device d
					SET d.user_id = '$user_id',
							d.device_token = '$device_token',
							d.last_used = 1,
							d.update_time = '$time'
					WHERE d.device_id = '$device_id'
					AND d.device_type = '$device_type'";
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '$deviceexists_update_sql----->'.$deviceexists_update_sql);
					$deviceexists_update_query = $this->dbAdapter->createQuery ( $deviceexists_update_sql );
					$deviceexists_update_result = $deviceexists_update_query->getResult ();
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, 'registerdevice.exec()->executed update' );
				}
			}
			$status = 'success';
			$message = "device token saved";
			if (! $isInternalJSON) {
				header ( "Content-type: text/xml" );
				$xml_output = '<?xml version="1.0"  encoding="utf-8" ?>';
				$xml_output .= "<xml>";
				$xml_output .= "<registerdeviceresponse>";
				$xml_output .= "<status>$status</status>";
				$xml_output .= "<message>$message</message>";
				$xml_output .= "</registerdeviceresponse>";
				$xml_output .= "</xml>";
				echo $xml_output;
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "RegisterDevice ---> xml_output ----> " . $xml_output );
			}
		} catch ( \Exception $e ) {
			if (! $isInternalJSON) {
				$xml_output = '<?xml version="1.0"  encoding="utf-8" ?>';
				$xml_output .= "<xml>";
				$xml_output .= "<registerdeviceresponse>";
				$xml_output .= "<status>failure</status>";
				$xml_output .= "<message>" . $e->getMessage () . "</message>";
				$xml_output .= "</registerdeviceresponse>";
				$xml_output .= "</xml>";
				echo $xml_output;
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "RegisterDevice ---> xml_output ----> " . $xml_output );
			}
		}
	}
}
?>
