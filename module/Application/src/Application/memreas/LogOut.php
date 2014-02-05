<?php

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
	public function exec() {
		$auth = $this->service_locator->get ( 'AuthService' );
		$auth->clearIdentity ();
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<logoutresponse>";
		$xml_output .= "<status>Sucess</status><message>Logedout Sucessfully</message>";
		
		$xml_output .= "</logoutresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		error_log ( "Logut ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
