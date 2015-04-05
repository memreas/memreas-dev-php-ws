<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

class gcm {
	protected $service_locator;
	protected $dbAdapter;
	protected $messages;
	protected $device_token='';
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
	}
	public function addDevice($device_token) {
		$this->device_token [] = $device_token;
	}
	public function getDeviceCount() {
		return count ( $this->device_token );
	}
	public function sendpush($message = '', $type = '', $event_id = '', $media_id = '') { // Message to be sent
		$url = 'https://android.googleapis.com/gcm/send';
		
		$fields = array (
				'registration_ids' => $this->device_token,
				'data' => array (
						"message" => $message,
						'type' => $type,
						'event_id' => $event_id,
						'media_id' => $media_id 
				) 
		);
		
		$headers = array (
				// memreas test key
				'Authorization: key='.MemreasConstants::GCM_SERVER_KEY,				
				'Content-Type: application/json' 
		);
		
		// Open connection
		$ch = curl_init ();
		
		// Set the url, number of POST vars, POST data
		curl_setopt ( $ch, CURLOPT_URL, $url );
		
		curl_setopt ( $ch, CURLOPT_POST, true );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode ( $fields ) );
		
		// Execute post
		$result = curl_exec ( $ch );
		
		// Close connection
		curl_close ( $ch );
		
		return $result;
	}
}
?>
