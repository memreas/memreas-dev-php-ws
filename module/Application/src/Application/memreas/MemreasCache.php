<?php

namespace Application\memreas;

use Guzzle\Http\Client;
use Application\Model\MemreasConstants;


class MemreasCache { 
    
    private $cache = null;
    private $client = null;
    private $baseURL = MemreasConstants::ORIGINAL_URL;
    
	public function __construct() {
error_log("MemreasCache.__construct()....".PHP_EOL);
		//print "In MemreasCache constructor <br>";
     	//$this->cache = new memcached();
		//$this->cache->addServer("localhost", 11211); 
		//$this->client = new Client();
 		//print "Exit MemreasCache constructor <br>";


		/**
		 * Sample PHP code to show how to integrate with the Amazon ElastiCcache
		 * Auto Discovery feature.
		 */
		
		/* Configuration endpoint to use to initialize memcached client. This is only an example. */
		$server_endpoint = MemreasConstants::ELASTICACHE_SERVER_ENDPOINT;
		/* Port for connecting to the ElastiCache cluster. This is only an example */
		$server_port = MemreasConstants::ELASTICACHE_SERVER_PORT;
		
error_log("Set endpoint $server_endpoint".PHP_EOL);
error_log("Set port $server_port".PHP_EOL);
		
		/**
		 * The following will initialize a Memcached client to utilize the Auto Discovery feature.
		 *
		 * By configuring the client with the Dynamic client mode with single endpoint, the
		 * client will periodically use the configuration endpoint to retrieve the current cache
		 * cluster configuration. This allows scaling the cache cluster up or down in number of nodes
		 * without requiring any changes to the PHP application.
		*/
		
		$this->cache = new \Memcached ();
error_log("Created new Memcached client..".PHP_EOL);
		$this->cache->setOption ( \Memcached::OPT_CLIENT_MODE, \Memcached::DYNAMIC_CLIENT_MODE );
		$this->cache->addServer ( $server_endpoint, $server_port );
		$now = date('Y-m-d H:i:s');
		
		$this->cache->set ( 'LAST-USER-ID-ACCESS', $now, 3600 ); // Store the data for 1 hour in the cluster, the client will decide which node to store
		
		//Connected at this point
error_log("Connected to elasticache client!".PHP_EOL);
error_log("Connected to elasticache client!".PHP_EOL);
error_log("Last access time is @ ". $this->cache->get ( 'LAST-USER-ID-ACCESS' ).PHP_EOL);
	} 
	
//		$static_client->set ( 'key', 'value' ); // Store the data in the cluster without expiration

	
	
	public function fetchPostBody($url, $action, $xml, $cache_me = false) {
		$request = $this->client->post(
			$url, 
			null, 
			array(
				'action' => $action,
    			'cache_me' => $cache_me,
    			'xml' => $xml
			)
		);

		$response = $request->send();
		return $response->getBody(true);
	}   
    
    public function fetchXML($url, $action, $uid, $xml, $invalidateCache = false) { 

//echo 'Inside fetchXML action ----> ' . $url . '<BR><BR>';
//echo 'Inside fetchXML action ----> ' . $action . '<BR><BR>';
//echo 'Inside fetchXML uid ----> ' . $uid . '<BR><BR>';

		$key = $action . '_' . $uid;
		$listPhotos = $this->cache->get($key);
		if (($invalidateCache) || (!$listPhotos)) {
			$listPhotos = $this->fetchPostBody($url, $action, $xml, true);
			$this->cache->set($key, $listPhotos);
			echo 'Inside invalidateCache || !listPhotos ----> just cached it!!!!!! <BR><BR>';
			//echo "listPhotos ---------->    $listPhotos<BR><BR>";
			echo "key ---------->    " . $key . "<BR><BR>";
		} else {
			echo "inside do nothing - return $action from cache KEY VALUE ---> " . $key . "<BR><BR>";
			//Do nothing - add debug if needed to test invalidation
		}

		return $listPhotos;
    } 

    public function invalidateCache($action, $uid) { 
		$key = $action . '_' . $uid;
		$this->cache->delete($key);
		echo 'JUST DELETED THIS KEY ----> ' . $key . '<BR>';
    } 
} 

//$memreasCache = new MemreasCache(); 
//$memreasCache->listPhotos('52b855ae-b9b7-11e2-a0c7-72b539fa3d40', true);
//error_log( $memreasCache->listPhotos('52b855ae-b9b7-11e2-a0c7-72b539fa3d40', false), 0);

?>
