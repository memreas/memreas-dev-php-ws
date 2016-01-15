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
	protected $parent;
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
 
		/*
		 * Kamlesh - note my comment in Index Controller the $action here is the stripe_action (i.e.listCards)
		 *  if you build with action=stripe and stripe_action='listCards' which is $action in this function you don't need
		 *  the if switch 
		 *  Your call would be $this->getStripeData($action);
		 *  
		 *  Note: you should add username, user_id, sid, memreascookie - whatever is available and validate user against rediscache
		 *   in payments server - let's discuss
		 */
		
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__, $this->message_data );
		$error_flag = 0;
		$message = '';
                //stripe_
                $action_method = substr($action,7 );
                Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__, $action_method );
		 $this->getStripeData($action_method);
	}
	public function getStripeData($action_method) {
		$guzzle = new Client ();
		
		//
		// Check xml or json
		//
		if (!empty ($this->message_data ['xml'])) {
			$jsonArr = json_decode ( $this->message_data ['xml'], true );
			Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::xml as JSON::', $jsonArr );
		} else if (!empty ($this->message_data ['json'])) {
			$jsonArr = json_decode ( $this->message_data ['json'], true );
			Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::json as JSON::', $jsonArr );
		}
		
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method::', MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method );
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::$_REQUEST [callback]::', $_REQUEST ['callback'] );
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::$_SESSION[sid]::', $_SESSION['sid'] );
		Mlog::addone ( __CLASS__ . __METHOD__ . '-' . __LINE__.'::$_REQUEST [callback]::', $_REQUEST ['callback'] );
		$response = $guzzle->request('POST', MemreasConstants::MEMREAS_PAY_URL_STRIPE, [
				'form_params' => [
						'action' => $action_method,
						'sid' => $_SESSION['sid'],
						'data' => json_encode($jsonArr)
				]
		]);
		
		
		echo $response->getBody ();
		Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::response body->',$response->getBody ());
	}
}

?>
