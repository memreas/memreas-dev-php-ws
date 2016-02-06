<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;
use GuzzleHttp\Client;

class AWSManagerSender {
	private $aws = null;
	private $s3 = null;
	private $bucket = null;
	private $sqs = null;
	private $ses = null;
	private $url = null;
	private $service_locator = null;
	private $dbAdapter = null;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		
		// Fetch aws handle
		$this->aws = MemreasConstants::fetchAWS ();
		
		// Fetch the S3 class
		$this->s3 = $this->aws->createS3 ();
		
		// Fetch the Ses class
		$this->ses = $this->aws->createSqs ();
		
		// Fetch the Ses class
		$this->ses = $this->aws->createSes ();
		
		// Set the bucket
		$this->bucket = MemreasConstants::S3BUCKET;
		
		// Set the backend url
		$this->url = MemreasConstants::MEMREAS_TRANSCODE_URL;
	}
	public function fetchXML($action, $json) {
		$guzzle = new \GuzzleHttp\Client ();
		$response = $guzzle->post ( $this->url, [ 
				'form_params' => [ 
						'action' => $action,
						'json' => $json 
				] 
		] );
		
		return $response->getBody ();
	}
	public function checkIfS3MediaExists($key) {
		try {
			$result = $this->s3->headObject ( array (
					// Bucket is required
					'Bucket' => MemreasConstants::S3BUCKET,
					'Key' => $key 
			) );
			
			// If the file exists we can return result else an exception will be raised
			return $result;
		} catch ( S3Exception $e ) {
			// File doesn't exist
			return false;
		}
	}
	public function pullMediaFromS3($s3file, $file) {
		try {
			Mlog::addone ( __FILE__ . __METHOD__ . '::pulling s3file', $s3file );
			$result = $this->s3->getObject ( array (
					'Bucket' => MemreasConstants::S3BUCKET,
					'Key' => $s3file,
					'SaveAs' => $file 
			) );
			$lsal = shell_exec ( 'ls -al ' . $file );
			// Mlog::addone ( __FILE__ . __METHOD__ . '::finished pullMediaFromS3', $lsal );
			return true;
		} catch ( Exception $e ) {
			throw $e;
		}
	}
	public function snsProcessMediaPublish($message_data) {
		$var = 0;
		$message_data ['memreastranscoder'] = MemreasConstants::MEMREAS_TRANSCODER;
		$json = json_encode ( $message_data );
		error_log ( "INPUT JSON ----> " . $json );
		
		try {
			
			if ((MemreasConstants::MEMREAS_TRANSCODER) && (MemreasConstants::MEMREAS_TRANSCODE_URL)) {
				/*
				 * Publish to web server here
				 */
				Mlog::addone ( 'Publishing to MemreasConstants::MEMREAS_TRANSCODE_URL', MemreasConstants::MEMREAS_TRANSCODE_URL );
				Mlog::addone ( 'json', $json );
				$result = $this->fetchXML ( 'transcode', $json );
				Mlog::addone ( '$result', $result );
			} else if (MemreasConstants::MEMREAS_TRANSCODER) {
				/*
				 * Publish to worker tier here
				 */
				$result = $this->sqs->sendMessage ( array (
						'QueueUrl' => MemreasConstants::QUEUEURL,
						'MessageBody' => $json 
				) );
				Mlog::addone ( 'Just published to MemreasConstants::QUEUEURL', MemreasConstants::QUEUEURL );
				Mlog::addone ( 'json', $json );
			}
		} catch ( \Exception $e ) {
			error_log ( "Caught exception: -------> " . $e->getMessage () . PHP_EOL );
			throw $e;
		}
		
		/*
		 * - call web service here - debugging only /* $guzzle = new Client();
		 * error_log("Inside fetchXML this->url " .
		 * MemreasConstants::MEMREAS_TRANSCODER_URL . PHP_EOL); $request =
		 * $guzzle->post( MemreasConstants::MEMREAS_TRANSCODER_URL, null, array(
		 * 'action' => 'transcoder', 'guzzle' => '1', 'json' => $json , ) );
		 * $request->addHeader('x-amz-sns-message-type', 'Notification');
		 * $result = $request->send(); error_log("Passed guzzle result ---->" .
		 * print_r($result, true) . PHP_EOL ); /*
		 */
		
		return $result;
	}
	public function webserviceUpload($user_id, $dirPath, $file_name, $content_type) {
		$s3_data = array ();
		$s3_data ['s3path'] = "$user_id/";
		$s3_data ['s3file_name'] = $file_name;
		$s3_data ['s3file'] = $s3_data ['s3path'] . $file_name;
		$file = $dirPath . $file_name;
		
		// Modified - Perform the upload to S3. Abort the upload if something
		// goes wrong
		$uploader = new MultipartUploader ( $this->s3, $file, [ 
				'bucket' => $this->bucket,
				'key' => $s3_data ['s3file'],
				'Content-Type' => $content_type,
				'ServerSideEncryption' => 'AES256' 
		] );
		// 'CacheControl' => 'max-age=3600',
		// 'x-amz-storage-class' => 'REDUCED_REDUNDANCY'
		
		try {
			$result = $uploader->upload ();
			// echo "Upload complete: {$result['ObjectURL'}\n";
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUpload worked::", $result );
		} catch ( MultipartUploadException $e ) {
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "::MultiPartUploadException::", $e->getMessage () );
		}
		
		// $body = EntityBody::factory(fopen($file, 'r+'));
		// $uploader = UploadBuilder::newInstance()->setClient($this->s3)
		// ->setSource($body)
		// ->setBucket($this->bucket)
		// ->setMinPartSize(10 * Size::MB)
		// ->setOption('ContentType', $content_type)
		// ->setKey($s3_data['s3file'])
		// ->build();
		
		// try {
		// $uploader->upload();
		// } catch (MultipartUploadException $e) {
		// $uploader->abort();
		// error_log(
		// "AWSManagerSender.webserviceUpload uploader error: " . $e .
		// PHP_EOL);
		// }
		
		return $s3_data;
	}
	public function sendSeSMail($to_array, $subject, $html) {
		$from = MemreasConstants::ADMIN_EMAIL;
		$client = $this->ses;
		
		$result = $client->sendEmail ( array (
				// Source is required
				'Source' => $from,
				// Destination is required
				'Destination' => array (
						'ToAddresses' => $to_array,
						'CcAddresses' => array (),
						'BccAddresses' => array () 
				),
				// Message is required
				'Message' => array (
						// Subject is required
						'Subject' => array (
								// Data is required
								'Data' => $subject 
						),
						// 'Charset' => 'iso-8859-1'
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
		// error_log("email result ---> ".$result.PHP_EOL);
	}
	public function __get($name) {
		return $this->$name;
	}
}

?>


