<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of memreas specific code, via any medium is strictly prohibited
 * proprietary and confidential
 */
// Select portions of software below utilize code found @ https://github.com/eddturtle/direct-upload-s3-signaturev4
// as such including license for software utilized. License is bounded to this file only.

// The MIT License (MIT)

// Copyright (c) 2015 Edd Turtle

// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:

// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.

// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
// use Application\memreas\PostPolicy
class Memreastvm {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $aws;
	protected $s3;
	protected $sts;
	protected $iam;
	protected $encryption;
	public function __construct() {
		/**
		 * Initialize aws
		 */
		$this->aws = MemreasConstants::fetchAWS ();
		
		// Fetch the S3 class
		$this->s3 = $this->aws->createS3 ();
		
		// Fetch the STS class
		$this->sts = $this->aws->createSts ();
		
		// Fetch the IAM class
		$this->iam = $this->aws->createIam ();
	}
	public function exec($type = "") {
		try {
			if (empty ( $_SESSION ['user_id'] )) {
				header ( 'Content-Type: application/json' );
				$status = 'error';
				$message = 'user is not logged in';
				$arr = array (
						'status' => $status,
						'message' => $message 
				);
				echo json_encode ( $arr );
				return;
			}
			/**
			 * Fetch data return type
			 */
			$data = simplexml_load_string ( $_POST ['xml'] );
			$type = $data->type;
			if (empty ( $type )) {
				$header = 'Content-Type: application/json';
			} else if ($type == 'xml') {
				$header = "Content-type: text/xml";
			}
			
			// create singature data
			$signature_data = $this->getSignature ( MemreasConstants::S3_APPKEY, MemreasConstants::S3_APPSEC, MemreasConstants::S3_REGION );
			
			// provide media_id
			$signature_data ['media_id'] = MUUID::fetchUUID ();
			
			// flush output
			$cdata = '<![CDATA[' . json_encode ( $signature_data ) . ']]>';
			if ($type == 'xml') {
				header ( "Content-type: text/xml" );
				$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$xml_output .= "<xml>";
				$xml_output .= "<memreas_tvm>$cdata</memreas_tvm>";
				$xml_output .= "</xml>";
				echo $xml_output;
			} else {
				header ( 'Content-Type: application/json' );
				echo json_encode ( $signature_data );
				error_log ( '$signature_data json' . json_encode ( $signature_data ) );
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . 'error::', $e->getMessage () );
		}
	}
	public function getSignature($s3key, $s3Secret, $expires) {
		
		// Fill These In!
		define ( 'S3_BUCKET', MemreasConstants::S3BUCKET );
		define ( 'S3_KEY', $s3key );
		define ( 'S3_SECRET', $s3Secret );
		define ( 'S3_REGION', MemreasConstants::S3_REGION );
		define ( 'S3_ACL', 'private' );
		
		$algorithm = "AWS4-HMAC-SHA256";
		$service = "s3";
		$date = gmdate ( 'Ymd\THis\Z' );
		$shortDate = gmdate ( 'Ymd' );
		$requestType = "aws4_request";
		$successStatus = '201';
		
		$scope = [ 
				S3_KEY,
				$shortDate,
				S3_REGION,
				$service,
				$requestType 
		];
		$credentials = implode ( '/', $scope );
		$encryption = 'AES256';
		$policy = [ 
				'expiration' => gmdate ( 'Y-m-d\TG:i:s\Z', strtotime ( '+6 hours' ) ),
				'conditions' => [ 
						[ 
								'bucket' => S3_BUCKET 
						],
						[ 
								'starts-with',
								'$key',
								'' 
						],
						[ 
								'starts-with',
								'$Content-Type',
								'' 
						],
						[ 
								'acl' => S3_ACL 
						],
						[ 
								'success_action_status' => $successStatus 
						],
						[ 
								'x-amz-algorithm' => $algorithm 
						],
						[ 
								'x-amz-credential' => $credentials 
						],
						[ 
								'x-amz-date' => $date 
						],
						[ 
								'x-amz-expires' => $expires 
						],
						[ 
								'x-amz-server-side-encryption' => $encryption 
						] 
				] 
		];
		
		$base64Policy = base64_encode ( json_encode ( $policy ) );
		
		// Signing Keys
		$dateKey = hash_hmac ( 'sha256', $shortDate, 'AWS4' . S3_SECRET, true );
		$dateRegionKey = hash_hmac ( 'sha256', S3_REGION, $dateKey, true );
		$dateRegionServiceKey = hash_hmac ( 'sha256', $service, $dateRegionKey, true );
		$signingKey = hash_hmac ( 'sha256', $requestType, $dateRegionServiceKey, true );
		
		// Signature
		$signature = hash_hmac ( 'sha256', $base64Policy, $signingKey );
		
		// return data
		$signature_data = array ();
		$signature_data ['acl'] = S3_ACL;
		$signature_data ['successStatus'] = $successStatus;
		$signature_data ['base64Policy'] = $base64Policy;
		$signature_data ['algorithm'] = $algorithm;
		$signature_data ['credentials'] = $credentials;
		$signature_data ['expires'] = $expires;
		$signature_data ['date'] = $date;
		$signature_data ['signature'] = $signature;
		
		return $signature_data;
	}
}

?>
