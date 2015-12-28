<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas\StripeWS;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Guzzle\Http\Client;

class GetAccountDetail {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
	}
	
	/*
	 *
	 */
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		
		$user_id = trim ( $data->getaccountdetail->user_id );
		
		$guzzle = new Client ();
		
		$request = $guzzle->post ( MemreasConstants::MEMREAS_PAY_URL, null, array (
				'action' => 'getaccountdetail',
				'user_id' => $user_id 
		) );
		
		$response = $request->send ();
		$data = json_decode ( $response->getBody ( true ), true );
		
		$status = $data ['status'];
		
		if ($status == 'Success') {
			$account = $data ['account'];
			$accountDetail = $data ['accountDetail'];
			$output .= "<account>";
			$output .= "<username>" . $account ['username'] . "</username>";
			$output .= "<account_id>" . $account ['account_id'] . "</account_id>";
			$output .= "<account_type>" . $account ['account_type'] . "</account_type>";
			$output .= "<balance>" . $account ['balance'] . "</balance>";
			$output .= "<first_name>" . $accountDetail ['first_name'] . "</first_name>";
			$output .= "<last_name>" . $accountDetail ['last_name'] . "</last_name>";
			$output .= "<last_name>" . $accountDetail ['last_name'] . "</last_name>";
			$output .= "<address_line_2>" . $accountDetail ['address_line_2'] . "</address_line_2>";
			$output .= "<city>" . $accountDetail ['city'] . "</city>";
			$output .= "<state>" . $accountDetail ['state'] . "</state>";
			$output .= "<postal_code>" . $accountDetail ['postal_code'] . "</postal_code>";
			$output .= "</account>";
		} else {
			$status = 'Failure';
			$message = $data ['message'];
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getaccountdetailresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</getaccountdetailresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		die ();
	}
}

?>
