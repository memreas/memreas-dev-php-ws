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
            Mlog::addone(__CLASS__ . __METHOD__ . '-' . __LINE__, $this->message_data);
            $error_flag = 0;
            $message = '';
            if ($action == 'stripe_listCards') {
                $this->getStripeData('listCards');
            } else if ($action == 'stripe_addCard') {
                $this->getStripeData('storeCard');
            } else if ($action == 'stripe_listCards') {
                $this->getStripeData('listCards');
            } else if ($action == 'stripe_listCards') {
                $this->getStripeData('listCards');
            }
    }
        public function getStripeData($action_method){
            $guzzle = new Client ();
		$jsonArr = json_decode ( $this->message_data['xml'], true );
                 
                $response = $guzzle->post ( 'https://memreasdev-pay.memreas.com/stripe/'.$action_method, [
                     'form_params' =>[
                         'callback' =>$_REQUEST['callback'],
                        'json' =>$this->message_data['xml'],
                    ]
                ]
				
		);
		
 	echo  $response->getBody ();
        } 
}

?>
