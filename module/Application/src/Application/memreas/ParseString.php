<?php

namespace Application\memreas;

class ParseString {
	public static function getKeyword($string) {
		preg_match_all ( '/(^|\s)#(\w*[a-zA-Z_]+\w*)/', $string, $keywords );
		
		return $keywords;
	}
	public static function getEventname($string) {
		preg_match_all ( '/(^|\s)!(\w*[a-zA-Z_]+\w*)/', $string, $events );
		return $events;
	}
	public static function getUserName($string) {
		preg_match_all ( '/(^|\s)@(\w*[a-zA-Z_]+\w*)/', $string, $userNames );
		return $userNames;
	}
}

?>
