<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use \Exception;

class FindTag {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
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
		$tag = (trim ( $data->findtag->tag ));
		$time = time ();
		
		//  
		$tblTag = $this->dbAdapter->find ( "\Application\Entity\Tag", $tag );
		
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
