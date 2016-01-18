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
use GuzzleHttp\Client;

class GetPlans {
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
		$user_id = trim ( $data->getplans->user_id );
		$guzzle = new Client ();
		
		$response = $guzzle->post ( MemreasConstants::MEMREAS_PAY_URL, [
                    'form_params' =>[
                        'action' => 'listplans',
			'user_id' => $user_id 
                    ]
                ]
				
		 );
		 
		$data = json_decode ( $response->getBody (), true );
		$status = $data ['status'];
		
		if ($status == 'Success') {
			$plans = $data ['plans'];
			if (! empty ( $plans )) {
				foreach ( $plans as $plan ) {
					$output .= '<user_id>' . $user_id . '</user_id>';
					$output .= '<plan_id>' . $plan ['plan'] ['id'] . '</plan_id>';
					$output .= '<plan_name>' . $plan ['plan'] ['name'] . '</plan_name>';
					$output .= '<plan_amount>' . ($plan ['plan'] ['amount'] / 100) . '</plan_amount>';
					$output .= '<plan_currency>' . $plan ['plan'] ['currency'] . '</plan_currency>';
				}
			} else {
				$status = 'Failure';
				$message = 'This user has no any actived plan';
			}
		} else
			$message = $data ['message'];
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getplansresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</getplansresponse>";
		$xml_output .= "</xml>";
		echo trim($xml_output);
		die ();
	}
}

?>
