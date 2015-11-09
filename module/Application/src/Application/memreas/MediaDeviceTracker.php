<?php

/**
 * MediaDeviceTracker
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Media;
use \Exception;

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
// <updatemediadownloaded>
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
	
	//
	// exec - purpose is to updated identification info for downloaded media
	// - mainly for ios and android
	//
	public function exec($frmweb = false, $output = '') {
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ , '::enter MediaDeviceTracker->exec()' );
		$error_flag = 0;
		$status = $message = 'failure';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		
		//
		// Set inbound vars - see sample xml
		//
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ , '::enter MediaDeviceTracker->exec()-> setting inbound vars' );
		$media_id = trim ( $data->mediadevicetracker->media_id );
		$user_id = trim ( $data->mediadevicetracker->user_id );
		$device_type = trim ( $data->mediadevicetracker->device_type );
		$device_id = trim ( $data->mediadevicetracker->device_id );
		$device_local_identifier = trim ( $data->mediadevicetracker->device_local_identifier );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ .'::$media_id::',  $media_id);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ .'::$user_id::',  $user_id);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ .'::$device_type::',  $device_type);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ .'::$device_id::',  $device_id);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ .'::$device_local_identifier::',  $device_local_identifier);
		
		//
		// Fetch the db entry
		//
		$query = $this->dbAdapter->createQueryBuilder ();
		$query->select ( "m" )->from ( "\Application\Entity\MediaDevice", "m" )->where ( "m.media_id = '{$media_id}'" )->andWhere ( "m.user_id = '{$user_id}'" );
		$media_on_devices = $query->getQuery ()->getResult ();
		$media_on_devices = $query->getArrayResult ();
		
		
		if ($media_on_devices) {
			$metadata = $media_on_devices[0]['metadata'];
			$devices = json_decode($metadata, true);
			
			foreach ( $devices as $device ) {
				if ($device['device']['device_id'] == $device_id]) {
					$found = true;
					$device['device']['device_type'] = $device_type;
					$device['device']['device_local_identifier'] = $device_local_identifier;
				}
			}
		if ($found) {
			$media_on_devices[0]['metadata'] = json_encode($devices);
			$this->dbAdapter->persist ( $media_on_devices );
			$this->dbAdapter->flush ();
			
			//Set status
			$message = 'updated metadata for media_device';
			$status = 'success';
				
		} else {
			//
			// Insert
			//
			$meta = array ();
			$meta ['devices'] ['device'] ['media_id'] = $media_id;
			$meta ['devices'] ['device'] ['device_id'] = $device_id;
			$meta ['devices'] ['device'] ['device_type'] = $device_type;
			$meta ['devices'] ['device'] ['device_local_identifier'] = $device_local_identifier;
				
			$now = date ( 'Y-m-d H:i:s' );
			$tblMediaDevice = new \Application\Entity\MediaDevice ();
			$tblMediaDevice->media_id = $media_id;
			$tblMediaDevice->user_id = $user_id;
			$tblMediaDevice->metadata = json_encode($meta);
			$tblMediaDevice->create_date = $now;
			$tblMediaDevice->update_date = $now;
			$this->dbAdapter->persist ( $tblMediaDevice );
			$this->dbAdapter->flush();

			//Set status
			$message = 'inserted metadata for media_device';
			$status = 'success';
		}
			
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
		$xml_output .= "</mediadevicetrackerresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
