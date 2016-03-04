<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

class FetchChameleon {
	public function __construct() {
	}

	public function exec() {
		/***
		 * Checking chameleon against cache 
		 */
		$data = simplexml_load_string ( $_POST ['xml'] );
		if ( $this->checkChameleon($data->fetchchameleon->x_memreas_chameleon) ) {
			$this->setChameleon();
		} else {
			$token_test = "before server_token:: ". $_SESSION ['x_memreas_chameleon']. " ::client_token::" .$data->fetchchameleon->x_memreas_chameleon;	
			$this->setChameleon();
			$token_test = "  after server_token:: ". $_SESSION ['x_memreas_chameleon'];	
		}
	
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<fetchchameleonresponse>";
		$xml_output .= "<status>success</status>";
		$xml_output .= "<x_memreas_chameleon>" . $_SESSION['x_memreas_chameleon'] . "</x_memreas_chameleon>";
		if (isset($token_test)) {
			$xml_output .= "<token_test>".$token_test."</token_test>";
		}
		$xml_output .= "</fetchchameleonresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
	
	public function setChameleon() {
		/**
		 * -
		 * create so set and return
		 */
		$chameleon_value = hash ( 'sha256', uniqid ( '', true ) );
		$_SESSION ['x_memreas_chameleon'] = $chameleon_value;
		//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'exit x_memreas_chameleon--->', $_SESSION ['x_memreas_chameleon'] );
	
		return $_SESSION ['x_memreas_chameleon'];
	}

	public function checkChameleon($x_memreas_chameleon) {
		Mlog::addone ( __CLASS__ . __METHOD__ . '::enter checkChameleon checking ::', '$x_memreas_chameleon->' . $x_memreas_chameleon . '  $_SESSION[x_memreas_chameleon]-->' . $_SESSION ['x_memreas_chameleon'] );
		if ($x_memreas_chameleon == $_SESSION ['x_memreas_chameleon']) {
			return true;
		}
		return false;
	
		/*
			// check chameleon and set new one
			if ($x_memreas_chameleon == $_SESSION ['x_memreas_chameleon']) {
			// user passed token test
			// set a new chameleon
			Mlog::addone ( __CLASS__ . __METHOD__ . '::enter checkChameleon--->', 'passed token test' );
			$this->setChameleon ();
			Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon new value', $_SESSION ['x_memreas_chameleon'] );
			return $_SESSION ['x_memreas_chameleon'];
			}
			// should have equaled - close session;
			Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon--->', 'failed token test' );
			Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon', '0' );
			return 0;
			*/
	}
	
	
	
}
?>
