<?php

namespace Application\memreas;

use Application\Model\MemreasConstants;

class Mlog {
	
	public static $log;
	
	/**
	 * funtion adds to array for name, value, and outputs
	 *
	 * @param unknown $objname
	 *        	- name of var to be error logged
	 * @param unknown $obj
	 *        	- to be error logged
	 * @param unknown $opt
	 *        	- how to format
	 *        	\n - newline
	 *        	p - print_r($obj)
	 *        	j - json_encode($obj)
	 *        	a - separator (--->)
	 */
	public static function addone($objname, $obj, $opt = '\n') {
		self::add($objname.'---->'.$obj, $opt);
		self::out();
	}
	
	/**
	 * funtion adds to array
	 *
	 * @param unknown $obj
	 *        	- to be error logged
	 * @param unknown $opt
	 *        	- how to format
	 *        	\n - newline
	 *        	p - print_r($obj)
	 *        	j - json_encode($obj)
	 *        	a - separator (--->)
	 */
	public static function add($obj, $opt = '\n') {
		self::$log [] = array (
				'obj' => $obj,
				'opt' => $opt 
		);
	}
	
	/**
	 * function outs to error_log()
	 *
	 * @param unknown $arr
	 *        	- array to be output
	 */
	public static function out() {
		foreach ( self::$log as $item ) {
			$obj = $item ['obj'];
			$opt = $item ['opt'];
			if ($opt == 'j') {
				error_log ( json_encode ( $obj ) . PHP_EOL );
			} else if ($opt == 'a') {
				error_log ( $obj . '...' );
			} else if ($opt == 'e') {
				error_log ( $obj);
			} else if ($opt == 'p') {
				error_log ( print_r ( $obj, true ) . PHP_EOL );
			} else {
				error_log ( $obj . PHP_EOL );
			}
		}// end for
		self::$log = array();
	}
}