<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
/**
 * MediaDeviceTracker
 */
namespace Application\memreas;

use \Exception;
use Application\Entity\Media;
use Application\Entity\MediaDevice;

// Sample xml
// <xml>
// <mediadevicetracker>
// <media>
// <media_id></media_id>
// <user_id></user_id>
// <device_id></device_id>
// <device_type></device_type>
// <device_local_identifier></device_local_identifier>
// </media>
// <mediadevicetracker>
// <xml>
class MediaDeviceTracker {
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
	
	/**
	 * Purpose: provide json back of user's devices and related media
	 * - used to check by device is media is syncd
	 *
	 * @param user_id $user_id        	
	 */
	public function fetchMediaDeviceMediaByUserId($user_id) {
		try {
			$query = "SELECT md
        			from Application\Entity\MediaDevice md, Application\Entity\Media m
        			WHERE md.user_id = m.user_id and md.media_id = m.media_id and m.delete_flag != 1 and m.transcode_status = 'success' and m.user_id = '$user_id'";
			//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$query::', $query );
			$statement = $this->dbAdapter->createQuery ( $query );
			$mediaDevicesForUser = $statement->getArrayResult ();
			//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$mediaDevicesForUser::', json_encode ( $mediaDevicesForUser ) );
		} catch ( QueryException $ex ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$ex::', $ex->getMessage () );
		}
		
		return $mediaDevicesForUser;
	}
	
	//
	// exec - purpose is to updated identification info for downloaded media
	// - mainly for ios and android
	//
	public function exec($data = null) {
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::enter MediaDeviceTracker->exec()' );
		$error_flag = 0;
		$status = $message = 'failure';
		if (empty ( $data )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
			//
			// Set inbound vars - see sample xml
			//
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::enter MediaDeviceTracker->exec()-> setting inbound vars' );
			$media_id = trim ( $data->mediadevicetracker->media_id );
			$user_id = trim ( $data->mediadevicetracker->user_id );
			$device_type = trim ( $data->mediadevicetracker->device_type );
			$device_id = trim ( $data->mediadevicetracker->device_id );
			$device_local_identifier = trim ( $data->mediadevicetracker->device_local_identifier );
			$task_identifier = trim ( $data->mediadevicetracker->task_identifier );
		} else {
			$media_id = $data ['media_id'];
			$user_id = $data ['user_id'];
			$device_type = $data ['device_type'];
			$device_id = $data ['device_id'];
			$device_local_identifier = $data ['device_local_identifier'];
			$task_identifier = $data ['task_identifier'];
		}
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$_POST [xml]::', $_POST ['xml'] );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$media_id::', $media_id );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$user_id::', $user_id );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$device_type::', $device_type );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$device_id::', $device_id );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$device_local_identifier::', $device_local_identifier );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$task_identifier::', $task_identifier );
		
		//
		// Fetch the db entry
		//
		try {
			$query = "SELECT md
        			from Application\Entity\MediaDevice md, Application\Entity\Media m
        			WHERE md.user_id = m.user_id and md.media_id = m.media_id and m.delete_flag != 1 and m.transcode_status = 'success' and m.user_id = '$user_id'";
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$query::', $query );
			$statement = $this->dbAdapter->createQuery ( $query );
			$media_on_devices = $statement->getArrayResult ();
		} catch ( QueryException $ex ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$ex::', $ex->getMessage () );
		}
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$media_on_devices::', $media_on_devices );
		
		//
		// parse results...
		//
		$found = false;
		$now = date ( 'Y-m-d H:i:s' );
		if ($media_on_devices) {
			$metadata = $media_on_devices [0] ['metadata'];
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::found $metadata::', $metadata );
			$devices = json_decode ( $metadata, true );
			//
			// Check if media was downloaded to device
			//
			foreach ( $devices as $device ) {
				// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::found $device[device][device_id]::', $device ['device_id'] );
				if ($device ['device_id'] == $device_id) {
					$found = true;
					$device ['device_type'] = $device_type;
					$device ['device_local_identifier'] = $device_local_identifier;
					// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::setting $device_local_identifier::', $device_local_identifier );
				}
			}
			if ($found) {
				//
				// Update existing...
				//
				try {
					// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::found $device about to update::', '***' );
					$json = json_encode ( $devices );
					$updateMediaDeviceQuery = "UPDATE Application\Entity\MediaDevice md " . " SET md.metadata = '{$json}'" . " , md.update_date ='{$now}'" . " WHERE md.media_id='{$media_id}' " . " AND md.user_id='{$user_id}'";
					$statement = $this->dbAdapter->createQuery ( $updateMediaDeviceQuery );
					$result = $statement->getResult ();
					// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::found $device about to updated!::', '***' );
					
					// Set status
					$message = 'updated metadata for media_device';
					$status = 'success';
				} catch ( \Exception $e ) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$e->getMessage()::', $e->getMessage () );
				}
			} else {
				//
				// Not found but db has enty so add to meta
				//
				$meta = array ();
				$meta ['media_id'] = $media_id;
				$meta ['device_id'] = $device_id;
				$meta ['device_type'] = $device_type;
				$meta ['device_local_identifier'] = $device_local_identifier;
				$devices [] = $meta;
				
				$json = json_encode ( $devices );
				$updateMediaDeviceQuery = "UPDATE Application\Entity\MediaDevice md " . " SET md.metadata = '{$json}'" . " , md.update_date ='{$now}'" . " WHERE md.media_id='{$media_id}' " . " AND md.user_id='{$user_id}'";
				$statement = $this->dbAdapter->createQuery ( $updateMediaDeviceQuery );
				$result = $statement->getResult ();
				// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::found $device about to updated!::', '***' );
				
				// Set status
				$message = 'updated metadata for media_device';
				$status = 'success';
			}
		} else {
			//
			// Insert no entry in db for this user
			//
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::!found $device about to insert::', '****' );
			$meta = array ();
			$meta ['media_id'] = $media_id;
			$meta ['device_id'] = $device_id;
			$meta ['device_type'] = $device_type;
			$meta ['device_local_identifier'] = $device_local_identifier;
			$devices = array ();
			$devices [] = $meta;
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$devices::', json_encode ( $devices ) );
			
			$tblMediaDevice = new \Application\Entity\MediaDevice ();
			$tblMediaDevice->media_id = $media_id;
			$tblMediaDevice->user_id = $user_id;
			$tblMediaDevice->metadata = json_encode ( $devices );
			$tblMediaDevice->create_date = $now;
			$tblMediaDevice->update_date = $now;
			$this->dbAdapter->persist ( $tblMediaDevice );
			$this->dbAdapter->flush ();
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::!found $device about to inserted!::', '***' );
			
			// Set status
			$message = 'inserted metadata for media_device';
			$status = 'success';
		}
		// end if ($media_on_devices)
		
		//
		// Response
		//
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<mediadevicetrackerresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		$xml_output .= "<message>{$message}</message>";
		$xml_output .= '<media_id>' . $media_id . '</media_id>';
		$xml_output .= '<device_id>' . $device_id . '</device_id>';
		$xml_output .= '<device_type>' . $device_type . '</device_type>';
		$xml_output .= '<device_local_identifier>' . $device_local_identifier . '</device_local_identifier>';
		$xml_output .= '<task_identifier>' . $task_identifier . '</task_identifier>';
		$xml_output .= "</mediadevicetrackerresponse>";
		$xml_output .= "</xml>";
		echo trim ( $xml_output );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$xml_output::', '*' . $xml_output . '*' );
	}
}

?>
