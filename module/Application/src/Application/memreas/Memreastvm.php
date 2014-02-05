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
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( MemreasConstants::MEMREASDB );
	}
	public function exec() {
		// S3_Access_User
		$aws = Aws::factory ( array (
				'key' => 'AKIAJMXGGG4BNFS42LZA',
				'secret' => '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' 
		// 'region' => Region::us_east_1
				) );
		
		// Fetch the policy
		$iam_handle = $aws->get ( 'iam' );
		$iam_policy = $iam_handle->getGroupPolicy ( array (
				'GroupName' => 'S3_Access',
				// 'PolicyName' => 'S3_Access_Policy' //This policy only allows access to memreasdev
				'PolicyName' => 'AmazonS3FullAccess-S3_Access-201302272114'  // This policy allows full access to S3
		 );
		$iam_policy_array = $iam_policy->toArray ();
		
		$GroupName = ( string ) $iam_policy_array ['GroupName'];
		$PolicyName = ( string ) $iam_policy_array ['PolicyName'];
		$PolicyDocument = ( string ) $iam_policy_array ['PolicyDocument'];
		$PolicyDocument_decode = urldecode ( $PolicyDocument );
		
		// Fetch the security token
		$s3_token = $aws->get ( 'sts' );
		
		// Fetch the session credentials
		$response = $s3_token->getFederationToken ( array (
				'Name' => 'S3_Access_User',
				'Policy' => $PolicyDocument_decode,
				'DurationSeconds' => 3600  //1 hour
		)// 1 hour
		null );
		
		$response_array = $response->toArray ();
		$AccessKeyId = ( string ) $response_array ['Credentials'] ['AccessKeyId'];
		$SecretAccessKey = ( string ) $response_array ['Credentials'] ['SecretAccessKey'];
		$SessionToken = ( string ) $response_array ['Credentials'] ['SessionToken'];
		$Expiration = ( string ) $response_array ['Credentials'] ['Expiration'];
		
		$arr = array (
				'AccessKeyId' => $AccessKeyId,
				'SecretAccessKey' => $SecretAccessKey,
				'SessionToken' => $SessionToken,
				'Expiration' => $Expiration 
		);
		
		header ( 'Content-Type: application/json' );
		echo json_encode ( $arr );
	}
}

?>
