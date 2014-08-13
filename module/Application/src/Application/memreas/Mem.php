<?php

namespace Application\memreas;
use \Exception;

class Mem {
	protected static $service_locator;
	protected static $dbAdapter;
  public static $item;
  protected static $collection;
                        
	public static function collect() {
                 self::$collection [] = self::$item;
                self::$item = array();	
	}
	
        public static function formatDateDiff($start, $end=null) { 
          $start ='@'.$start;
    if(!($start instanceof DateTime)) { 
        $start = new \DateTime($start); 
    } 
    
    if($end === null) { 
        $end = new \DateTime(); 
    }else if(!($end instanceof DateTime)) { 
        $end = new \DateTime($end); 
    } 
    
    $interval = $end->diff($start); 
    $doPlural = function($nb,$str){return $nb>1?$str.'s ago':$str;}; // adds plurals 
    
    $format = array(); 
    if($interval->y !== 0) { 
        $format[] = "%y ".$doPlural($interval->y, "year"); 
    } else 
    if($interval->m !== 0) { 
        $format[] = "%m ".$doPlural($interval->m, "month"); 
    } else
    if($interval->d !== 0) { 
        $format[] = "%d ".$doPlural($interval->d, "day"); 
    } else
    if($interval->h !== 0) { 
        $format[] = "%h ".$doPlural($interval->h, "hour"); 
    } else
    if($interval->i !== 0) { 
        $format[] = "%i ".$doPlural($interval->i, "minute"); 
    } else
    if($interval->s !== 0) { 
        if(!count($format)) { 
            return "less than a minute ago"; 
        } else { 
            $format[] = "%s ".$doPlural($interval->s, "second"); 
        } 
    } 
    
    // We use the two biggest parts 
    if(count($format) > 1) { 
        $format = array_shift($format)." and ".array_shift($format); 
    } else { 
        $format = array_pop($format); 
    } 
    
    // Prepend 'since ' or whatever you like 
    return $interval->format($format); 
  }
}
	 
	
?>
