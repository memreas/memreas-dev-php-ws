<?php

namespace Application\memreas;

use Aws\Common\Aws;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
// use Application\memreas\PostPolicy
class Memreastvm {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
	}
	public function exec($type = "") {
		try {
Mlog::add($_SESSION,'p',1);
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
			
			/**
			 * Initialize aws
			 */
			$aws = Aws::factory ( array (
					'key' => MemreasConstants::S3_APPKEY,
					'secret' => MemreasConstants::S3_APPSEC,
					'region' => MemreasConstants::S3_REGION
			) );
				
			// S3_Access_User
			$s3 = $aws->get ( 's3' );
			
			// Fetch the policy
			$iam_handle = $aws->get ( 'iam' );
			$iam_policy = $iam_handle->getGroupPolicy ( array (
					// 'PolicyName' => 'S3_Access_Policy' //This policy only allows access to S3 IAM User
					// This policy allows full access to S3
					'GroupName' => 'S3_Access',
					'PolicyName' => 'AmazonS3FullAccess-S3_Access-201302272114' 
			) );
			$iam_policy_array = $iam_policy->toArray ();
			
			$GroupName = ( string ) $iam_policy_array ['GroupName'];
			$PolicyName = ( string ) $iam_policy_array ['PolicyName'];
			$PolicyDocument = ( string ) $iam_policy_array ['PolicyDocument'];
			$PolicyDocument_decode = urldecode ( $PolicyDocument );
			
			// Fetch the security token
			$s3_token = $aws->get ( 'sts' );
			
			// Fetch the session credentials
			$duration = 1800; // 30 minutes
			$response = $s3_token->getFederationToken ( array (
					'Name' => 'S3_Access_User',
					'Policy' => $PolicyDocument_decode,
					'DurationSeconds' => $duration 
			) );
			
			$response_array = $response->toArray ();
			$AccessKeyId = ( string ) $response_array ['Credentials'] ['AccessKeyId'];
			$SecretAccessKey = ( string ) $response_array ['Credentials'] ['SecretAccessKey'];
			$SessionToken = ( string ) $response_array ['Credentials'] ['SessionToken'];
			$Expiration = ( string ) $response_array ['Credentials'] ['Expiration'];
			
			$arr = array (
					'AccessKeyId' => $AccessKeyId,
					'SecretAccessKey' => $SecretAccessKey,
					'SessionToken' => $SessionToken,
					'Expiration' => $Expiration,
					'Duration' => $duration 
			);
			$cdata = '<![CDATA[' . json_encode ( $arr ) . ']]>';
			if ($type == 'xml') {
				header ( "Content-type: text/xml" );
				$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$xml_output .= "<xml>";
				$xml_output .= "<memreas_tvm>$cdata</memreas_tvm>";
				$xml_output .= "</xml>";
				echo $xml_output;
			} else {
				header ( 'Content-Type: application/json' );
				echo json_encode ( $arr );
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . 'error::', $e - getMessage );
		}
	}
}

?>
