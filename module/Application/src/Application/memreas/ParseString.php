<?php
namespace Application\memreas;
class ParseString {

   public function __construct() {
    
   }
    public static function getKeyword($tring){
        preg_match_all('/(^|\s)#(\w*[a-zA-Z_]+\w*)/', $string, $keywords);
        return $keywords;
    }

   public static function getEventname($string){
                preg_match_all('/(^|\s)!(\w*[a-zA-Z_]+\w*)/', $string, $events);
                return $events;

    }
    public static function getUserName($string){
                preg_match_all('/(^|\s)@(\w*[a-zA-Z_]+\w*)/', $string, $userNames);
                return $userNames;        
    }
}

?>
