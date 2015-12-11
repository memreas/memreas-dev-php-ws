<?php

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
		$cloudFrontHost = MemreasConstants::CLOUDFRONT_HLSSTREAMING_HOST;
		$domain = 'memreas.com';
		$customPolicy = '{
   							"Statement":[
      							{
         							"Resource":"' . $cloudFrontHost . '",
         							"Condition":{
            								"DateLessThan":{
               								"AWS:EpochTime":' . $this->expires . '
            								},
         								"IpAddress":{"AWS:SourceIp":"' . $ipAddress . '"}
         							}
      							}
   							]
						}';
		$encodedCustomPolicy = MemreasSignedCookie::url_safe_base64_encode ( $customPolicy );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$encodedCustomPolicy', $encodedCustomPolicy );
		$customPolicySignature = MemreasSignedCookie::getSignedPolicy ( $this->private_key_filename, $customPolicy );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$customPolicySignature', $customPolicySignature );
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$this->key_pair_id', $this->key_pair_id );
		
		// MemreasSignedCookie::setCookie ( "CloudFront-Policy", $encodedCustomPolicy, $domain );
		// MemreasSignedCookie::setCookie ( "CloudFront-Signature", $customPolicySignature, $domain );
		// MemreasSignedCookie::setCookie ( "CloudFront-Key-Pair-Id", $this->key_pair_id, $domain );
		
		setrawcookie ( "CloudFront-Policy", $encodedCustomPolicy, 0, "/", $domain, 1, 1 );
		setrawcookie ( "CloudFront-Signature", $customPolicySignature, 0, "/", $domain, 1, 1 );
		setrawcookie ( "CloudFront-Key-Pair-Id", $this->key_pair_id, 0, "/", $domain, 1, 1 );
		$_SESSION ["CloudFront-Policy"] = $encodedCustomPolicy;
		$_SESSION ["CloudFront-Signature"] = $customPolicySignature;
		$_SESSION ["CloudFront-Key-Pair-Id"] = $this->key_pair_id;
		
		// error_log ( __CLASS__ . __METHOD__ . __LINE__ . "Cookies->" . print_r ( $_COOKIE, true ) . PHP_EOL );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "CloudFront-Policy->", $_COOKIE ['CloudFront-Policy'] );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "CloudFront-Signature->", $_COOKIE ['CloudFront-Signature'] );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "CloudFront-Key-Pair-Id->", $_COOKIE ['CloudFront-Key-Pair-Id'] );
	}
	public static function rsa_sha1_sign($policy, $private_key_filename) {
		$signature = "";
		openssl_sign ( $policy, $signature, file_get_contents ( $private_key_filename ) );
		return $signature;
	}
	public static function url_safe_base64_encode($value) {
		$encoded = base64_encode ( $value );
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
	public static function getSignedPolicy($private_key_filename, $policy) {
		$signature = MemreasSignedCookie::rsa_sha1_sign ( $policy, $private_key_filename );
		$encoded_signature = MemreasSignedCookie::url_safe_base64_encode ( $signature );
		return $encoded_signature;
	}
	public static function setCookie($name, $val, $domain) {
		// using our own implementation because
		// using php setcookie means the values are URL encoded and then AWS CF fails
		header ( "Set-Cookie: $name=$val; path=/; domain=$domain; secure; httpOnly", false );
	}
}

?>
