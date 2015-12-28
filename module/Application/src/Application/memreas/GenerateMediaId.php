<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;

class GenerateMediaId {
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<generatemediaidresponse>";
		$xml_output .= "<status>success</status>";
		$xml_output .= "<media_id>" . MUUID::fetchUUID () . "</media_id>";
		$xml_output .= "</generatemediaidresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}
?>
