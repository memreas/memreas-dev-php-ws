<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

class gcm {
	protected $service_locator;
	protected $dbAdapter;
	protected $messages;
	protected $device_tokens;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
	}
	public function addDevice($device_token) {
		$this->device_tokens [] = $device_token;
	}
	public function getDeviceCount() {
		return count ( $this->device_tokens );
	}
	public function sendpush($message = '', $type = '', $event_id = '', $media_id = '', $user_id = '') { // Message to be sent
		$url = 'https://android.googleapis.com/gcm/send';
		
		$fields = array (
				'registration_ids' => $this->device_tokens,
				'data' => array (
						"message" => $message,
						'type' => $type,
						'event_id' => $event_id,
						'media_id' => $media_id ,
						'user_id' => $user_id						
				) 
		);
error_log("gcm fields ---> ".json_encode($fields).PHP_EOL);		
		$headers = array (
				// memreas key
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
error_log("result ---> ".$result.PHP_EOL);
		
		return $result;
	}
}
?>
