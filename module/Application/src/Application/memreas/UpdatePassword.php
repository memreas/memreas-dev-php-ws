<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\AWSManagerSender;
use Zend\View\Model\ViewModel;
use \Exception;

class UpdatePassword {
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
	public function exec($frmweb = false, $output = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$user_id = trim ( $data->updatepassword->user_id );
		$password = trim ( $data->updatepassword->old );
		$new = trim ( $data->updatepassword->new );
		$retype = trim ( $data->updatepassword->retype );
		$status = '';
		$message = '';
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<updatepasswordresponse>";
		if ($new != $retype) {
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>New Password and Verify password did not match </message>";
		}
		
		$sql = "SELECT u FROM Application\Entity\User as u where u.user_id = '" . $user_id . "' and u.password = '" . md5 ( $password ) . "'";
		$statement = $this->dbAdapter->createQuery ( $sql );
		$userInfo = $statement->getOneOrNullResult ();
		
		if (count ( $userInfo ) > 0) {
			$pass = md5 ( $new );
			$updatequr = "UPDATE Application\Entity\User u  SET u.password ='" . $pass . "' WHERE u.user_id='" . $user_id . "'";
			$statement = $this->dbAdapter->createQuery ( $updatequr );
			$resofupd = $statement->getResult ();
			$xml_output .= '<status>success</status>';
			$xml_output .= '<message>password changed successfully</message>';
		} else {
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>Current password is invalid</message>";
		}
		
		$xml_output .= "</updatepasswordresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
