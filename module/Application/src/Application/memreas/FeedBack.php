<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use \Exception;

class FeedBack {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$stausMessage = $feedback_id = '';
		$name = trim ( $data->feedback->name );
		$user_id = trim ( $data->feedback->user_id );
		
		$email = trim ( $data->feedback->email );
		$feedBackMessage = trim ( $data->feedback->message );
		$time = time ();
		$message = '';
		
		if (empty ( $user_id )) {
			$message = 'User Not Found';
			$status = 'Failure';
		} else if (! $this->is_valid_email ( $email )) {
			$message .= 'Please enter valid email address. ';
			$status = 'Failure';
		} else if (empty ( $feedBackMessage )) {
			$message .= 'Message is empty ';
			$status = 'Failure';
		} 

		else {
			// add FeedBack
			$feedback_id = MUUID::fetchUUID ();
			$tblFeedBack = new \Application\Entity\FeedBack ();
			$tblFeedBack->feedback_id = $feedback_id;
			$tblFeedBack->user_id = $user_id;
			
			$tblFeedBack->name = $name;
			$tblFeedBack->email = $email;
			$tblFeedBack->create_time = $time;
			$tblFeedBack->message = $feedBackMessage;
			
			$this->dbAdapter->persist ( $tblFeedBack );
			$this->dbAdapter->flush ();
			$message .= 'Feedback saved ';
			$status = 'success';
		}
		
		if (empty ( $frmweb )) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<feedbackresult>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>" . $message . "</message>";
			$xml_output .= "<feedback_id>$feedback_id</feedback_id>";
			
			$xml_output .= "</feedbackresult>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}
	public function is_valid_email($email) {
		$result = TRUE;
		if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
			$result = FALSE;
		}
		return $result;
	}
}

?>
