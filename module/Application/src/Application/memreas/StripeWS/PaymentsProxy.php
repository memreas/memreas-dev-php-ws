<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas\StripeWS;

use Application\memreas\Mlog;
use Application\memreas\GetDiskUsage;
use Application\Model\MemreasConstants;
use GuzzleHttp\Client;

class PaymentsProxy {
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
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
		
		
		/*-
		 * special case for subscribe
		 */
		if ($action_method == 'subscribe') {
			$disk_usage = new GetDiskUsage($this->service_locator);
			$jsonArr['disk_usage'] = $disk_usage->exec($_SESSION['user_id'], true);
		}
		/*-
		 * these cases handle admin, credit activation from email, web queries, and mobile queries
		 */
		if (isset ( $_REQUEST ['admin_key'] )) {
			/**
			 * Admin is logged in and request user data
			 */
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'sid' => $_SESSION ['sid'],
							'admin_key' => $_REQUEST ['admin_key'],
							'json' => json_encode ( $jsonArr ) 
					]
			] );
		} else if (isset ( $_REQUEST ['token'] ) && ($action_method == 'activeCredit')) {
			Mlog::addone ( $cm, __LINE__ );
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'token' => $_REQUEST ['token'], 
							'cid' => $_REQUEST ['cid'] 
					] 
			] );
		} else if (isset ( $_REQUEST ['memreascookie'] )) {
			Mlog::addone ( $cm, __LINE__ );
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'memreascookie' => $_SESSION ['memreascookie'],
							'json' => json_encode ( $jsonArr ) 
					] 
			] );
		} else {
			Mlog::addone ( $cm . __LINE__, 'about to send email action==>' . $action_method );
			/*
			 * $response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . 'email', [
			 * 'form_params' => [
			 * 'sid' => $_SESSION ['sid'],
			 * 'to' => 'johnmeah0@gmail.com',
			 * 'subject' => 'hello',
			 * 'content' => 'world'
			 * ]
			 * ] );
			 */
			Mlog::addone ( $cm, __LINE__ );
			Mlog::addone ( $cm . __LINE__, $jsonArr );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'json--->', $jsonArr );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$action_method----->', $action_method );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::MEMREAS_PAY_URL_STRIPE----->', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$_SESSION[sid]----->', $_SESSION['sid'] );
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'json----->', json_encode ( $jsonArr ) );
			$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
					'form_params' => [ 
							'sid' => $_SESSION ['sid'],
							'json' => json_encode ( $jsonArr ) 
					] 
			] );
		}
		// Mlog::addone ( $cm . '$response->getStatusCode()--->', $response->getStatusCode () );
		// Mlog::addone ( $cm . '$response->getReasonPhrase()--->', $response->getReasonPhrase () );
		// Mlog::addone ( $cm . '$response->getBody ()--->', trim(( string ) $response->getBody ()) );
		
		$result = trim ( ( string ) $response->getBody () );
		Mlog::addone ( $cm . __LINE__ . '::$ouptput--->', $result );
                //return data not echo to output
               return $result;
	}
}

?>
