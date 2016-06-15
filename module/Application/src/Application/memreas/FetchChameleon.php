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
		/**
		 * *
		 * Checking chameleon against cache
		 */
		$data = simplexml_load_string ( $_POST ['xml'] );
		
		$result = $this->checkChameleon ( $data->fetchchameleon->x_memreas_chameleon );
		if (! $result) {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::X_MEMREAS_CHAMELEON FAILURE check::action::', $action . ' ::$this->x_memreas_chameleon->' . $this->x_memreas_chameleon );
			Mlog::addone ( __CLASS__ . __METHOD__ . '::X_MEMREAS_CHAMELEON FAILURE check::$_SERVER[x_memreas_chameleon]->', $_SERVER ['x_memreas_chameleon'] );
		}
		/**
		 * Always set new chameleon - keep last 3
		 */
		$this->setChameleon ();
		
		return $result;
	}
	public function setChameleon() {
		/**
		 * -
		 * create chameleon and add to array - keep last 3 given async calls
		 */
		$chameleon_value = hash ( 'sha256', uniqid ( '', true ) );
		$_SESSION ['x_memreas_chameleon'] = $chameleon_value;
		/*
		 * if (isset ( $_SESSION ['x_memreas_chameleon'] )) {
		 * $x_memreas_chameleonArr = $_SESSION ['x_memreas_chameleon'];
		 * } else {
		 * $x_memreas_chameleonArr = [];
		 * }
		 *
		 * if (! is_array ( $x_memreas_chameleonArr )) {
		 * // first time so create array
		 * $x_memreas_chameleonArr = [];
		 * $x_memreas_chameleonArr [] = $_SESSION ['x_memreas_chameleon'];
		 * }
		 * if (count ( $x_memreas_chameleonArr ) > 3) {
		 * unset ( $x_memreas_chameleonArr [0] );
		 * $x_memreas_chameleonArr = array_values ( $x_memreas_chameleonArr );
		 * $x_memreas_chameleonArr [] = $chameleon_value;
		 * $_SESSION ['x_memreas_chameleon'] = $x_memreas_chameleonArr;
		 * } else {
		 * // < 3
		 * $x_memreas_chameleonArr [] = $chameleon_value;
		 * $_SESSION ['x_memreas_chameleon'] = $x_memreas_chameleonArr;
		 * }
		 */
		
		return $chameleon_value;
	}
	public function checkChameleon($client_x_memreas_chameleon) {
		// Mlog::addone ( __CLASS__ . __METHOD__ . '::enter checkChameleon checking ::', '$client_x_memreas_chameleon->' . $client_x_memreas_chameleon . ' $_SESSION[x_memreas_chameleon]-->' . $_SESSION ['x_memreas_chameleon'] );
		$x_memreas_chameleonArr = $_SESSION ['x_memreas_chameleon'];
		// only check if count > 0
		if (count ( $x_memreas_chameleonArr ) > 0) {
			foreach ( $x_memreas_chameleonArr as $x_memreas_chameleon ) {
				if ($x_memreas_chameleon == $client_x_memreas_chameleon) {
					return true;
				}
			}
		} else if (! empty ( $actionname ) && ($actionname == 'login')) {
			// if count 0 then we should be in login
			return true;
		}
		return false;
		
		/*
		 * // check chameleon and set new one
		 * if ($x_memreas_chameleon == $_SESSION ['x_memreas_chameleon']) {
		 * // user passed token test
		 * // set a new chameleon
		 * Mlog::addone ( __CLASS__ . __METHOD__ . '::enter checkChameleon--->', 'passed token test' );
		 * $this->setChameleon ();
		 * Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon new value', $_SESSION ['x_memreas_chameleon'] );
		 * return $_SESSION ['x_memreas_chameleon'];
		 * }
		 * // should have equaled - close session;
		 * Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon--->', 'failed token test' );
		 * Mlog::addone ( __CLASS__ . __METHOD__ . '::exit checkChameleon', '0' );
		 * return 0;
		 */
	}
}
?>
