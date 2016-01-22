<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;
use Application\Model\MemreasConstants;

class GenerateMediaId {
	public function exec() {
		$cm = __CLASS__ . __METHOD__;
		$data = simplexml_load_string ( $_POST ['xml'] );
		Mlog::addone ( $cm . '::$data---->', $data, 'p' );
		Mlog::addone ( $cm . '::$data->media_id_batch---->', $data->media_id_batch );
		
		if (!empty($data->media_id_batch)) {
			$media_id_batch = array();
			for($i=0;$i<=MemreasConstants::media_id_batch_create_count; $i++) {
				$media_id_batch[] = MUUID::fetchUUID ();
			}
			$media_id_output = '<media_id_batch>' . json_encode($media_id_batch) . '<media_id_batch>';
		} else {
			$media_id_output = '';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<generatemediaidresponse>";
		$xml_output .= "<status>success</status>";
		$xml_output .= "<media_id>" . MUUID::fetchUUID () . "</media_id>";
		$xml_output .= $media_id_output;
		$xml_output .= "</generatemediaidresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		Mlog::addone ( $cm . '::$xml_output---->', $xml_output );
		
	}
}
?>
