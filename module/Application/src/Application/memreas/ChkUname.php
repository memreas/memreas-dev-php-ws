<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\User;

class ChkUname {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		error_log ( "View ChkUname.xml_input ---->  " . $_POST ['xml'] . PHP_EOL );
		$data = simplexml_load_string ( $_POST ['xml'] );
		// print_r($data);
		
		$username = trim ( $data->checkusername->username );
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		if (isset ( $username ) && ! empty ( $username )) {
			/*
			 * TODO: This query should be cached
			 */
			$query = "SELECT u FROM  Application\Entity\User as u  where u.username = '$username'";
			// $statement = $this->dbAdapter->createStatement($query);
			// $result = $statement->execute();
			// $row = $result->current();
			$statement = $this->dbAdapter->createQuery ( $query );
			$result = $statement->getResult ();
			
			if (! empty ( $result )) {
				$status = 'Success';
				$message = 'Username is taken';
				$isexist = 'Yes';
			} else {
				$status = 'Failure';
				$message = 'Username is not taken';
				$isexist = 'No';
			}
		} else {
			$status = 'Failure';
			$message = 'User name field is Empty';
			$isexist = 'No';
		}
		$xml_output .= "<checkusernameresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "<isexist>$isexist</isexist>";
		$xml_output .= "</checkusernameresponse>";
		$xml_output .= "</xml>";
		
		echo $xml_output;
		error_log ( "View ChkUname.xml_output ---->  " . $xml_output . PHP_EOL );
	}
}

?>
