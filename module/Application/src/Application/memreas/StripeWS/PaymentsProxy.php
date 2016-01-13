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
                $action_method = substr($action,6 );
		 $this->getStripeData($action_method);
	}
	public function getStripeData($action_method) {
		$guzzle = new Client ();
		$jsonArr = json_decode ( $this->message_data ['xml'], true );
		
		$response = $guzzle->post ( MemreasConstants::MEMREAS_PAY_URL_STRIPE . $action_method, [ 
				'form_params' => [ 
						'callback' => $_REQUEST ['callback'],
						'sid' => $_SESSION['sid'],
						'json' => $this->message_data ['xml'] 
				] 
		] );
		
		echo $response->getBody ();
		Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::response body->',$response->getBody ());
	}
}

?>
