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

		$now = date ( 'Y-m-d H:i:s' );
		$this->cache->set ( 'LAST-USER-ID-ACCESS', $now, 3600 ); // Store the data for 1 hour in the cluster, the client will decide which node to store

		// Connected at this point
	}

	public function setCache($key, $value, $ttl = MemreasConstants::ELASTICACHE_CACHE_TTL) { 
		if(!$this->isCacheEnable){
			return null;
		}
		$result = $this->cache->set ( $key , $value, $ttl );
//error_log('JUST ADDED THIS KEY ----> ' . $key . PHP_EOL);
// error_log('VALUE ----> ' . $value . PHP_EOL);

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

	public function fetchPostBody($url, $action, $xml, $cache_me = false) {
		$request = $this->client->post ( $url, null, array (
				'action' => $action,
				'cache_me' => $cache_me,
				'xml' => $xml
		) );

		$response = $request->send ();
		return $response->getBody ( true );
	}

	public function fetchXML($url,$key, $xml, $invalidateCache = false) {

		// echo 'Inside fetchXML action ----> ' . $url . '<BR><BR>';
		// echo 'Inside fetchXML action ----> ' . $action . '<BR><BR>';
		// echo 'Inside fetchXML uid ----> ' . $uid . '<BR><BR>';
 		$listPhotos = $this->cache->get ( $key );
		if (($invalidateCache) || (! $listPhotos)) {
			$listPhotos = $this->fetchPostBody ( $url, $action, $xml, true );
			$this->cache->set ( $key, $listPhotos );
			echo 'Inside invalidateCache || !listPhotos ----> just cached it!!!!!! <BR><BR>';
			// echo "listPhotos ----------> $listPhotos<BR><BR>";
			echo "key ---------->    " . $key . "<BR><BR>";
		} else {
			echo "inside do nothing - return $action from cache KEY VALUE ---> " . $key . "<BR><BR>";
			// Do nothing - add debug if needed to test invalidation
		}

		return $listPhotos;
	}

	/*
	 * Add function to invalidate cache for media
	 */
	public function invalidateMedia() {
		// write functions for media 
		//  - add media event (key is event_id or user_id) 
		//  - mediainappropriate (key is user id for invalidate) 
		//  - deletePhoto (key is user id for invalidate)
		//  - update media
		//  - removeeventmedia
		$data = simplexml_load_string($_POST ['xml']);
		if (!empty($data->addmediaevent->event_id)) {
			//               	$this->elasticache->invalidateCache("countlistallmedia_" . $data->addmediaevent->event_id);
			$this->invalidateCache("listallmedia_" . $data->addmediaevent->event_id);
			//$this->invalidateCache("viewevents_" . $data->addmediaevent->event_id);
		}
		
		if (!empty($data->addmediaevent->user_id)) {
			//countviewevent can return me / friends / public
			//               	$this->elasticache->invalidateCache("countviewevent_is_my_event_" . $data->addmediaevent->user_id);
			$this->invalidateCache("listallmedia_" . $data->addmediaevent->user_id);
			//$this->invalidateCache("viewevents_" . $data->addmediaevent->user_id);
		}
	}
	
	/*
	 * Add function to invalidate cache for events
	 */
	public function invalidateEvents($cache_keys) {
	}
	
	/*
	 * Add function to invalidate cache for comments
	 */
	public function invalidateComments($cache_keys) {
	}
	
	/*
	 * Add function to invalidate cache for friends
	 */
	public function invalidateFriends($cache_keys) {
	}
	

}

?>
