<?php
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
	

// setup vars
$time = time ();
$expires = $time + 36000;
//$url = 'https://d35oo0o186bbvt.cloudfront.net/3f68e4a4-74bc-4c2d-bf5c-09f8fd501b7d/394e4d63-22b1-45ce-bc04-0595a890a051/hls/VID_20141115_181326.testFont.m3u8';
$url = 'https://d35oo0o186bbvt.cloudfront.net/*';
$domain = 'memreas.com';
$customPolicy = '{"Statement":[{"Resource":"' . $url . '","Condition":{"DateLessThan":{"AWS:EpochTime":' . $expires . '}}}]}';
$encodedCustomPolicy = url_safe_base64_encode ( $customPolicy );
$customPolicySignature = rsa_sha1_sign ( $customPolicy, '/Users/johnmeah/AWS/memreasgit/memreas-dev-php-ws/key/pk-APKAISSKGZE3DR5HQCHA.pem' );
$customPolicySignature = url_safe_base64_encode ( $customPolicySignature );

$cmd = "curl -v -H 'Cookie: CloudFront-Policy=" . $encodedCustomPolicy . "' -H 'Cookie: CloudFront-Signature=" .  $customPolicySignature . "' -H 'Cookie: CloudFront-Key-Pair-Id=APKAISSKGZE3DR5HQCHA' https://d35oo0o186bbvt.cloudfront.net/3f68e4a4-74bc-4c2d-bf5c-09f8fd501b7d/394e4d63-22b1-45ce-bc04-0595a890a051/hls/VID_20141115_181326.testFont.m3u8";

echo $cmd . PHP_EOL;
echo "about to run cmd...\n";
echo shell_exec($cmd);


