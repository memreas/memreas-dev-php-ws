<?php

namespace Application\memreas;

use Guzzle\Http\Client;
use Application\Model\MemreasConstants;
use Aws\ElastiCache;

class AWSMemreasCache {
	private $aws = null;
	private $cache = null;
	private $client = null;
	private $baseURL = MemreasConstants::ORIGINAL_URL;
    private $isCacheEnable = MemreasConstants::ELASTICACHE_SERVER_USE;
	public function __construct() {
		if(!$this->isCacheEnable){
			return null;
		}
//error_log ( "MemreasCache.__construct()....".date ( 'Y-m-d H:i:s' ). PHP_EOL );

		/**
		 * Sample PHP code to show how to integrate with the Amazon ElastiCcache
		 * Auto Discovery feature.
		 */

		/* Configuration endpoint to use to initialize memcached client. This is only an example. */
		$server_endpoint = MemreasConstants::ELASTICACHE_SERVER_ENDPOINT;
		/* Port for connecting to the ElastiCache cluster. This is only an example */
		$server_port = MemreasConstants::ELASTICACHE_SERVER_PORT;

// error_log ( "Set endpoint $server_endpoint" . PHP_EOL );
// error_log ( "Set port $server_port" . PHP_EOL );

		/**
		 * The following will initialize a Memcached client to utilize the Auto Discovery feature.
		 *
		 * By configuring the client with the Dynamic client mode with single endpoint, the
		 * client will periodically use the configuration endpoint to retrieve the current cache
		 * cluster configuration. This allows scaling the cache cluster up or down in number of nodes
		 * without requiring any changes to the PHP application.
		 */

		$now = date ( 'Y-m-d H:i:s' );
		$dynamic_client = new \Memcached();
		$dynamic_client->setOption( \Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE);
		$dynamic_client->addServer($server_endpoint, $server_port);
		//$dynamic_client->set ( 'LAST-USER-ID-ACCESS', $now, 3600 ); // Store the data for 1 hour in the cluster, the client will decide which node to store
		
		$this->cache = $dynamic_client;

		//$now = date ( 'Y-m-d H:i:s' );
		//$this->cache->set ( 'LAST-USER-ID-ACCESS', $now, 3600 ); // Store the data for 1 hour in the cluster, the client will decide which node to store

		// Connected at this point
	}

	public function setCache($key, $value, $ttl = MemreasConstants::ELASTICACHE_CACHE_TTL) { 
		if(!$this->isCacheEnable){
			return null;
		}
		$result = $this->cache->set ( $key , json_encode($value), $ttl );
		
		//Debug
		if($result) {
			$now= 
			error_log('JUST ADDED THIS KEY ----> ' . $key . PHP_EOL);
			// error_log('VALUE ----> ' . $value . PHP_EOL);
		} else {
			error_log('FAILED TO ADD THIS KEY ----> ' . $key . PHP_EOL);
			//error_log('FAILED TO ADD THIS KEY VALUE----> ' . print_r($value, true) . PHP_EOL);
		}

		return $result;
	}

	public function getCache($key) {
		if(!$this->isCacheEnable){
			return null;
		}

 		$result = $this->cache->get ( $key );
 		if ($result) {
			 error_log('JUST FETCHED THIS KEY ----> ' . $key . PHP_EOL);
 		} else {
 			error_log('COULD NOT FIND THIS KEY GOING TO DB ----> ' . $key . PHP_EOL);
 		}

 		return $result;
	}

	public function invalidateCache($key) {
		if(!$this->isCacheEnable){
			return null;
		}

 		$result = $this->cache->delete ( $key );
 		if ($result) {
			 error_log('JUST DELETED THIS KEY ----> ' . $key . PHP_EOL);
 		} else {
 			error_log('COULD NOT DELETE THIS KEY ----> ' . $key . PHP_EOL);
 		}
	}

	public function invalidateCacheMulti($keys) {
		if(!$this->isCacheEnable){
			return null;
		}

 		$result = $this->cache->deleteMulti ( $keys );
 		if ($result) {
			 error_log('JUST DELETED THESE KEYS ----> ' . json_encode($keys) . PHP_EOL);
 		} else {
 			error_log('COULD NOT DELETE THES KEYS ----> ' . json_encode($keys) . PHP_EOL);
 		}
 		return $result;
	}

