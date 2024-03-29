<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\memreas\login;
// memreas models
use Application\Model\User;

class Memreas {
	protected $user_id;
	protected $session;
	public function __construct() {
		// print "In MemreasAWSTranscoder constructor <br>";
		error_log ( "Inside Memreas contructor...", 0 );
		error_log ( "Exit Memreas constructor", 0 );
		// print "Exit MemreasAWSTranscoder constructor <br>";
	}
	public function fetchSession() {
		if (! isset ( $this->session )) {
			$this->session = new Container ( 'user' );
			$this->user_id = $this->session->offsetGet ( 'user_id' );
		}
	}
	public function login($message_data, $memreas_tables, $service_locator) {
		// Fetch the user_id
		if (! isset ( $this->user_id )) {
			// Login here....
			
			$result = array (
					"Status" => "Success",
					"Description" => "Inside login..." 
			);
			
			error_log ( "Returning result...", 0 );
			
			return $result;
		}
		
		// Return an error message:
		$result = array (
				"Status" => "Error",
				"Description" => "$this->user_id not found" 
		);
		return $result;
	}
}
?>
