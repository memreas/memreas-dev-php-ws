<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

class LogOut {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct() {
	}
	public function exec($sessHandler) {
		//error_log ( 'IndexController -> logout->exec()...' . PHP_EOL );
		try {
			ob_clean();
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<logoutresponse>";
			$xml_output .= "<status>Success</status><message>Log out success</message><logout>1</logout>";
			
			$xml_output .= "</logoutresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
			if (! empty ( $_SESSION ['memreascookie'] )) {
				$sessHandler->closeSessionWithMemreasCookie ();
			} else {
				$sessHandler->closeSessionWithSID ();
			}
			//error_log ( "Logut ---> xml_output ----> " . $xml_output . PHP_EOL );
		} catch ( \Exception $e ) {
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
	}
}
?>
