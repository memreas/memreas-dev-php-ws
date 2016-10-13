<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;

class GetDiskUsage {
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec($user_id = '', $stringOnly = false) {
		if (isset ( $_POST ['xml'] )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
			$user_id = trim ( $data->getdiskusage->user_id );
		} else {
			// assume $user_id is set (for stripe subscription)
		}
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
			
			/*
			 * -
			 * memreasdevsec or memreasprodsec
			 */
			$total_used = 0.0;
			$iterator = $client->getIterator ( 'ListObjects', array (
					'Bucket' => MemreasConstants::S3BUCKET,
					'Prefix' => $user_id 
			) );
			
			foreach ( $iterator as $object ) {
				if (isset ( $object ['Size'] ) && ! empty ( $object ['Size'] )) {
					// $total_used = \bcadd ( $total_used, $object ['Size'] );
					$total_used += $object ['Size'];
				}
			}
			
			/*
			 * -
			 * memreasdevhlssec or memreasprodhlssec
			 */
			$iterator = $client->getIterator ( 'ListObjects', array (
					'Bucket' => MemreasConstants::S3HLSBUCKET,
					'Prefix' => $user_id 
			) );
			
			foreach ( $iterator as $object ) {
				if (isset ( $object ['Size'] ) && ! empty ( $object ['Size'] )) {
					// $total_used = \bcadd ( $total_used, $object ['Size'] );
					$total_used += $object ['Size'];
				}
			}
		}
		
		if ($total_used > 0) {
			$total_used = $total_used / 1024 / 1024 / 1024;
		}
		
		$xml_output .= "<total_used>$total_used GB</total_used>";
		$xml_output .= "</getdiskusageresponse>";
		$xml_output .= "</xml>";
		if (! $stringOnly) {
			// for admin
			echo $xml_output;
		} else {
			// for stripe
			return $total_used;
		}
		error_log ( "getdisusage ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
