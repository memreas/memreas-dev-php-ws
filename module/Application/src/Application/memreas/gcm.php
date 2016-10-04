<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

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
	public function sendpush($message = '', $type = '', $event_id = '', $media_id = '', $user_id = '') {
		
		//
		// Firebase sample message
		//
		/*
		 * {
		 * "to" : "bk3RNwTe3H0:CI2k_HHwgIpoDKCIZvvDMExUdFQ3P1...",
		 * "data" : {
		 * "Nick" : "Mario",
		 * "body" : "great match!",
		 * "Room" : "PortugalVSDenmark"
		 * },
		 * }
		 */
		
		$fields = array (
				'to' => $this->device_tokens,
				'data' => array (
						"message" => $message,
						'type' => $type,
						'event_id' => $event_id,
						'media_id' => $media_id,
						'user_id' => $user_id
				)
		);
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "gcm fields ---> " . json_encode ( $fields ) );
		$headers = array (
				// memreas key
				'Authorization: key=' . MemreasConstants::GCM_SERVER_KEY,
				'Content-Type: application/json'
		);
		
		// Open connection
		$ch = curl_init ();
		
		// Set the url, number of POST vars, POST data
		curl_setopt ( $ch, CURLOPT_URL, MemreasConstants::FCM_SERVER_URL );
		curl_setopt ( $ch, CURLOPT_POST, true );
		curl_setopt ( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, json_encode ( $fields ) );
		
		// Execute post
		$result = curl_exec ( $ch );
		
		// Close connection
		curl_close ( $ch );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "gcm result ---> " . $result );
		
		return $result;
	}
}
?>
