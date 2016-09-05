<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */

ini_set("log_errors", 1);
ini_set("error_log", "php_errors.log");

function curlUrl($url, $action, $xml) {

	// This is the data to POST to the form. The KEY of the array is the name of the field. The value is the value posted.
	$data_to_post = array();
	$data_to_post['action'] = $action;
	$data_to_post['xml'] = $xml;
	
	// Initialize cURL
	$curl = curl_init();
	
	// Set the options
	curl_setopt($curl,CURLOPT_URL, $url);
	
	// This sets the number of fields to post
	curl_setopt($curl,CURLOPT_POST, sizeof($data_to_post));
	
	// This is the fields to post in the form of an array.
	curl_setopt($curl,CURLOPT_POSTFIELDS, $data_to_post);
	
	//execute the post
	$result = curl_exec($curl);
	
	//close the connection
	curl_close($curl);
	
	return $result;
}

$xml = '<xml>
			<login>
				<username>glenntest1</username>
				<password>1234567890</password>
				<device_type>web</device_type>
				<device_id>127.0.0.1</device_id>
			</login>
		</xml>';

//dev 
$url = 'https://memreasdev-wsa.memreas.com/index?action=';
$action = 'login';
error_log("starting dev login..." . PHP_EOL);
//echo $url;
//echo $action;
$result = curlUrl($url, $action, $xml);
error_log('$result-->' . $result . PHP_EOL);
error_log("completed dev login..." . PHP_EOL);


//dev 
$url = 'https://memreasprod-ws.memreas.com/index?action=';
$action = 'login';
error_log("starting prod login..." . PHP_EOL);
//echo $url;
//echo $action;
$result = curlUrl($url, $action, $xml);
error_log('$result-->' . $result . PHP_EOL);
error_log("completed prod login..." . PHP_EOL);
error_log("COMPLETED HOURLY LOGIN" . PHP_EOL);

