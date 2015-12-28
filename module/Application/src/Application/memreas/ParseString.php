<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

class ParseString {
	public static function getKeyword($string) {
		preg_match_all ( '/(^|\s)#(\w*[a-zA-Z_]+\w*)/', $string, $keywords );
		
		return $keywords;
	}
	public static function getEventname($string) {
		preg_match_all ( '/!(.*?)!|!(.*?)\s/', $string, $events );
		return $events;
	}
	public static function getUserName($string) {
		preg_match_all ( '/(^|\s)@(\w*[a-zA-Z_]+\w*)/', $string, $userNames );
		return $userNames;
	}
}

?>
 