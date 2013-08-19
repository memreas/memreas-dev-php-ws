<?php


chdir(dirname(__DIR__));
//echo getcwd().'<br>';

require_once 'config.php';
require 'init_autoloader.php';
use Guzzle\Http\Client;

//error_reporting(E_ALL & ~E_NOTICE); 

class MemreasCache { 
    
    private $cache = null;
    private $client = null;
    private $baseURL = 'http://192.168.1.8/memreas/webservices/';
    
	public function __construct() {
 		//print "In MemreasCache constructor <br>";
     	$this->cache = new memcached();
		$this->cache->addServer("localhost", 11211); 
		$this->client = new Client();
 		//print "Exit MemreasCache constructor <br>";
	} 
	
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
