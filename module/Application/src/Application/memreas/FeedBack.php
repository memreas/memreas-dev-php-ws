<?php

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
				$name = trim ( $data->feedback->user_id );

		$email = trim ( $data->feedback->email );
		$message_ = trim ( $data->feedback->message );
		$time = time ();
		
		if (empty($user_id) || empty($name)) {
    		$message .= 'User Not Found';
    		$status = 'Failure';
		}else if (!is_valid_email($email)) {
    		$message .= 'Please enter valid email address. ';
    		$status = 'Failure';
		}else if(empty($message)){
			$message .= 'Message is empty ';
    		$status = 'Failure';
		} 

		else {
			// add  FeedBack
			$feedback_id = MUUID::fetchUUID ();
			$tblFeedBack = new \Application\Entity\FeedBack();
			$tblFeedBack->name = $name;
			$tblFeedBack->email = $message;
			$tblFeedBack->tag_type = $tag_type;
			$tblFeedBack->create_time = $time;
			
			$tblTag->update_time = $time;
			$tblTag->meta = $meta;
			$this->dbAdapter->persist ( $tblTag );
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
			$xml_output .= "<meta>$meta</meta>";
			
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
