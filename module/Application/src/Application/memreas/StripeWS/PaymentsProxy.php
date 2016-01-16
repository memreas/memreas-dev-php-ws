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
use Application\memreas\Mlog;
use Application\memreas\Utility;

class PaymentsProxy {
	protected $message_data;
	protected $memreas_tables;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $parent) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->parent = $parent;
	}
	
	/*
	 *
	 */
	public function exec($action) {
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__, $this->message_data );
		$error_flag = 0;
		$message = '';
		// stripe_
		$action_method = substr ( $action, 7 );
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__, $action_method );
		$this->getStripeData ( $action_method );
	}
	public function getStripeData($action_method) {
		$guzzle = new Client ();
		
		//
		// Check xml or json
		//
		if (! empty ( $this->message_data ['xml'] )) {
			$jsonArr = json_decode ( $this->message_data ['xml'], true );
			Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__ . '::xml as JSON::', $jsonArr );
		} else if (! empty ( $this->message_data ['json'] )) {
			$jsonArr = $this->message_data;
			//Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__ . '::json as JSON::', $jsonArr );
			error_log(__CLASS__.__METHOD__.__LINE__.'$this->message_data::'.print_r($this->message_data, true).PHP_EOL);
		}
		//$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
		//		'form_params' => [ 
		//				'sid' => $_SESSION ['sid'],
		//				'json' => json_encode($jsonArr) 
		//		] 
		//] );
		//$data = json_decode($response->getBody(), true);
		//error_log('$data -->'.print_r($data,true).PHP_EOL);
		
		$response = $guzzle->request('POST', MemreasConstants::MEMREAS_PAY_URL, [
				'form_params' => [
						'action' => $action_method,
						'sid' => $_SESSION['sid'],
						'json' => json_encode($jsonArr) 
				]
		]);
		$data = json_decode($response->getBody(), true);
		error_log('$data -->'.print_r($data,true).PHP_EOL);
		
		
		echo $_REQUEST ['callback'] . "(" . json_encode($data) . ")";
		//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::response body->', $response->getBody () );
		exit();
	}
}

?>
