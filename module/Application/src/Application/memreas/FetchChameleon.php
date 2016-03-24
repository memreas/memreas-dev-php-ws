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

		$result = $this->checkChameleon($data->fetchchameleon->x_memreas_chameleon);
		if (!$result) {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::X_MEMREAS_CHAMELEON FAILURE check::action::', $action . ' ::$this->x_memreas_chameleon->' . $this->x_memreas_chameleon );
			Mlog::addone ( __CLASS__ . __METHOD__ . '::X_MEMREAS_CHAMELEON FAILURE check::$_SERVER[x_memreas_chameleon]->' , $_SERVER['x_memreas_chameleon'] );
		}
		/**
		 * Always set new chameleon - keep last 3
		 */
		$this->setChameleon();
		
		return $result;
	}
	
	public function setChameleon() {
		/**
		 * -
		 * create chameleon and add to array - keep last 3 given async calls
		 */
		$chameleon_value = hash ( 'sha256', uniqid ( '', true ) );
		$x_memreas_chameleonArr = $_SESSION ['x_memreas_chameleon'];
		$_SESSION ['x_memreas_chameleon'][] = $chameleon_value;
		
		if (count($_SESSION ['x_memreas_chameleon']) > 3) {
			unset($_SESSION ['x_memreas_chameleon'][0]);
			$_SESSION ['x_memreas_chameleon'] = array_values($_SESSION ['x_memreas_chameleon']);
		}
		return $chameleon_value;
	}

	public function checkChameleon($client_x_memreas_chameleon) {
		Mlog::addone ( __CLASS__ . __METHOD__ . '::enter checkChameleon checking ::', '$client_x_memreas_chameleon->' . $client_x_memreas_chameleon . '  $_SESSION[x_memreas_chameleon]-->' . $_SESSION ['x_memreas_chameleon'] );
		$x_memreas_chameleonArr = $_SESSION ['x_memreas_chameleon'];
		foreach ($x_memreas_chameleonArr as $x_memreas_chameleon) {
			if ($x_memreas_chameleon == $client_x_memreas_chameleon) {
				return true;
			}
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
