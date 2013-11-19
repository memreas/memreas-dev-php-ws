<?php

namespace Application\memreas;
 use Aws\Common\Aws;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\UUID;
//use Application\memreas\PostPolicy
class Memreastvm {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		$this->key = 'AKIAJMXGGG4BNFS42LZA';
        $this->secret = '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';
		$this->private_key_filename = getcwd().'/key/pk-S3AccessUser-key.pem';
		$this->key_pair_id = 'VOCBNKDCW72JC2ZCP3FCJEYRGPS2HCVQ';
		$this->expires = time() + 360000; // 100 hour from now
		$this->signature_encoded = null;
		$this->policy_encoded = null;
    }

    public function exec() {
		//S3_Access_User
		$aws = Aws::factory(array(
		   'key'    => 'AKIAJMXGGG4BNFS42LZA',
		   'secret' => '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'
		   //'region' => Region::us_east_1
		));

		//Fetch the policy
		$iam_handle = $aws->get('iam');
		$iam_policy = $iam_handle->getGroupPolicy(array(
			'GroupName' => 'S3_Access',
			//'PolicyName' => 'S3_Access_Policy' //This policy only allows access to memreasdev
			'PolicyName' => 'AmazonS3FullAccess-S3_Access-201302272114'  //This policy allows full access to S3
		));
		$iam_policy_array = $iam_policy->toArray();

		$GroupName = (string)$iam_policy_array['GroupName'];
		$PolicyName = (string)$iam_policy_array['PolicyName'];
		$PolicyDocument = (string)$iam_policy_array['PolicyDocument'];
		$PolicyDocument_decode = urldecode($PolicyDocument);

		//Fetch the security token
		$s3_token = $aws->get('sts');

		// Fetch the session credentials
		$response = $s3_token->getFederationToken(array(
			'Name' => 'S3_Access_User',
			'Policy' => $PolicyDocument_decode,
			'DurationSeconds' => 3600 //1 hour
		));

		$response_array = $response->toArray();
		$AccessKeyId = (string)$response_array['Credentials']['AccessKeyId'];
		$SecretAccessKey = (string)$response_array['Credentials']['SecretAccessKey'];
		$SessionToken = (string)$response_array['Credentials']['SessionToken'];

		
		//base64_encode(utf8_encode(preg_replace('/\s\s+|\\f|\\n|\\r|\\t|\\v/', '', $PolicyDocument_decode)));
		/*
		//Encode the policy and signature
		$this->policy_encoded = $this->url_safe_base64_encode($PolicyDocument);
		// sign the original policy, not the encoded version
		$signature = $this->rsa_sha1_sign($PolicyDocument, $this->private_key_filename);
		// make the signature safe to be included in a url
		$this->signature_encoded  = $this->url_safe_base64_encode($signature);
		*/
		
		$arr = array(
			'AccessKeyId' => $AccessKeyId, 
			'SecretAccessKey' => $SecretAccessKey, 
			'SessionToken' => $SessionToken,
			'EncodedPolicy' => $this->url_safe_base64_encode($PolicyDocument_decode),
			'EncodedSignature' =>  base64_encode(hash_hmac('sha1', $PolicyDocument, $this->secret, true))
		);

		header('Content-Type: application/json');
		echo json_encode($arr);
    }

    /*
     * Calculate HMAC-SHA1 according to RFC2104
    * See http://www.faqs.org/rfcs/rfc2104.html
    */
    function hmacsha1($key,$data) {
    	$blocksize=64;
    	$hashfunc='sha1';
    	if (strlen($key)>$blocksize)
    		$key=pack('H*', $hashfunc($key));
    	$key=str_pad($key,$blocksize,chr(0x00));
    	$ipad=str_repeat(chr(0x36),$blocksize);
    	$opad=str_repeat(chr(0x5c),$blocksize);
    	$hmac = pack(
    			'H*',$hashfunc(
    					($key^$opad).pack(
    							'H*',$hashfunc(
    									($key^$ipad).$data
    
    							)
    					)
    			)
    	);
    	return bin2hex($hmac);
    }
    
    /*
     * Used to encode a field for Amazon Auth
    * (taken from the Amazon S3 PHP example library)
    */
    function hex2b64($str)
    {
    	$raw = '';
    	for ($i=0; $i < strlen($str); $i+=2)
    	{
    		$raw .= chr(hexdec(substr($str, $i, 2)));
    	}
    	return base64_encode($raw);
    }    
    
    function rsa_sha1_sign($policy, $private_key_filename) {
    	$signature = "";
    
    	// load the private key
    	$fp = fopen($private_key_filename, "r");
    	$priv_key = fread($fp, 8192);
    	fclose($fp);
    	$pkeyid = openssl_get_privatekey($priv_key);
    
    	// compute signature
    	openssl_sign($policy, $signature, $pkeyid);
    
    	// free the key from memory
    	openssl_free_key($pkeyid);
    
    	return $signature;
    }
    
    function url_safe_base64_encode($value) {
    	$encoded = base64_encode($value);
    	// replace unsafe characters +, = and / with the safe characters -, _ and ~
    	return str_replace(
    			array('+', '=', '/'),
    			array('-', '_', '~'),
    			$encoded);
    }    

    
}

?>