	/*
	 * Add function to invalidate cache for media
	 */
	public function invalidateMedia($user_id, $event_id = null, $media_id = null) {
//error_log("Inside invalidateMedia".PHP_EOL); 		
//error_log('Inside invalidateMedia $user_id ----> *' . $user_id . '*' . PHP_EOL);
//error_log('Inside invalidateMedia $event_id ----> *' . $event_id . '*' . PHP_EOL);
//error_log('Inside invalidateMedia $media_id ----> *' . $media_id . '*' . PHP_EOL);
// write functions for media 
		//  - add media event (key is event_id or user_id) 
		//  - mediainappropriate (key is user id for invalidate) 
		//  - deletePhoto (key is user id for invalidate)
		//  - update media
		//  - removeeventmedia
		$cache_keys = array();
		$event_id = trim($event_id);
		if (!empty($event_id)) {
			$cache_keys[] = "listallmedia_" . $event_id;
			$cache_keys[] = "geteventdetails_" . $event_id;
		}
		$media_id = trim($media_id);
		if (!empty($media_id)) {
			$cache_keys[] = "viewmediadetails_" . $media_id;
		}
		$user_id = trim($user_id);
		if (!empty($user_id)) {
			//countviewevent can return me / friends / public
			$cache_keys[] = "listallmedia_" . $user_id;
			$cache_keys[] = "viewevents_is_my_event_" . $user_id;
			$cache_keys[] = "viewevents_is_friend_event_" . $user_id;
		}

		//Mecached - deleteMulti...
		$result = $this->invalidateCacheMulti($cache_keys);
		if ($result) {
			$now = date ( 'Y-m-d H:i:s' );
			error_log('invalidateCacheMulti JUST DELETED THESE KEYS ----> ' . json_encode($cache_keys) . " time: " . $now . PHP_EOL);
		} else {
			$now = date ( 'Y-m-d H:i:s' );
			error_log('invalidateCacheMulti COULD NOT DELETE THES KEYS ----> ' . json_encode($cache_keys) . " time: " . $now . PHP_EOL);
		}
		
	}
	
	/*
	 * Add function to invalidate cache for events
	 */
	public function invalidateEvents($user_id) {
error_log("Inside invalidateEvents".PHP_EOL); 		
		// write functions for media 
		//  - add event (key is event_id) 
		//  - removeevent
		if (!empty($user_id)) {
			//countviewevent can return me / friends / public
			$cache_keys = array(
					"viewevents_is_my_event_" . $user_id,
					"viewevents_is_friend_event_" . $user_id,
			);
			$this->invalidateCacheMulti($cache_keys);
		}
	}
	
	/*
	 * Add function to invalidate cache for comments
	 */
	public function invalidateComments($cache_keys) {
	}
	
	/*
	 * Add function to invalidate cache for event friends
	 */
	public function invalidateEventFriends($event_id, $user_id) {
error_log("Inside invalidateEventFriends".PHP_EOL); 		
		// write functions for media 
		//  - add event friend 
		//  - remove event friend
		if (!empty($event_id)) {
			//countviewevent can return me / friends / public
			$cache_keys = array(
					"geteventpeople_" . $event_id,
					"viewevents_is_my_event_" . $user_id,
					"viewevents_is_friend_event_" . $user_id,
			);
			$this->invalidateCacheMulti($cache_keys);
		}
	}
	
	/*
	 * Add function to invalidate cache for friends
	 */
	public function invalidateFriends($user_id) {
error_log("Inside invalidateEventFriends".PHP_EOL); 		
		// write functions for media 
		//  - add friend 
		//  - remove friend
		if (!empty($event_id)) {
			//countviewevent can return me / friends / public
			$cache_keys = array(
					"listmemreasfriends_" . $user_id,
					"getfriends_" . $user_id,
			);
			$this->invalidateCacheMulti($cache_keys);
			$this->invalidateEvents($user_id);
		}
	}
	
	/*
	 * Add function to invalidate cache for notifications
	 */
	public function invalidateNotifications($user_id) {
		// write functions for groups 
		//  - list notification  (key is user_id) 
		if (!empty($user_id)) {
			$this->invalidateCache("listnotification_" . $user_id);
		}
	}
	
	
	/*
	 * Add function to invalidate cache for user
	 */
	public function invalidateUser($user_id) {
		// write functions for groups
		//  - list group  (key is user_id)
		if (!empty($user_id)) {
			$this->invalidateCache("getuserdetails_" . $user_id);
		}
	}
	
	/*
	 * Add function to invalidate cache for groups
	 */
	public function invalidateGroups($user_id) {
		// write functions for groups 
		//  - list group  (key is user_id) 
		if (!empty($user_id)) {
			$this->invalidateCache("listgroup_" . $user_id);
		}
	}
	
}

?>
