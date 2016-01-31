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

	public function __construct() {
	}
	
	/*
	 *
	 */
	public function exec($action, $jsonArr, $callback = null) {
		$cm = __CLASS__ . __METHOD__;
		Mlog::addone ( $cm . __LINE__, jsonArr );

		$action_method = substr ( $action, 7 );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'json--->', jsonArr );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$action_method----->', $action_method );
		$guzzle = new \GuzzleHttp\Client ();
		if (!empty($callback)) {
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [
					'form_params' => [
							'callback' => $callback,
							'sid' => $_SESSION ['sid'],
							'json' => json_encode($json)
					]
			] );
			
			//$sid = $_SESSION ['sid'];
			//$json = json_encode($jsonArr);
			//$query_string = "?callback=$callback&sid=$sid&json=$json";
			//$url = MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method . $query_string;
			//Mlog::addone($cm.'stripe get url--->', $url);
			//$response = $guzzle->request ( 'GET', $url);
		} else {
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [
					'form_params' => [
							'sid' => $_SESSION ['sid'],
							'json' => json_encode($json)
					]
			] );
			
			//$sid = $_SESSION ['sid'];
			//$json = json_encode($jsonArr);
			//$query_string = "?sid=$sid&json=$json";
			//$url = MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method . $query_string;
			//Mlog::addone($cm.'stripe get url--->', $url);
			//$response = $guzzle->request ( 'GET', $url);
		}
		Mlog::addone($cm.'$response->getStatusCode()--->', $response->getStatusCode());
		Mlog::addone($cm.'$response->getReasonPhrase()--->', $response->getReasonPhrase());
		Mlog::addone($cm.'$response->getBody ()--->', (string) $response->getBody ());
		
		
		echo (string) $response->getBody ();
		Mlog::addone ( $cm.__LINE.'::$response->getBody ()--->', (string) $response->getBody () );
	}
}

?>
