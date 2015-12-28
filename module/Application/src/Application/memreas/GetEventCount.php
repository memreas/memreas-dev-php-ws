<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

class GetEventCount {
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
		$event_id = trim ( $data->geteventcount->event_id );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<geteventcountresponse>";
		$q1 = "select e.event_id, e.location from Application\Entity\Event e where e.event_id=:event_id";
		$statement = $this->dbAdapter->createQuery ( $q1 );
		$statement->setParameter ( 'event_id', $event_id );
		$event = $statement->getOneOrNullResult ();
		
		// get like count
		$likeCountSql = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like=1" );
		$likeCountSql->setParameter ( 1, $event_id );
		$likeCount = $likeCountSql->getSingleScalarResult ();
		
		// get comment count for event
		$commCountSql = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND  (c.type='text' or c.type='audio')" );
		$commCountSql->setParameter ( 1, $event_id );
		$commCount = $commCountSql->getSingleScalarResult ();
		
		if (empty ( $event_id ) || empty ( $event )) {
			$xml_output .= "<status>Failure</status>";
			$xml_output .= "<message>No Record Found </message>";
		} else {
			$xml_output .= "<status>Success</status>";
			$xml_output .= "<event_id>" . $event ['event_id'] . "</event_id>";
			$xml_output .= "<comment_count>" . $commCount . "</comment_count>";
			$xml_output .= "<like_count>" . $likeCount . "</like_count>";
		}
		$xml_output .= "</geteventcountresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		error_log ( "geteventcount ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
