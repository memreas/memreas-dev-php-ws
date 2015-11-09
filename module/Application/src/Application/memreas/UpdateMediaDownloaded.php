<?php

/*
 * Update media detail
 * @params: null
 * @Return
 * @Tran Tuan
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Media;
use \Exception;

// Sample xml
// <xml>
// <updatemediadownloaded>
// <media>
// <media_id></media_id>
// <device_id></device_id>
// <device_type></device_type>
// <device_local_identifier></device_local_identifier>
// </media>
// <updatemediadownloaded>
// <xml>
class UpdateMediaDownloaded {
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
		$error_flag = 0;
		$status = $message = 'failure';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$media_arr = trim ( $data->updatemedia->media->count () );
		
		if ($data->updatemediadownloaded->media->count ()) {
			foreach ( $data->updatemediadownloaded->media as $media ) {
				
				//
				// Set inbound vars - see sample xml
				//
				$media_id = trim ( $media->media_id );
				$device_type = trim ( $media->device_type );
				$device_id = trim ( $media->device_id );
				$device_local_identifier = trim ( $media->device_local_identifier );
				
				//
				// Fetch the db entry
				//
				$query = $this->dbAdapter->createQueryBuilder ();
				$query->select ( "m" )->from ( "\Application\Entity\Media", "m" )->where ( "m.media_id = '{$media_id}'" );
				$media = $query->getQuery ()->getResult ();
				
				//
				// Update
				//
				if (empty ( $media )) {
					$message = 'media does not exist';
					$status = 'failure';
				} else {
					$metadata = $media [0]->metadata;
					$metadata = json_decode ( $metadata, true );
					
					//
					// Correct existing meta if using device not devices
					//
					if (isset ( $metadata ["S3_files"] ["device"] )) {
						$temp = $metadata ["S3_files"] ["device"];
						unset ( $metadata ["S3_files"] ["device"] );
						$metadata ["S3_files"] ["devices"] ["device"] = $temp;
					}
					
					//
					// Check for devices
					//
					if (isset ( $metadata ["S3_files"] ["devices"] )) {
						$devices = $metadata ["S3_files"] ["devices"];
						$found = false;
						foreach ( $devices as $device ) {
							// if wrong type continue
							if (($device ['device_id'] == $device_id) && ($device ['device_type'] == $device_type)) {
								//
								// Found the device entry
								//
								$device ['device_local_identifier'] = $device_local_identifier;
								$found = true;
							} else {
								continue;
							}
							// if wrong type continue
							if ($device ['device_id'] != $device_id) {
								continue;
							}
						}
					}
					
					//
					// if not found then add a new device
					//
					if (! $found) {
						$devices ['device'] ['device_id'] = $device_id;
						$devices ['device'] ['device_type'] = $device_type;
						$devices ['device'] ['device_local_identifier'] = $device_local_identifier;
						$devices ['device'] ['origin'] = 0;
					}
					$metadata ["S3_files"] ["devices"] = $devices;
					
					$media->metadata = $metadata;
					$this->dbAdapter->persist ( $media );
					$this->dbAdapter->flush ();
					
					$message = 'updated metadata for media downloaded';
					$status = 'success';
				}
			}
			$output .= '<media_id>' . $media_id . '</media_id>';
			$output .= '<device_id>' . $device_id . '</device_id>';
			$output .= '<device_type>' . $device_type . '</device_type>';
			$output .= '<device_local_identifier>' . $device_local_identifier . '</device_local_identifier>';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<updatemediaresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</updatemediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
