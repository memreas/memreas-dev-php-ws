<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas\StripeWS;

use Application\memreas\Mlog;
use Application\Model\MemreasConstants;
use GuzzleHttp\Client;

class PaymentsProxy {

	public function __construct() {
	}
	
	/*
	 *
	 */
	public function exec($action, $jsonArr) {
		$cm = __CLASS__ . __METHOD__;
		Mlog::addone ( $cm . __LINE__, jsonArr );

		$action_method = substr ( $action, 7 );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'json--->', jsonArr );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__.'$action_method----->', $action_method );
		$guzzle = new \GuzzleHttp\Client ();
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [
					'form_params' => [
							'sid' => $_SESSION ['sid'],
							'json' => json_encode($jsonArr)
					]
			] );
		Mlog::addone($cm.'$response->getStatusCode()--->', $response->getStatusCode());
		Mlog::addone($cm.'$response->getReasonPhrase()--->', $response->getReasonPhrase());
		Mlog::addone($cm.'$response->getBody ()--->', (string) $response->getBody ());
		
		if (!empty($callback)) {
			echo $callback . "(" . trim( (string) $response->getBody () ) . ")";
		} else {
			echo trim( (string) $response->getBody () );
		}
		Mlog::addone ( $cm.__LINE.'::$response->getBody ()--->', trim( (string) $response->getBody () ) );
	}
}

?>
