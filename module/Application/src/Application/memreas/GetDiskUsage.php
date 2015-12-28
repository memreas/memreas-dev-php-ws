<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;

class GetDiskUsage {
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
		$user_id = trim ( $data->getdiskusage->user_id );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getdiskusageresponse>";
		$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
		
		if (empty ( $userOBj )) {
			$xml_output .= "<status>Failure</status>";
			$xml_output .= "<message>No Record Found </message>";
		} else {
			$xml_output .= "<status>Success</status>";
			$aws = new AWSManagerSender ( $this->service_locator );
			$client = $aws->s3;
			$bucket = 'memreasdevsec';
			$total_used = 0.0;
			// $user_id="c96f0282-8f3a-414b-bd7a-ead57b1bfa4e";
			
			$iterator = $client->getIterator ( 'ListObjects', array (
					'Bucket' => $bucket,
					'Prefix' => $user_id 
			) );
			
			foreach ( $iterator as $object ) {
				$total_used = bcadd ( $total_used, $object ['Size'] );
			}
		}
		
		if ($total_used > 0) {
			$total_used = $total_used / 1024 / 1024 / 1024;
		}
		
		$xml_output .= "<total_used>$total_used GB</total_used>";
		$xml_output .= "</getdiskusageresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		error_log ( "getdisusage ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
