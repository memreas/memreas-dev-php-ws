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
use Application\Entity\EventMedia;

class RemoveEventMedia {
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
		error_log ( "Inside RemoveEventMedia _POST ['xml'] ---> " . $_POST ['xml'] . PHP_EOL );
		$data = simplexml_load_string ( $_POST ['xml'] );
		$media_ids = $data->removeeventmedia->media_ids->media_id;
		$event_id = $data->removeeventmedia->event_id;
		
		if (! empty ( $media_ids )) {
			$mediaList = array ();
			foreach ( $media_ids as $media_id )
				$mediaList [] = "'" . $media_id . "'";
			
			$mediaList = implode ( ', ', $mediaList );
			
			$query_event = "DELETE FROM Application\Entity\EventMedia em WHERE em.media_id IN ({$mediaList}) AND em.event_id = '{$event_id}'";
			$event_statement = $this->dbAdapter->createQuery ( $query_event );
			$event_result = $event_statement->getResult ();
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<removeeventmediaresponse>";
		$xml_output .= "<status>Success</status>";
		$xml_output .= "<message>Event media removed</message>";
		$xml_output .= "</removeeventmediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}
?>
