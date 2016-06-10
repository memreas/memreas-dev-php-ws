<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
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
	public function sendpush($message = '', $type = '', $event_id = '', $media_id = '') { // Message to be sent
		$payload ['aps'] = array (
				'alert' => $message,
				'badge' => '1',
				'sound' => 'default' 
		);
		$payload ['event_id'] = $event_id;
		$payload ['type'] = $type;
		if (! empty ( $media_id )) {
			$payload ['media_id'] = $media_id;
		}
		$payload = json_encode ( $payload );
		
		$ctx = stream_context_create ();
		stream_context_set_option ( $ctx, 'ssl', 'local_cert', getcwd () . '/key/' . MemreasConstants::APNS );
		//stream_context_set_option ( $ctx, 'ssl', 'local_cert', getcwd () . '/key/memreas_apns.pem' );
		stream_context_set_option ( $ctx, 'ssl', 'passphrase', 'nopass' );
		//$fp = stream_socket_client('ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
		$fp = stream_socket_client(MemreasConstants::APNS_GATEWAY, $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
		
		//$fp = stream_socket_client ( 'ssl://gateway.sandbox.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx );
		
		if (! $fp) {
			// print "Failed to connect $err $errstr";
			return;
		} else {
			// print "Notifications sent!";
		}
		// Pass device key
		foreach ( $this->device_token as $deviceToken ) {
			$msg = chr ( 0 ) . pack ( 'n', 32 ) . pack ( 'H*', $deviceToken ) . pack ( 'n', strlen ( $payload ) ) . $payload;
			
			// Send it to the server
			$result = fwrite ( $fp, $msg, strlen ( $msg ) );
		}
		fclose ( $fp );
		return $result;
	}
}
?>
