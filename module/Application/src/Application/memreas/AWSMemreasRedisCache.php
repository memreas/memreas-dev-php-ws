<?php

namespace Application\memreas;

use Guzzle\Http\Client;
use Application\Model\MemreasConstants;
use Aws\ElastiCache;
use Predis\Collection\Iterator;

class AWSMemreasRedisCache {
	private $aws = "";
	public $cache = "";
	private $client = "";
    private $isCacheEnable = MemreasConstants::ELASTICACHE_SERVER_USE;
    private $dbAdapter;
    private $url_signer;
    
	public function __construct($service_locator) {
		if(!$this->isCacheEnable){
			return;
		}

    	$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
    	$this->url_signer = new MemreasSignedURL ();

    	try{
			$this->cache = new \Predis\Client([
					'scheme' => 'tcp',
					'host'   => MemreasConstants::ELASTICACHE_SERVER_ENDPOINT,
					'port'   => 6379,
					]);
			
		} catch (\Predis\Connection\ConnectionException $ex) {
			error_log("exception ---> ". print_r($ex, true) . PHP_EOL);				
		}		
		//$this->cache->set('foo', 'bar');
		//error_log("Fetching from REDIS! ---> " . $this->cache->get('foo') . PHP_EOL);
	}
	
	public function setCache($key, $value, $ttl = MemreasConstants::ELASTICACHE_CACHE_TTL) {
		if(!$this->isCacheEnable){
			return false;
		}
		//$result = $this->cache->set ( $key , $value, $ttl );
		$result = $this->cache->executeRaw(array('SETEX', $key , $ttl, $value));

		//Debug
		if($result) {
			//error_log('JUST ADDED THIS KEY ----> ' . $key . PHP_EOL);
			// error_log('VALUE ----> ' . $value . PHP_EOL);
		} else {
			//error_log('FAILED TO ADD THIS KEY ----> ' . $key . ' reason code ---> ' . $this->cache->getResultCode(). PHP_EOL);
			error_log('FAILED TO ADD THIS KEY VALUE----> ' . $value . PHP_EOL);
		}
		return $result;
	}
	
