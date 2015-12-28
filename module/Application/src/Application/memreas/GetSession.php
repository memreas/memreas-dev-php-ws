<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

class GetSession {
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
		$data = simplexml_load_string ( $_POST ['xml'] );
		$sid = trim ( $data->getsession->sid );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getsessionresponse>";
		if (! empty ( $sid )) {
			
			$xml_output .= "<username>" . $_SESSION ['user'] ['username'] . "</username>";
			$xml_output .= "<userid>" . $_SESSION ['user'] ['user_id'] . "</userid>";
		}
		$xml_output .= "</getsessionresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		error_log ( "getsession ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
