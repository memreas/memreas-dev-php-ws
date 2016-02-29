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

class GetOrderHistory {
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
                error_log('kkk->'.print_r($_POST,true));
		$user_id = trim ( $data->getorderhistory->user_id );
		$search_username = trim ( $data->getorderhistory->search_username );
		$page = trim ( $data->getorderhistory->page );
		$limit = trim ( $data->getorderhistory->limit );
		
		$guzzle = new Client ();
		
		$request_data = array (
				'user_id' => $user_id,
				'search_username' => $search_username,
				'page' => $page,
				'limit' => $limit 
		);
		
		$response = $guzzle->post ( MemreasConstants::MEMREAS_PAY_URL, [ 
				'form_params' => [ 
                                                'sid' => $_SESSION ['sid'],
						'admin_key' => $_REQUEST ['admin_key'],	 
						'action' => 'getorderhistory',
						'data' => json_encode ( $request_data )
                                    ] 
		]);
		
 		$data = json_decode ( $response->getBody (), true );
		
		$status = $data ['status'];
		
		if ($status == 'Success') {
			$status = 'Success';
			$orders = $data ['orders'];
			if (! empty ( $orders )) {
				$output .= '<orders>';
				if ($user_id)
					$output .= '<user_id>' . $user_id . '</user_id>';
				foreach ( $orders as $order ) {
					$username = $order ['username'];
					$transaction = $order ['transaction'];
					$transactionRequest = json_decode ( $transaction ['transaction_request'], true );
					if (is_array ( $transactionRequest )) {
						if (isset ( $transaction ['description'] ))
							$description = $transaction ['description'];
						else
							$description = '';
					} else
						$description = '';
					
					$output .= '<order>';
					$output .= '<username>' . $username . '</username>';
					$output .= '<transaction_id>' . $transaction ['transaction_id'] . '</transaction_id>';
					$output .= '<transaction_type>' . str_replace ( "_", " ", $transaction ['transaction_type'] ) . '</transaction_type>';
					$output .= '<transaction_detail>' . $description . '</transaction_detail>';
					$output .= '<amount>' . $transaction ['amount'] . '</amount>';
					$output .= '<balance>' . $order ['balance'] . '</balance>';
					$output .= '<transaction_sent>' . $transaction ['transaction_sent'] . '</transaction_sent>';
					$output .= '</order>';
				}
				$output .= '</orders>';
			} else {
				$status = 'Failure';
				$message = 'No record found';
			}
		} else {
			$status = 'Failure';
			$message = $data ['message'];
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getorderhistoryresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</getorderhistoryresponse>";
		$xml_output .= "</xml>";
		echo trim($xml_output);
		die ();
	}
}

?>