	public function warmHashTagSet($user_id) {
		sleep(1);
		$warming_hashtag = $this->cache->get('warming_hashtag');
		if (!$warming_hashtag) {
error_log("cache warming @warming_hashtag started...".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
			$warming = $this->cache->set('warming_hashtag', '1');
				
			//Fetch all event ids to check for public and friend
			$tagRep = $this->dbAdapter->getRepository('Application\Entity\Event');
			$tags = $tagRep->getHashTags();
			$event_ids[] = array();
			foreach ($tags as $tag) {
//error_log("tag ----> ".$tag['tag']." :: tag_id -> ".$tag['tag_id']." :: meta -> ".$tag['meta'].PHP_EOL);
				$tag_meta = json_decode($tag['meta'], true);
				if (!empty($tag_meta['event'])){
					$event_ids[] = $tag_meta['event'];
				} 
			}

			//Now filter by public and friends and add to cache...
			$public_event_ids = $tagRep->filterPublicHashTags($event_ids);
			$hashtag_public_eid_hash = array();
			foreach ($public_event_ids as $eid) {
				$index = array_search($eid, $tags);
error_log("adding public tag ---> ".$tags[$index]['tag'].PHP_EOL);
				$result = $this->cache->zadd('#hashtag', 0, $tags[$index]['tag']);
				$hashtag_public_eid_hash[$eid] = $tags[$index]['tag'];
			}
			$reply = $this->cache->hmset('#hashtag_public_eid_hash', $hashtag_public_eid_hash);
			
error_log("public_event_tags count ---> ".count($public_event_ids).PHP_EOL);
error_log("ZCARD result ---> ".$this->cache->zcard('#hashtag').PHP_EOL);
			$friend_event_ids = $tagRep->filterFriendHashTags($event_ids, $user_id);
			$hashtag_friends_eid_hash = array();
			foreach ($friend_event_ids as $eid) {
				$tag = array_search($eid, $tags);
				$result = $this->cache->zadd('#hashtag_'.$user_id, 0, $tag['tag']);
				$hashtag_friends_eid_hash[$eid] = $tags[$index]['tag'];
				
			}
error_log("friend_event_tags count ---> ".count($friend_event_ids).PHP_EOL);
error_log("ZCARD result ---> ".$this->cache->zcard('#hashtag_'.$user_id).PHP_EOL);
			$reply = $this->cache->hmset('#hashtag_friends_hash_'.$user_id, $hashtag_friends_eid_hash);

				
//$result = $this->cache->executeRaw(array('HLEN', '@person_uid_hash'));
//error_log("HLEN result ---> $result".PHP_EOL);
//$result = $this->cache->executeRaw(array('HLEN', '@person_hash'));
//error_log("HLEN result ---> $result".PHP_EOL);
			$warming = $this->cache->set('warming_hashtag', '0');
error_log("cache warming @warming_hashtag finished...".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
			
			//$this->elasticache->setCache("!event", $mc);
		}
	}
	
	public function warmPersonSet() {
		sleep(1);
		$warming = $this->cache->get('warming');
error_log("warming--->".$warming.PHP_EOL);			
		if (!$warming) {
error_log("cache warming @person started...".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
			$warming = $this->cache->set('warming', '1');
			
			$url_signer = new MemreasSignedURL();
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u.user_id', 'u.username', 'm.metadata' );
			$qb->from ( 'Application\Entity\User', 'u' );
			$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
			
//error_log("Inside warming fetched query...".PHP_EOL);			
			//create index for catch;
			$userIndexArr = $qb->getQuery()->getResult();
			$person_meta_hash = array();
			$person_uid_hash = array();
			$person_profile_pic_hash = array();
				
			foreach ($userIndexArr as $row) {
				$json_array = json_decode ( $row ['metadata'], true );
			
				if (empty ( $json_array ['S3_files'] ['path'] )){
					$url1 = MemreasConstants::ORIGINAL_URL.'/memreas/img/profile-pic.jpg';
				}else{
					$url1 = $this->url_signer->signArrayOfUrls(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path']);
				}
			
				$person_json = json_encode(
								array(
									'username'      => $row['username'],
									'user_id'      => $row['user_id'],
									'profile_photo' => $url1
								)); 
				
				/*
				 * TODO: need to send this in one shot
				 */
				$person_meta_hash[$row['username']] = $person_json;
				$person_uid_hash[$row['username']] = $row['user_id'];
				$person_profile_pic_hash[$row['username']] = $url1;
				$result = $this->cache->zadd('@person', 0, $row['username']);
			}
			$reply = $this->cache->hmset('@person_meta_hash', $person_meta_hash);
			$reply = $this->cache->hmset('@person_uid_hash', $person_uid_hash);
			$reply = $this->cache->hmset('@person_profile_pic_hash', $person_profile_pic_hash);
			//Finished warming so reset flag
			$warming = $this->cache->set('warming', '0');
error_log("cache warming @person ended... @ ".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
		} else {
			error_log("Outside warming...".PHP_EOL);
		}
	}		
	
	public function findSet($set, $match) {

error_log("Inside findSet.... set $set match $match" . PHP_EOL);
		//Scan the hash and return 0 or the sub-array
		$arr = array('ZRANGEBYLEX', $set, "[".$match, "(".$match."z" );
		$result = $this->cache->executeRaw(array('ZRANGEBYLEX', $set, "[".$match, "(".$match."z" ));
 		if ($result != "(empty list or set)") {
 			$matches = $result;
 		} else {
 			$matches = 0;
//error_log("error matches------> " . json_encode($matches) . PHP_EOL);
 		}
error_log("matches------> " . json_encode($matches) . PHP_EOL);
		return $matches;
	}

	public function addSet($set, $key, $val=null) {
		if (is_null($val) && !$this->cache->executeRaw(array('ZCARD', $set, $key)) ) {
		
//error_log("addSet $set:count------->".$this->cache->executeRaw(array('ZCARD', $set, $key)).PHP_EOL);
//error_log("addSet $set:$key".PHP_EOL);
			return $this->cache->zadd("$set", "$key");
		} else if (!is_null($val)) {
//error_log("addSet $set:$key:$val".PHP_EOL);
			return $this->cache->hset("$set", "$key", "$val");
		} else {
			//do nothing key exists...
//error_log("addSet $set:$key already exists...".PHP_EOL);
		}				
	}
	
	public function hasSet($set, $hash = false) {
		//Scan the hash and return 0 or the sub-array
		if ($hash) {
			$result = $this->cache->executeRaw(array('HLEN', $set));
		} else {
			$result = $this->cache->executeRaw(array('ZCARD', $set));
		}
error_log("hasSet result set $set ------> " . json_encode($result) . PHP_EOL);
		return $result;
	}
	
	public function getSet($set) {
		return $this->cache->smembers($set, true);
	}	
	
	public function remSet($set) {
		$this->cache->executeRaw(array('DEL', $set));
	}	

	public function getCache($key) {
		if(!$this->isCacheEnable){
			//error_log("isCacheEnable ----> ".$this->isCacheEnable.PHP_EOL);
			return false;
		}

		$result = $this->cache->get ( $key );
		/*
		if ($result) {
			//error_log('JUST FETCHED THIS KEY ----> ' . $key . PHP_EOL);
		} else {
			error_log('COULD NOT FIND THIS KEY GOING TO DB ----> ' . $key . PHP_EOL);
		}
		*/
		
		return $result;
	}

	public function invalidateCache($key) {
		if(!$this->isCacheEnable){
			return false;
		}

		$result = $this->cache->delete ( $key );
		if ($result) {
			//error_log('JUST DELETED THIS KEY ----> ' . $key . PHP_EOL);
		} else {
			error_log('COULD NOT DELETE THIS KEY ----> ' . $key . PHP_EOL);
		}
	}

	public function invalidateCacheMulti($keys) {
		if(!$this->isCacheEnable){
			return false;
		}

		$result = $this->cache->deleteMulti ( $keys );
		if ($result) {
			//error_log('JUST DELETED THESE KEYS ----> ' . json_encode($keys) . PHP_EOL);
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
			$now = date ( 'Y-m-d H:i:s.u' );
			error_log('invalidateCacheMulti JUST DELETED THESE KEYS ----> ' . json_encode($cache_keys) . " time: " . $now . PHP_EOL);
		} else {
			$now = date ( 'Y-m-d H:i:s.u' );
			error_log('invalidateCacheMulti COULD NOT DELETE THES KEYS ----> ' . json_encode($cache_keys) . " time: " . $now . PHP_EOL);
		}
	} // End invalidateMedia

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
		