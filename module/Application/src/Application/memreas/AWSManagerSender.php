<?php

namespace Application\memreas;

use Guzzle\Http\Client;
use Guzzle\Http\EntityBody;
use Aws\Common\Aws;
use Aws\Common\Enum\Size;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;
use Aws\ElasticTranscoder\ElasticTranscoderClient;
use PHPImageWorkshop\ImageWorkshop;
use Application\Model\MemreasConstants;

error_reporting ( E_ALL & ~ E_NOTICE );
class AWSManagerSender {
	private $aws = null;
	private $s3 = null;
	private $bucket = null;
	private $sns = null;
	private $ses = null;
	private $ses = null;
	private $topicArn = null;
	private $elasticache = null;
	private $awsTranscode = null;
	private $service_locator = null;
	private $dbAdapter = null;
	public function __construct($service_locator) {
		// print "In AWSManagerSender constructor <br>";
		error_log ( "Inside AWSManagerSender contructor...", 0 );
		
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->aws = Aws::factory ( array (
				'key' => 'AKIAJMXGGG4BNFS42LZA',
				'secret' => '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',
				'region' => 'us-east-1' 
		) );
		
		// Fetch the S3 class
		$this->s3 = $this->aws->get ( 's3' );
		
		// Fetch the AWS Elastic Transcoder class
		$this->awsTranscode = $this->aws->get ( 'ElasticTranscoder' );
		
		// Set the bucket
		$this->bucket = MemreasConstants::S3BUCKET;
		
		// Fetch the SNS class
		$this->sns = $this->aws->get ( 'sns' );
		
		// Fetch the SNS class
		$this->ses = $this->aws->get ( 'ses' );
		
		// Fetch the
		$this->elasticache = $this->aws->get ( 'ElastiCache' );
		
		// Set the topicArn
		$this->topicArn = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
		
		error_log ( "Exit AWSManagerSender constructor", 0 );
		// print "Exit AWSManagerSender constructor <br>";
	}
	public function snsProcessMediaPublish($message_data) {
		$var = 0;
		$json = json_encode ( $message_data );
		error_log ( "INPUT JSON ----> " . $json );
		
		try {
			/* - publish to topic here */
			$result = $this->sns->publish ( array (
					'TopicArn' => $this->topicArn,
					'Message' => $json,
					'Subject' => 'snsProcessMediaPublish' 
			) );
		} catch ( Exception $e ) {
			error_log ( "Caught exception: -------> " . print_r ( $e->getMessage (), true ) . PHP_EOL );
			throw $e;
		}
		
		/*
		 * - call web service here - debugging only /* $guzzle = new Client(); error_log("Inside fetchXML this->url " . MemreasConstants::MEMREAS_TRANSCODER_URL . PHP_EOL); $request = $guzzle->post( MemreasConstants::MEMREAS_TRANSCODER_URL, null, array( 'action' => 'transcoder', 'guzzle' => '1', 'json' => $json , ) ); $request->addHeader('x-amz-sns-message-type', 'Notification'); $result = $request->send(); error_log("Passed guzzle result ---->" . print_r($result, true) . PHP_EOL ); /*
		 */
		
		return $result;
	}
	public function webserviceUpload($user_id, $dirPath, $file_name, $content_type) {
		$s3_data = array ();
		$s3_data ['s3path'] = "$user_id/images/";
		$s3_data ['s3file_name'] = $file_name;
		$s3_data ['s3file'] = $s3_data ['s3path'] . $file_name;
		$file = $dirPath . $file_name;
		error_log ( "file ---> $file" . PHP_EOL );
		$body = EntityBody::factory ( fopen ( $file, 'r+' ) );
		$uploader = UploadBuilder::newInstance ()->setClient ( $this->s3 )->setSource ( $body )->setBucket ( $this->bucket )->setMinPartSize ( 10 * Size::MB )->setOption ( 'ContentType', $content_type )->setKey ( $s3_data ['s3file'] )->build ();
		
		// Modified - Perform the upload to S3. Abort the upload if something goes wrong
		try {
			$uploader->upload ();
		} catch ( MultipartUploadException $e ) {
			$uploader->abort ();
			error_log ( "AWSManagerSender.webserviceUpload uploader error: " . $e . PHP_EOL );
		}
		
		return $s3_data;
	}
	public function sendSeSMail($to, $subject, $html) {
		$from = 'kamleshpawar2000@yahoo.com';
		$client = $this->ses;
		
		$result = $client->sendEmail ( array (
				// Source is required
				'Source' => $from,
				// Destination is required
				'Destination' => array (
						'ToAddresses' => array (
								$to 
						),
						'CcAddresses' => array (),
						'BccAddresses' => array () 
				),
				// Message is required
				'Message' => array (
						// Subject is required
						'Subject' => array (
								// Data is required
								'Data' => $subject,
								'Charset' => 'iso-8859-1' 
						),
						// Body is required
						'Body' => array (
								'Text' => array (
										// Data is required
										'Data' => $html,
										'Charset' => 'iso-8859-1' 
								),
								'Html' => array (
										// Data is required
										'Data' => $html,
										'Charset' => 'iso-8859-1' 
								) 
						) 
				),
				'ReplyToAddresses' => array (
						$from 
				),
				'ReturnPath' => $from 
		) );
	}
	
	/*
	 * TODO: Add ElastiCache connectivity
	 */
	public function connect() {
		/**
		 * Sample PHP code to show how to integrate with the Amazon ElastiCcache
		 * Auto Discovery feature.
		 */
		
		/* Configuration endpoint to use to initialize memcached client. This is only an example. */
		$server_endpoint = MemreasConstants::ELASTICACHE_SERVER_ENDPOINT;
		/* Port for connecting to the ElastiCache cluster. This is only an example */
		$server_port = MemreasConstants::ELASTICACHE_SERVER_PORT;
		
error_log("Set endpoint $server_endpoint".PHP_EOL);		
error_log("Set port $server_port".PHP_EOL);		
		
		/**
		 * The following will initialize a Memcached client to utilize the Auto Discovery feature.
		 *
		 * By configuring the client with the Dynamic client mode with single endpoint, the
		 * client will periodically use the configuration endpoint to retrieve the current cache
		 * cluster configuration. This allows scaling the cache cluster up or down in number of nodes
		 * without requiring any changes to the PHP application.
		 */
		
		$dynamic_client = new Memcached ();
error_log("Created new Memcached client..".PHP_EOL);
		$dynamic_client->setOption ( Memcached::OPT_CLIENT_MODE, Memcached::DYNAMIC_CLIENT_MODE );
		$dynamic_client->addServer ( $server_endpoint, $server_port );
		
		$dynamic_client->set ( 'LAST-USER-ID-ACCESS', 'user_name', 3600 ); // Store the data for 1 hour in the cluster, the client will decide which node to store
		
		/**
		 * Configuring the client with Static client mode disables the usage of Auto Discovery
		 * and the client operates as it did before the introduction of Auto Discovery.
		 * The user
		 * can then add a list of server endpoints.
		 */
		
		$static_client = new Memcached ();
		$static_client->setOption ( Memcached::OPT_CLIENT_MODE, Memcached::STATIC_CLIENT_MODE );
		$static_client->addServer ( $server_endpoint, $server_port );

		
		//Connected at this point
error_log("Connected to elasticache client!".PHP_EOL);		
		
		
		$static_client->set ( 'key', 'value' ); // Store the data in the cluster without expiration
	}
}

?>


