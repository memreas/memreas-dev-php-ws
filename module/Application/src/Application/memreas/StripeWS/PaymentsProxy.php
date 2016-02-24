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
	 * Proxy for stripe
	 *
	 * // memreas stripe actions
	 * accounthistoryAction()
	 * activeCreditAction()
	 * addSellerAction()
	 * addValueAction()
	 * buyMediaAction()
	 * checkOwnEventAction()
	 * deleteCardsAction()
	 * decrementAction()
	 * getCustomerInfoAction()
	 * getUserBalanceAction()
	 * listPlanAction()
	 * listCardsAction()
	 * listMassPayeeAction()
	 * storeCardAction()
	 * subscribeAction()
	 * viewCardAction()
	 * updateCardAction()
	 *
	 */
	public function exec($action, $jsonArr) {
		$cm = __CLASS__ . __METHOD__;
		$action_method = substr ( $action, 7 );
		$guzzle = new \GuzzleHttp\Client ();
		if (isset($_REQUEST['admin_key'])) {
			/**
			 * Admin is logged in and request user data
			 */
			Mlog::addone ( $cm . __LINE__ . 'token--->', $_REQUEST['token'] );
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [
					'form_params' => [
							'sid' => $_SESSION ['sid'],
							'admin_key' => $_REQUEST['admin_key'],
							'json' => json_encode ( $jsonArr )
								
					]
			] );
			
					
		} else if (isset($_REQUEST['memreascookie'])) {
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'memreascookie' => $_SESSION ['memreascookie'],
							'json' => json_encode ( $jsonArr ) 
					] 
			] );
				
		} else {
			Mlog::addone ( $cm . __LINE__, $jsonArr );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'json--->', $jsonArr );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$action_method----->', $action_method );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::MEMREAS_PAY_URL_STRIPE----->', MemreasConstants::MEMREAS_PAY_URL_STRIPE );
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'sid' => $_SESSION ['sid'],
							'json' => json_encode ( $jsonArr ) 
					] 
			] );
			Mlog::addone ( $cm , __LINE__ );
				
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'memreascookie' => $_SESSION ['memreascookie'],
							'to' => 'johnmeah0@gmail.com',
							'subject' => 'hello',
							'content' => 'world'
					] 
			] );
			Mlog::addone ( $cm , __LINE__ );
		}
		// Mlog::addone ( $cm . '$response->getStatusCode()--->', $response->getStatusCode () );
		// Mlog::addone ( $cm . '$response->getReasonPhrase()--->', $response->getReasonPhrase () );
		// Mlog::addone ( $cm . '$response->getBody ()--->', trim(( string ) $response->getBody ()) );
		
		$result = trim ( ( string ) $response->getBody () );
		Mlog::addone ( $cm . __LINE__ . '::$ouptput--->', $result );
		echo $result;
	}
}

?>
