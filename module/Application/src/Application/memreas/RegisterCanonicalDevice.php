<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;

class RegisterCanonicalDevice {
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
	public function exec() {
		try {
			$data = simplexml_load_string ( $_POST ['xml'] );
			
			$device_id = trim ( $data->registercanonicaldevice->device_id );
			$canonical_device_token = trim ( $data->registercanonicaldevice->canonical_device_token );
			$device_type = trim ( $data->registercanonicaldevice->device_type );
			// error_log ( 'registercanonicaldevice.exec()->device_id' . $device_id . PHP_EOL );
			// error_log ( 'registercanonicaldevice.exec()->canonical_device_token' . $canonical_device_token . PHP_EOL );
			// error_log ( 'registercanonicaldevice.exec()->device_type' . $device_type . PHP_EOL );
			
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registercanonicaldeviceresponse>";
			$time = time ();
			if (! empty ( $canonical_device_token ) && ! empty ( $device_type )) {
				
				// device exists so update data based on last login/registration...
				$qb = $this->dbAdapter->createQueryBuilder ();
				$q = $qb->update ( '\Application\Entity\Device', 'd' )->set ( 'd.device_token', $qb->expr ()->literal ( $canonical_device_token ) )->set ( 'd.update_time', $qb->expr ()->literal ( $time ) )->where ( 'd.device_id = ?1 AND d.device_type = ?2' )->setParameter ( 1, $device_id )->setParameter ( 2, $device_type )->getQuery ();
				$p = $q->execute ();
				// error_log ( 'registercanonicaldevice.exec()->executed updated' . PHP_EOL );
				
				$status = 'success';
				$message = "device token saved";
			} else {
				$status = 'failure';
				$message = "";
			}
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= "</registercanonicaldeviceresponse>";
			$xml_output .= "</xml>";
		} catch ( \Exception $e ) {
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registercanonicaldeviceresponse>";
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>" . $e->getMessage () . "</message>";
			$xml_output .= "</registercanonicaldeviceresponse>";
			$xml_output .= "</xml>";
			/*
			 * Send email to admin if canonical update fails...
			 */
			Email::$item ['type'] = Email::ADMIN_ERROR_OCCURRED;
			Email::$item ['email'] = MemreasConstants::ADMIN_EMAIL;
			Email::$item ['class'] = 'RegisterCanonicalDevice';
			Email::$item ['message'] = $e->getMessage ();
			Email::collect ();
			Email::sendmail ( $this->service_locator );
		}
		
		echo $xml_output;
		error_log ( "getsession ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
