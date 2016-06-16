<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
/*
 * Get user's details service
 * @params: user_id Provide user id to get back detail
 * @Return User information detail
 * @Tran Tuan
 */
namespace Application\memreas;

use Application\Entity\User;
use Application\memreas\StripeWS\PaymentsProxy;

class GetUserDetails {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $url_signer;
	protected $redis;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function exec($frmweb = false, $output = '') {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::GetUserDetails', 'GetUserDetails exec()' );
			$this->redis = AWSMemreasRedisCache::getHandle ();
			if (empty ( $this->redis )) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::', 'GetUserDetails failed to get handle' );
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::', 'GetUserDetails success getting handle' );
			}
			$error_flag = 0;
			$message = '';
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
			}
			$user_id = trim ( $data->getuserdetails->user_id );
			
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u' );
			$qb->from ( 'Application\Entity\User', 'u' );
			$qb->where ( "u.user_id=?1" );
			$qb->setParameter ( 1, $user_id );
			$result_user = $qb->getQuery ()->getResult ();
			if (empty ( $result_user )) {
				$status = "Failure";
				$message = "No data available to this user";
			} else {
				$status = 'Success';
				$user_id = $result_user [0]->user_id;
				$output .= '<user_id>' . $user_id . '</user_id>';
				$output .= '<username>' . $result_user [0]->username . '</username>';
				$output .= '<email>' . $result_user [0]->email_address . '</email>';
				$metadata = $result_user [0]->metadata;
				$metadata = json_decode ( $metadata, true );
				if (isset ( $metadata ['alternate_email'] )) {
					$output .= '<alternate_email>' . $metadata ['alternate_email'] . '</alternate_email>';
				} else {
					$output .= '<alternate_email></alternate_email>';
				}
				
				if (isset ( $metadata ['gender'] )) {
					$output .= '<gender>' . $metadata ['gender'] . '</gender>';
				} else {
					$output .= '<gender></gender>';
				}
				
				if (isset ( $metadata ['dob'] )) {
					$output .= '<dob>' . $metadata ['dob'] . '</dob>';
				} else {
					$output .= '<dob></dob>';
				}
				
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . ':: empty($stripe_getCustomerInfo)', '...' );
				$jsonArr = [ ];
				$jsonArr ['memreascookie'] = $_SESSION ['memreascookie'];
				$jsonArr ['user_id'] = $_SESSION ['user_id'];
				$PaymentsProxy = new PaymentsProxy ( $this->service_locator );
				$result = $PaymentsProxy->exec ( "stripe_getCustomerInfo", $jsonArr );
				Mlog::addone ( 'stripe_getCustomerInfo-->', $result );
				$userAccountArr = json_decode ( $result, true );
				
				// For stripe customer id
				if (isset ( $userAccountArr ['buyer_account'] ['accountHeader'] ['stripe_customer_id'] )) {
					$_SESSION ['stripe_customer_id'] = $userAccountArr ['buyer_account'] ['accountHeader'] ['stripe_customer_id'];
				}
				
				// For stripe seller customer id
				if (isset ( $userAccountArr ['seller_account'] ['accountHeader'] ['stripe_customer_id'] )) {
					$_SESSION ['stripe_seller_customer_id'] = $userAccountArr ['seller_account'] ['accountHeader'] ['stripe_customer_id'];
				}
				
				// For Plan
				if (isset ( $userAccountArr ['seller_account'] ['subscription'] )) {
					$subscription = $userAccountArr ['seller_account'] ['subscription'];
					$output .= '<subscription><plan>' . $subscription ['plan'] . '</plan><plan_name>' . $subscription ['description'] . '</plan_name></subscription>';
					
					// Store data in session for stripe customer id
					$_SESSION ['plan'] = $subscription ['plan'];
				} else {
					// Mlog::addone ( 'if (empty( $metadata [subscription] )', '<subscription><plan>FREE</plan></subscription>' );
					$output .= '<subscription><plan>FREE</plan></subscription>';
				}
				
				// error_log('$data -->'.print_r($data,true).PHP_EOL);
				$data = $userAccountArr;
				if ((! empty ( $data )) && ($data ['status'] == 'Success')) {
					$output .= '<accounts>';
					//
					// Handle buyer_account
					//
					$account = (! empty ( $data ['buyer_account'] )) ? $data ['buyer_account'] : null;
					if ($account) {
						$output .= '<account>';
						// $output .= '<account_id>' . $account['accountHeader']['account_id'] . '</account_id>';
						$output .= '<account_type>' . $account ['accountHeader'] ['account_type'] . '</account_type>';
						$output .= '<account_balance>' . $account ['accountHeader'] ['balance'] . '</account_balance>';
						$output .= '</account>';
					}
					//
					// Handle seller_account
					//
					$account = (! empty ( $data ['seller_account'] )) ? $data ['seller_account'] : null;
					if ($account) {
						$output .= '<account>';
						// $output .= '<account_id>' . $account['accountHeader']['account_id'] . '</account_id>';
						$output .= '<account_type>' . $account ['accountHeader'] ['account_type'] . '</account_type>';
						$output .= '<account_balance>' . $account ['accountHeader'] ['balance'] . '</account_balance>';
						$output .= '</account>';
					}
					$output .= '</accounts>';
				} else {
					$output .= '<account_type>Free user</account_type>';
				}
				$profile_image = $_SESSION ['profile_pic'];
				
				$output .= '<profile><![CDATA[' . $profile_image . ']]></profile>';
			}
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<userdetailsresponse>";
			$xml_output .= "<status>" . $status . "</status>";
			if (isset ( $message ))
				$xml_output .= "<message>{$message}</message>";
			$xml_output .= $output;
			$xml_output .= "</userdetailsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'GetUserDetails error ->' . $e->getMessage ();
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<userdetailsresponse>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= $output;
			$xml_output .= "</userdetailsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
		error_log ( __CLASS__ . __METHOD__ . __LINE__ . '$this->xml_output--->' . $xml_output . PHP_EOL );
	} // end exec()
}

?>
