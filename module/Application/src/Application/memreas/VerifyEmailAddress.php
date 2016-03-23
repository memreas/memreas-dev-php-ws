<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\SessionManager;
use Zend\Session\Container;
use Application\Model\MemreasConstants;

class VerifyEmailAddress {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $user_id;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec() {
		
		/*
		 * fetch input vars
		 */
		if (isset ( $_GET ['email_verification_id'] ) && isset ( $_GET ['user_id'] )) {
			$email_verification_id_received = $_GET ['email_verification_id'];
			$user_id = $_GET ['user_id'];
			
			/*
			 * Get user meta here
			 */
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u' )->from ( 'Application\Entity\User', 'u' )->where ( "u.user_id = '{$user_id}'" );
			$user = $qb->getQuery ()->getOneOrNullResult ();
			
			if ($user) {
				$metadata = json_decode ( $user->metadata, true );
				// error_log("user->metadata ----> ".$user->metadata.PHP_EOL);
				$email_verification_sent = $metadata ['user'] ['email_verification_id'];
			}
			/*
			 * If the codes match update the user meta else return false
			 */
			if ($email_verification_sent == $email_verification_id_received) {
				/*
				 * Update user meta
				 */
				$metadata ['user'] ['email_verified'] = "1";
				
				/*
				 * Get the client's ip address and store it with date
				 */
				if (isset ( $_SERVER ['HTTP_X_FORWARDED_FOR'] ) && $_SERVER ['HTTP_X_FORWARDED_FOR']) {
					$clientIpAddress = $_SERVER ['HTTP_X_FORWARDED_FOR'];
				} else {
					$clientIpAddress = $_SERVER ['REMOTE_ADDR'];
				}
				$metadata ['user'] ['email_verified_ip_address'] = $clientIpAddress;
				$metadata ['user'] ['email_verified_time'] = date ( "Y-m-d H:i:s" );
				
				/*
				 * Store user meta
				 */
				$user->metadata = json_encode ( $metadata );
				$this->dbAdapter->persist ( $user );
				$this->dbAdapter->flush ();
				$this->user_id = $user->user_id;
				return $this->user_id;
			}
			return false;
		}
	}
}
?>
