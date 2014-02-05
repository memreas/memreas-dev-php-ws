<?php
error_reporting ( E_ALL );
ini_set ( 'display_errors', 'On' );
require 'config.php';
function xml2array($xmlstring) {
	$xml = simplexml_load_string ( $xmlstring );
	$json = json_encode ( $xml );
	$arr = json_decode ( $json, TRUE );
	
	return $arr;
}
function array2xml($array, $xml = false) {
	if ($xml === false) {
		$xml = new SimpleXMLElement ( '<?xml version=\'1.0\' encoding=\'utf-8\'?><' . key ( $array ) . '/>' );
		$array = $array [key ( $array )];
	}
	foreach ( $array as $key => $value ) {
		if (is_array ( $value )) {
			array2xml ( $value, $xml->addChild ( $key ) );
		} else {
			$xml->addChild ( $key, $value );
		}
	}
	return $xml->asXML ();
}

error_log ( print_r ( $_REQUEST, true ) );

// Added to support template pages - ajax calls need to be jsonp
$callback = "";
if (isset ( $_GET ['json'] )) {
	$callback = $_GET ['callback'];
	$json_input = $_GET ['json'];
	$jsonArr = json_decode ( $json_input, true );
	$actionname = $jsonArr ['action'];
	$type = $jsonArr ['type'];
	$json2xml = $jsonArr ['json'];
	
	$xml_converted = array2xml ( $json2xml );
	error_log ( "XML output from function... " . $xml_converted );
	$_POST ['xml'] = $xml_converted;
	// Capture the echo from the includes in case we need to convert back to json
	ob_start ();
} else {
	$actionname = strtoupper ( $_REQUEST ['action'] );
}

error_log ( "Inside index_json.php actionname .... $actionname" );

if (strtoupper ( $actionname ) == strtoupper ( "login" )) {
	include 'login.php';
} else if (strtoupper ( $actionname ) == strtoupper ( "registration" )) {
	include 'registration.php';
} else if (strtoupper ( $actionname ) == strtoupper ( "addphoto" )) {
	include 'addphoto.php';
} else if (strtoupper ( $actionname ) == strtoupper ( "forgetpassword" )) {
	include 'forgetpassword.php';
} elseif (strtoupper ( $actionname ) == strtoupper ( 'changepassword' )) {
	include 'changepassword.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'listphotos' )) {
	include 'listphotos.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'logout' )) {
	include 'logout.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'uploadphoto' )) {
	include 'uploadphoto.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'uploadmedia' )) {
	include 'uploadmedia.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'deletephoto' )) {
	include 'deletephoto.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'addevent' )) {
	include 'addevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'checkusername' )) {
	include 'checkusername.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'editevent' )) {
	include 'editevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'viewevents' )) {
	include 'viewevents.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'likemedia' )) {
	include 'likemedia.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'listallmedia' )) {
	include 'listallmedia.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'countlistallmedia' )) {
	include 'countlistallmedia.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'creategroup' )) {
	include 'creategroup.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'listgroup' )) {
	include 'listgroup.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'mediainappropriate' )) {
	include 'mediainappropriate.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'addcomments' )) {
	include 'addcomments.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'addmediatoevent' )) {
	include 'addmediatoevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'addmediaevent' )) {
	include 'addmediaevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'viewallfriends' )) {
	include 'viewallfriends.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'countviewallfriends' )) {
	include 'countviewallfriends.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'addfriendtoevent' )) {
	include 'addfriendtoevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'countviewevent' )) {
	include 'countviewevent.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'snsProcessMediaPublish' )) {
	include 'snsProcessMediaPublish.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'download' )) {
	include 'download.php';
} else if (strtoupper ( $actionname ) == strtoupper ( 'viewmediadetails' )) {
	include 'viewmediadetails.php';
}

if (isset ( $_GET ['json'] )) {
	$output = ob_get_clean ();
	error_log ( "Final Output ----> " . $output );
	$xml = simplexml_load_string ( $output );
	$json = json_encode ( $xml );
	error_log ( "Final JSON ----> " . $json );
	error_log ( "Callback ---> " . $callback . "(" . $json . ")" );
	header ( "Content-type: plain/text" );
	echo $callback . "(" . $json . ")";
}

?>
