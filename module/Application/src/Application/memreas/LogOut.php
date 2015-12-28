<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

class LogOut {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		// $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
	}
	public function exec($sessHandler) {
		error_log ( 'IndexController -> logout->exec()...' . PHP_EOL );
		try {
			if (! empty ( $_SESSION ['memreascookie'] )) {
				$sessHandler->closeSessionWithMemreasCookie ();
			} else {
				$sessHandler->closeSessionWithSID ();
			}
			
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<logoutresponse>";
			$xml_output .= "<status>Success</status><message>Loggedout Sucessfully</message>";
			
			$xml_output .= "</logoutresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
			error_log ( "Logut ---> xml_output ----> " . $xml_output . PHP_EOL );
		} catch ( \Exception $e ) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<logoutresponse>";
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>" . $e->getMessage () . "</message>";
			$xml_output .= "</logoutresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
	}
}
?>
