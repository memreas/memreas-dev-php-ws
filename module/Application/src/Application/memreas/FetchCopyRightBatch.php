<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;
use Application\memreas\Mlog;
use Application\Model\MemreasConstants;

class FetchCopyRightBatch {
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec() {
		$copyright_id = '';
		$copyright_id_md5 = '';
		$copyright_batch_json = '';
		try {
			$data = simplexml_load_string ( $_POST ['xml'] );
			
			$status = 'failure';
			// Mlog::addone ( '$_SESSION [user_id];', $_SESSION ['user_id']; );
			//
			// Check if batch is available
			//
			$query_event = "select c
			     from Application\Entity\CopyrightBatch c
			     where c.user_id = '" . $_SESSION ['user_id'] . "'
			     and c.remaining > 0
			     ORDER BY c.create_date DESC";
			$statement = $this->dbAdapter->createQuery ( $query_event );
			$result = $statement->getResult ( (\Doctrine\ORM\Query::HYDRATE_ARRAY) );
			if (! empty ( $result )) {
				$copyright_batch_json = $result [0] ['metadata'];
				$remaining = $result [0] ['remaining'];
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::remaining::', $remaining );
				if ($remaining < MemreasConstants::copyright_batch_minimum) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::$this->createNewBatch($remaining)' );
					$this->createNewBatch ( MemreasConstants::copyright_batch_create_count );
				}
				$status = 'success';
			} else {
				$remaining = MemreasConstants::copyright_batch_create_count;
				$copyright_batch_json = $this->createNewBatch ( $remaining );
				$status = 'success';
			}
		} catch ( Exception $e ) {
			Mlog::addone ( 'Caught exception: ', $e->getMessage () );
		}
		
		// Mlog::addone ( 'for db ---> $copyright_batch_json::',
		// $copyright_batch_json );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<fetchcopyrightbatchresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<remaining>$remaining</remaining>";
		$xml_output .= "<copyright_batch>" . '<![CDATA[' . $copyright_batch_json . ']]>' . "</copyright_batch>";
		$xml_output .= "</fetchcopyrightbatchresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		//error_log ( "fetchcopyrightbatchresponse ---> " . $xml_output . PHP_EOL );
	}
	public function createNewBatch($count) {
		$copyright_batch = [ ];
		$id_count = $count;
		$copyright_batch_id = MUUID::fetchUUID ();
		for($i = 0; $i < $id_count; $i ++) {
			$media_id = MUUID::fetchUUID ();
			$copyright_id = MUUID::fetchUUID ();
			$copyright_id_md5 = md5 ( $copyright_id );
			$copyright_id_sha1 = sha1 ( $copyright_id );
			$copyright_id_sha256 = hash ( 'sha256', $copyright_id );
			
			// array entry
			$copyright = [ ];
			$copyright ['copyright_batch_id'] = $copyright_batch_id;
			$copyright ['copyright_id'] = $copyright_id;
			$copyright ['media_id'] = $media_id;
			$copyright ['copyright_id_md5'] = $copyright_id_md5;
			$copyright ['copyright_id_sha1'] = $copyright_id_sha1;
			$copyright ['copyright_id_sha256'] = $copyright_id_sha256;
			$copyright ['used'] = 0;
			
			$copyright_batch [] = $copyright;
		}
		$copyright_batch_json = json_encode ( $copyright_batch );
		
		$now = date ( 'Y-m-d H:i:s' );
		$tblCopyrightBatch = new \Application\Entity\CopyrightBatch ();
		$tblCopyrightBatch->copyright_batch_id = $copyright_batch_id;
		$tblCopyrightBatch->user_id = $_SESSION ['user_id'];
		$tblCopyrightBatch->metadata = $copyright_batch_json;
		$tblCopyrightBatch->remaining = $id_count;
		$tblCopyrightBatch->create_date = $now;
		$tblCopyrightBatch->update_time = $now;
		$this->dbAdapter->persist ( $tblCopyrightBatch );
		$this->dbAdapter->flush ();
		$remaining = $id_count;
		
		//Mlog::addone ( __CLASS__ . __METHOD__ . '$copyright_batch_json--->', $copyright_batch_json );
		return $copyright_batch_json;
	}
}
?>
