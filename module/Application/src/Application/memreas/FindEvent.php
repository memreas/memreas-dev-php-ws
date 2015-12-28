<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use \Exception;

class FindEvent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$message = '';
		$tag = (trim ( $data->findevent->tag ));
		$time = time ();
		
		//
		$mc = $this->elasticache->getCache ( '!event' );
		$search_result = array ();
		foreach ( $mc as $er ) {
			if (strpos ( $er ['name'], $search ) !== false) {
				$er ['name'] = '!' . $er ['name'];
				$search_result [] = $er;
			}
		}
		
		$result ['count'] = count ( $search_result );
		$result ['search'] = $search_result;
		
		// $result = preg_grep("/$search/", $mc);
		// echo '<pre>';print_r($result);
		
		echo json_encode ( $result );
		
		if (! $tblTag) {
			$tag_id = '';
			$meta = '';
			$status = "failure";
			$message = "No result found";
		} else {
			
			$status = "Sucess";
			$message = "Notification Updated";
		}
		
		if (empty ( $frmweb )) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<tagresult>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<tag>" . $message . "</tag>";
			$xml_output .= "<tag_id>$tag_id</tag_id>";
			$xml_output .= "<meta>$meta</meta>";
			
			$xml_output .= "</tagresult>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}
}

?>
