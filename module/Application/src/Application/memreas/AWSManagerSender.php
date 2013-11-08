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

error_reporting(E_ALL & ~E_NOTICE);

class AWSManagerSender {

    private $aws = null;
    private $s3 = null;
    private $bucket = null;
    private $sns = null;
    private $ses = null;
    private $topicArn = null;
    private $awsTranscode = null;
    private $service_locator = null;
    private $dbAdapter = null;

    public function __construct($service_locator) {
        //print "In AWSManagerSender constructor <br>";
        error_log("Inside AWSManagerSender contructor...", 0);

		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        $this->aws = Aws::factory(array(
                    'key' => 'AKIAJMXGGG4BNFS42LZA',
                    'secret' => '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',
                    'region' => 'us-east-1'
        ));

        //Fetch the S3 class
        $this->s3 = $this->aws->get('s3');

        //Fetch the AWS Elastic Transcoder class
        $this->awsTranscode = $this->aws->get('ElasticTranscoder');

        //Set the bucket
        $this->bucket = "memreasdev";

        //Fetch the SNS class
        $this->sns = $this->aws->get('sns');

        //Fetch the SNS class
        $this->ses = $this->aws->get('ses');

        //Set the topicArn
        $this->topicArn = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';

        error_log("Exit AWSManagerSender constructor", 0);
        //print "Exit AWSManagerSender constructor <br>";
    }

    function snsProcessMediaPublish($message_data) {

		$var=0;
        $json = json_encode($message_data);
        error_log("INPUT JSON ----> " . $json);

		try {
			/* - publish to topic here */
			$hello = "world";
			$result = $this->sns->publish(array(
			   'TopicArn' => $this->topicArn,
				'Message'  => $json,
				'Subject'  => 'snsProcessMediaPublish',
				));
			/**/
			
		} catch (Exception $e) {
			error_log("Caught exception: -------> " . print_r($e->getMessage(), true) . PHP_EOL);
			throw $e;
		}
        
		/* - call web service here - debugging only /*
		$guzzle = new Client();
		error_log("Inside fetchXML this->url " . MemreasConstants::MEMREAS_TRANSCODER_URL . PHP_EOL);
		$request = $guzzle->post(
			MemreasConstants::MEMREAS_TRANSCODER_URL, 
			null, 
			array(
				'action' => 'transcoder',
				'guzzle' => '1',
				'json' => $json ,
	    	)
		);
		$request->addHeader('x-amz-sns-message-type', 'Notification');
		$result = $request->send();
		error_log("Passed guzzle result ---->" . print_r($result, true) . PHP_EOL );
        /**/

        return $result;
    }

     public function webserviceUpload($user_id, $dirPath, $file_name, $content_type) {

		$s3_data = array();
		$s3_data['s3path'] = "$user_id/images/";
		$s3_data['s3file_name'] = $file_name;
		$s3_data['s3file'] = $s3_data['s3path'] . $file_name;
		$file = $dirPath . $file_name;
error_log("file ---> $file" . PHP_EOL);
        $body = EntityBody::factory(fopen($file, 'r+'));
		$uploader = UploadBuilder::newInstance()
                ->setClient($this->s3)
                ->setSource($body)
                ->setBucket($this->bucket)
                ->setMinPartSize(10 * Size::MB)
                ->setOption('ContentType', $content_type)
                ->setKey($s3_data['s3file'])
                ->build();

        //  Modified - Perform the upload to S3. Abort the upload if something goes wrong
        try {
            $uploader->upload();
        } catch (MultipartUploadException $e) {
            $uploader->abort();
			error_log("AWSManagerSender.webserviceUpload uploader error: " . $e . PHP_EOL);
        }

        return $s3_data;
    }
}

?>

