<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use \Exception;

class Utility {
	protected static $service_locator;
	protected static $catch;
	protected static $dbAdapter;
	public static $item;
	protected static $collection;
	public static function collect() {
		self::$collection [] = self::$item;
		self::$item = array ();
	}
	public static function isValidTimeStamp($timestamp) {
		return (( string ) ( int ) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~ PHP_INT_MAX);
	}
	public static function toDateTime($unixTimestamp) {
		// examples...
		// $today = date("F j, Y, g:i a"); // March 10, 2001, 5:16 pm
		// $today = date("m.d.y"); // 03.10.01
		// $today = date("j, n, Y"); // 10, 3, 2001
		// $today = date("Ymd"); // 20010310
		// $today = date('h-i-s, j-m-y, it is w Day'); // 05-16-18, 10-03-01, 1631 1618 6 Satpm01
		// $today = date('\i\t \i\s \t\h\e jS \d\a\y.'); // it is the 10th day.
		// $today = date("D M j G:i:s T Y"); // Sat Mar 10 17:16:18 MST 2001
		// $today = date('H:m:s \m \i\s\ \m\o\n\t\h'); // 17:03:18 m is month
		// $today = date("H:i:s"); // 17:16:18
		// $today = date("Y-m-d H:i:s"); // 2001-03-10 17:16:18 (the MySQL DATETIME format)
		// return date ( "Y-M-d H:i:s", $unixTimestamp );
		return date ( "Y-M-d", $unixTimestamp );
	}
	public static function formatDateDiff($start, $end = null) {
		/**
		 * Check if timestampe is unix timestamp and convert to datetime
		 */
		//Mlog::addone('formatDateDiff($start, $end = null) $start-->', '*'.$start.'*');
		//Mlog::addone('formatDateDiff($start, $end = null) $end-->', '*'.$end.'*');
		if (self::isValidTimeStamp($start)) {
			$start = self::toDateTime($start);
		}
		$pos = strpos ( $start, '-' );
		
		if ($pos === false) {
			$start = '@' . $start;
		}
		if (! ($start instanceof DateTime)) {
			$start = new \DateTime ( $start );
		}
		
		if (self::isValidTimeStamp($end)) {
			$end = self::toDateTime($end);
		}
		if ($end === null) {
			$end = new \DateTime ();
		} else if (! ($end instanceof DateTime)) {
			$end = new \DateTime ( $end );
		}
		
		$interval = $end->diff ( $start );
		$doPlural = function ($nb, $str) {
			return $nb > 1 ? $str . 's ago' : $str;
		}; // adds plurals
		
		$format = array ();
		if ($interval->y !== 0) {
			$format [] = "%y " . $doPlural ( $interval->y, "year" );
		} else if ($interval->m !== 0) {
			$format [] = "%m " . $doPlural ( $interval->m, "month" );
		} else if ($interval->d !== 0) {
			$format [] = "%d " . $doPlural ( $interval->d, "day" );
		} else if ($interval->h !== 0) {
			$format [] = "%h " . $doPlural ( $interval->h, "hour" );
		} else if ($interval->i !== 0) {
			$format [] = "%i " . $doPlural ( $interval->i, "minute" );
		} else if ($interval->s !== 0) {
			if (! count ( $format )) {
				return "less than a minute ago";
			} else {
				$format [] = "%s " . $doPlural ( $interval->s, "second" );
			}
		}
		
		// We use the two biggest parts
		if (count ( $format ) > 1) {
			$format = array_shift ( $format ) . " and " . array_shift ( $format );
		} else {
			$format = array_pop ( $format );
		}
		
		// Prepend 'since ' or whatever you like
		return $interval->format ( $format );
	}
}

?>
