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

class ListPayees {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec($frmweb = false, $output = '') {
		$message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$page = trim ( $data->listpayees->page );
		$limit = trim ( $data->listpayees->limit );
		$username = trim ( $data->listpayees->username );
		
		$guzzle = new Client ();
		
		$response = $guzzle->request ( 'POST', MemreasConstants::MEMREAS_PAY_URL, [ 
				'form_params' => [ 
                                                'sid' => $_SESSION ['sid'],
						'admin_key' => $_REQUEST ['admin_key'],	
                        'action' => 'listpayees',
				'username' => $username,
				'page' => $page,
				'limit' => $limit 
                    ]
                ]
				
		 );
		
		 
$result = trim ( ( string ) $response->getBody () );
 		echo $result;
                die();
	}
}

?>
