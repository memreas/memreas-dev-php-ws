<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Aws\S3;
use Aws\CloudFront;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\Mlog;

class MemreasSignedCookie {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $aws;
	protected $s3;
	protected $cloud_front;
	public function __construct() {
		$this->private_key_filename = getcwd () . MemreasConstants::CLOUDFRONT_KEY_FILE;
		$this->key_pair_id = MemreasConstants::CLOUDFRONT_KEY_PAIR_ID;
		$this->expires = time () + MemreasConstants::CLOUDFRONT_EXPIRY_TIME;
		$this->signature_encoded = null;
		$this->policy_encoded = null;
		
		// Fetch aws handle
		$this->aws = MemreasConstants::fetchAWS ();
		
		// Fetch the S3 class
		$this->s3 = $this->aws->createS3 ();
		
		// Fetch the CloudFront class
		$this->cloud_front = $this->aws->createCloudFront ();
	}
	public function exec($ipAddress) {
		// $data = simplexml_load_string ( $_POST ['xml'] );
		$time = time ();
		$cloudFrontHost = MemreasConstants::CLOUDFRONT_HLSSTREAMING_HOST . '*';
		// $domain = 'memreas.com';
		$domain = 'memreas.com';
		$customPolicy = '{"Statement":[{"Resource":"' . $cloudFrontHost . '","Condition":{"DateLessThan":{"AWS:EpochTime":' . $this->expires . '}}}]}';
		$encodedCustomPolicy = $this->url_safe_base64_encode ( $customPolicy );
		$customPolicySignature = $this->rsa_sha1_sign ( $customPolicy, $this->private_key_filename );
		$customPolicySignature = $this->url_safe_base64_encode ( $customPolicySignature );
		
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$customPolicy', $customPolicy );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$encodedCustomPolicy', $encodedCustomPolicy );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$customPolicySignature', $customPolicySignature );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$this->key_pair_id', $this->key_pair_id );
		
		setrawcookie ( "CloudFront-Policy", $encodedCustomPolicy, 0, "/*", $domain, 1, 1 );
		setrawcookie ( "CloudFront-Signature", $customPolicySignature, 0, "/*", $domain, 1, 1 );
		setrawcookie ( "CloudFront-Key-Pair-Id", $this->key_pair_id, 0, "/*", $domain, 1, 1 );
		
		$xmlCookieData = array ();
		$xmlCookieData ["CloudFront-Policy"] = $encodedCustomPolicy;
		$xmlCookieData ["CloudFront-Signature"] = $customPolicySignature;
		$xmlCookieData ["CloudFront-Key-Pair-Id"] = $this->key_pair_id;
		
		return $xmlCookieData;
	}
	function rsa_sha1_sign($policy, $private_key_filename) {
		$signature = "";
		openssl_sign ( $policy, $signature, file_get_contents ( $private_key_filename ) );
		return $signature;
	}
	function url_safe_base64_encode($value) {
		$encoded = base64_encode ( $value );
		// replace unsafe characters +, = and / with the safe characters -, _ and ~
		return str_replace ( array (
				'+',
				'=',
				'/' 
		), array (
				'-',
				'_',
				'~' 
		), $encoded );
	}
}

?>
