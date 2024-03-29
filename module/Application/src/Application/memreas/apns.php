<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;

class apns {
	protected $service_locator;
	protected $dbAdapter;
	protected $device_token = array ();
	public function __construct() {
		// $this->dbAdapter = $service_locator->get(MEMREASDB);
		// $this->service_locator = $service_locator;
		// $this->dbAdapter = $this->service_locator->get('doctrine.entitymanager.orm_default');
	}
	public function addDevice($device_token) {
		$this->device_token [] = $device_token;
	}
	public function getDeviceCount() {
		return count ( $this->device_token );
	}
	public function sendpush($message = '', $type = '', $event_id = '', $media_id = '') { 
		// Setup the payload
		$payload ['aps'] = array (
				'badge' => 1,
				'alert' => $message,
				'sound' => 'default' 
		);
		$payload ['event_id'] = $event_id;
		$payload ['type'] = $type;
		if (! empty ( $media_id )) {
			$payload ['media_id'] = $media_id;
		}
		
		// json_encode it
		$payload = json_encode ( $payload );
		
		// setup the context
		$ctx = stream_context_create ();
		stream_context_set_option ( $ctx, 'ssl', 'local_cert', getcwd () . '/key/' . MemreasConstants::APNS );
		stream_context_set_option ( $ctx, 'ssl', 'passphrase', '' );
		$fp = stream_socket_client ( MemreasConstants::APNS_GATEWAY, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx );
		
		// check if the connection is valid
		if (!$fp) {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "APNS Notifications FAILURE!" );
			return;
		} else {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, "APNS Notifications sent!" );
		}
		
		// Pass device key
		foreach ( $this->device_token as $deviceToken ) {
			// binary encode the message
			$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
			
			// Send it to the server
			$result = fwrite ( $fp, $msg, strlen ( $msg ) );
		}
		$result = fclose ( $fp );
		return $result;
	}
}
?>
