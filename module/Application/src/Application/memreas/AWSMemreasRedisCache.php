<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;
use Aws\ElastiCache;
use Predis\Collection\Iterator;

class AWSMemreasRedisCache {
	private $aws = "";
	public $cache = "";
	private $client = "";
	private $service_locator;
	private $isCacheEnable = MemreasConstants::REDIS_SERVER_USE;
	private $dbAdapter;
	private $url_signer;
	public function __construct($service_locator) {
		$cm = __CLASS__ . __METHOD__;
		if (! $this->isCacheEnable) {
			return;
		}
		$this->service_locator = $service_locator;
		
		$this->aws_manager = new AWSManagerSender ( $service_locator );
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
		
		try {
			$this->cache = new \Predis\Client ( [ 
					'scheme' => 'tcp',
					'host' => MemreasConstants::REDIS_SERVER_ENDPOINT,
					'port' => 6379 
			] );
		} catch ( \Exception $e ) {
			// Mlog::addone ( $cm, '::predis connection exception ---> ' . $e->getMessage () );
			$to = MemreasConstants::ADMIN_EMAIL;
			$html = '<html><head></head><body><p>REDIS CONNECTION ERROR<p>' . $e->getMessage () . '</body></html>';
			$this->aws_manager->sendSeSMail ( $to, 'REDIS CONNECTION ERROR', $html );
		}
	}
	public function setExpire($set) {
		$reply = $this->cache->executeRaw ( array (
				'EXPIRE',
				$set,
				MemreasConstants::REDIS_CACHE_TTL 
		) );
		
		return $reply;
	}
	public function setCache($key, $value, $ttl = MemreasConstants::REDIS_CACHE_TTL) {
		$cm = __CLASS__ . __METHOD__;
		if (! $this->isCacheEnable) {
			return false;
		}
		// $result = $this->cache->set ( $key , $value, $ttl );
		$result = $this->cache->executeRaw ( array (
				'SETEX',
				$key,
				$ttl,
				$value 
		) );
		
		// Debug
		// if ($result) {
		// //Mlog::addone($cm, '::JUST ADDED THIS KEY ----> ' . $key . ' value--->' . $value);
		// } else {
		// //Mlog::addone($cm, '::FAILED TO ADD THIS KEY ----> ' . $key . ' reason code ---> ' . $this->cache->getResultCode());
		// //Mlog::addone($cm, '::FAILED TO ADD THIS KEY VALUE----> ' . $value);
		// }
		return $result;
	}
	public function warmHashTagSet($user_id) {
		$cm = __CLASS__ . __METHOD__;
		sleep ( 1 );
		
		$warming_hashtag = $this->cache->get ( 'warming_hashtag' );
		// Mlog::addone ( $cm, '::warming_hashtag...' . $warming_hashtag );
		if (! $warming_hashtag || ($warming_hashtag == "(nil)")) {
			// Mlog::addone ( $cm, '::cache warming #hashtag started...' . date ( 'Y-m-d H:i:s.u' ) );
			$warming = $this->cache->set ( 'warming_hashtag', '1' );
			
			// Fetch all event ids to check for public and friend
			$tagRep = $this->dbAdapter->getRepository ( 'Application\Entity\Event' );
			$tags = $tagRep->getHashTags ();
			if ($tags) {
				
				$event_ids [] = array ();
				foreach ( $tags as $tag ) {
					$tag_meta = json_decode ( $tag ['meta'], true );
					if (! empty ( $tag_meta ['event'] )) {
						$event_ids [$tag_meta ['event'] [0]] = $tag ['tag'];
					}
				}
				// Mlog::addone ( 'warmHashTagSet($user_id)', 'past for loop' );
				
				/*
				 * Now filter by public and friends and add to cache...
				 */
				$keys = array_keys ( $event_ids );
				$public_event_ids = $tagRep->filterPublicHashTags ( $keys );
				// Mlog::addone ( 'warmHashTagSet($user_id)', 'past $tagRep->filterPublicHashTags ( $keys )' );
				$hashtag_public_eid_hash = array ();
				foreach ( $public_event_ids as $eid ) {
					if (! empty ( $event_ids [$eid ['event_id']] )) {
						// Mlog::addone ( $cm, '::public_event_tags event_ids[eid[event_id]] ---> ' . $event_ids [$eid ['event_id']] );
						// Mlog::addone ( $cm, '::public_event_tags eid[tag] ---> ' . $event_ids [$eid ['event_id']] );
						$result = $this->cache->zadd ( '#hashtag', 0, $event_ids [$eid ['event_id']] );
						$hashtag_public_eid_hash [$eid ['event_id']] = $event_ids [$eid ['event_id']];
					}
				}
				// Mlog::addone ( 'warmHashTagSet($user_id)', 'past $tagRep->filterPublicHashTags ( $keys ) for loop...' );
				$reply = $this->cache->hmset ( '#hashtag_public_eid_hash', $hashtag_public_eid_hash );
				$friend_event_ids = $tagRep->filterFriendHashTags ( $keys, $user_id );
				// Mlog::addone ( 'warmHashTagSet($user_id)', 'past $tagRep->filterFriendHashTags ( $keys, $user_id )' );
				$hashtag_friends_eid_hash = array ();
				foreach ( $friend_event_ids as $eid ) {
					if (! empty ( $event_ids [$eid ['event_id']] )) {
						// Mlog::addone ( $cm, '::friend_event_tags event_ids[eid[event_id]] ---> ' . $event_ids [$eid ['event_id']] );
						// Mlog::addone ( $cm, '::friend_event_tags eid[tag] ---> ' . $event_ids [$eid ['event_id']] );
						$result = $this->cache->zadd ( '#hashtag_' . $user_id, 0, $event_ids [$eid ['event_id']] );
						$hashtag_friends_eid_hash [$eid ['event_id']] = $event_ids [$eid ['event_id']];
					}
				}
				// Mlog::addone ( $cm, '::friend_event_tags count ---> ' . count ( $friend_event_ids ) );
				// Mlog::addone ( $cm, '::ZCARD #hashtag_' . $user_id . ' result ---> ' . $this->cache->zcard ( '#hashtag_' . $user_id ) );
				$reply = $this->cache->hmset ( '#hashtag_friends_hash_' . $user_id, $hashtag_friends_eid_hash );
				// Mlog::addone ( $cm . __LINE__, '::setExpire reply ---> ' . $reply );
				
				$result = $this->cache->executeRaw ( array (
						'HLEN',
						'#hashtag_friends_hash_' . $user_id 
				) );
				// Mlog::addone ( $cm, '::HLEN #hashtag_friends_hash_' . $user_id . ' result --->' . $result );
				$result = $this->cache->executeRaw ( array (
						'HLEN',
						'#hashtag_public_eid_hash' 
				) );
				// Mlog::addone ( $cm, '::HLEN #hashtag_public_eid_hash result --->' . $result );
				$warming = $this->cache->set ( 'warming_hashtag', '0' );
				// Mlog::addone ( $cm, '::cache warming @warming_hashtag finished...' . date ( 'Y-m-d H:i:s.u' ) );
				
				/**
				 * Set expire for each set
				 */
				$reply = $this->setExpire ( '#hashtag' );
				// Mlog::addone ( $cm . __LINE__, '::#hashtag setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '#hashtag_' . $user_id );
				// Mlog::addone ( $cm . __LINE__, '::#hashtag_' . $user_id . ' setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '#hashtag_friends_hash_' . $user_id );
				// Mlog::addone ( $cm . __LINE__, '::#hashtag_friends_hash_' . $user_id . ' setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '#hashtag_public_eid_hash' );
				// Mlog::addone ( $cm . __LINE__, '::!memreas_eid_hash setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '#hashtag_public_meta_hash' );
				// Mlog::addone ( $cm . __LINE__, '::!memreas_meta_hash setExpire reply ---> ' . $reply );
				
				// Mlog::addone ( $cm, '::cache warming #hashtag completed...' . date ( 'Y-m-d H:i:s.u' ) );
			} else { // end if ($tags)
				$warming = $this->cache->set ( 'warming_hashtag', '0' );
				// Mlog::addone ( $cm, '::cache warming #hashtag completed 0 entries...' . date ( 'Y-m-d H:i:s.u' ) );
			}
		}
	}
	/**
	 * Redis cache for public and friends events
	 */
	public function warmMemreasSet($user_id) {
		// Mlog::addone ( $cm, 'entered warmMemreasSet' );
		$cm = __CLASS__ . __METHOD__;
		sleep ( 1 );
		$user_id = $_SESSION ['user_id'];
		$reply = $this->sExists ( 'warming_memreas' );
		// Mlog::addone ( $cm, '::warming_memreas...' . $reply );
		if (! $warming_memreas || ($warming_memreas == "(nil)")) {
			// Mlog::addone ( $cm, '::cache warming @warming_memreas started...' . date ( 'Y-m-d H:i:s.u' ) );
			$warming = $this->cache->set ( 'warming_memreas', '1' );
			
			/**
			 * - Public event cache filtered for valid viewable and ghost dates
			 */
			$eventRep = $this->service_locator->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
			if (! $this->sExists ( '!memreas' )) {
				// Mlog::addone ( $cm, '::building cache for public events started...' . date ( 'Y-m-d H:i:s.u' ) );
				
				$events_result = $eventRep->createEventCache ( 'public' );
				// //Mlog::addone ( $cm . '::$eventRep->createEventCache ( public ) --->', $events_result, 'p');
				// //Mlog::addone ( $cm . '::warmMemreasSet Public Count --->', count ( $events_result ) );
				$public_event_ids = array ();
				foreach ( $events_result as $event ) {
					// Mlog::addone ( 'public event after createEventCache top of redis add loop $event ---> ', $event, 'p' );
					
					$event_id = $event ['event_id'];
					$event_owner = $event ['user_id'];
					/**
					 * - Check for viewable from / to since db date is string
					 */
					if ((! empty ( ( int ) (trim ( $event ['viewable_from'] )) )) && (! empty ( ( int ) (trim ( $event ['viewable_to'] )) ))) {
						$now = time ();
						/**
						 * check if now is outside dates
						 */
						if (($now < $event ['viewable_from']) || ($now > $event ['viewable_to'])) {
							// Mlog::addone ( "warmMemreasSet::checking viewable from to bounds event_id is out of bounds for now $now --> ", $event ['event_id'] );
							continue;
						}
					}
					/**
					 * - Check for self_destruct/ghost since db date is string
					 */
					if (! empty ( ( int ) (trim ( $event ['self_destuct'] )) )) {
						$now = time ();
						/**
						 * check if now is outside dates
						 */
						// Mlog::addone ( 'Check for ghost date--->', '$now--->' . $now . ' $ghost' );
						if ($now <= $event ['self_destruct']) {
							// Mlog::addone ( "warmMemreasSet::checking ghost is out of bounds for now $now --> ", $event ['event_id'] );
							continue;
						}
					}
					
					/**
					 * Add to sorted set here
					 * Note: event_owner_key is set to ensure uniqueness of key (user_id_event_id)
					 * owner_name_key needs to have create time as part of key since same owner can create event with same name
					 * i.e.
					 * "user_id_ name of my event"
					 */
					$event_name_key = $event ['name'] . '{' . $event_owner . '_' . $event ['create_time'] . '}';
					$event_owner_key = $event_owner . '_' . $event_id;
					$reply = $this->cache->zadd ( '!memreas', 0, $event_name_key );
					$reply = $this->cache->hset ( "!memreas_meta_hash", $event_name_key, $event_owner_key );
					$reply = $this->cache->hset ( "!memreas_eid_hash", $event_owner_key, json_encode ( $event ) );
				}
				/**
				 * Adding here would reduce REDIS hits but not working
				 */
				// $reply = $this->cache->hmset ( "!memreas_meta_hash", $public_event_meta_hash );
				// $reply = $this->cache->hmset ( "!memreas_eid_hash", $public_eid_hash );
				
				/**
				 * Check the counts
				 */
				$reply = $this->cache->executeRaw ( array (
						'ZCARD',
						'!memreas' 
				) );
				// Mlog::addone ( $cm, '::Public ZCARD !memreas result --->' . $reply );
				
				$reply = $this->cache->executeRaw ( array (
						'HLEN',
						'!memreas_meta_hash' 
				) );
				// Mlog::addone ( $cm, '::public HLEN !memreas_meta_hash result --->' . $reply );
				
				$reply = $this->cache->executeRaw ( array (
						'HLEN',
						'!memreas_eid_hash' 
				) );
				// Mlog::addone ( $cm, '::HLEN !memreas_eid_hash result --->' . $reply );
			}
			
			/**
			 * - Friends event cache filtered for valid viewable and ghost dates
			 */
			if (! $this->sExists ( '!memreas_friends_events_' . $user_id )) {
				// Mlog::addone ( $cm, '::building cache for friends events started...' . date ( 'Y-m-d H:i:s.u' ) );
				$events_result = $eventRep->createEventCache ( 'friends' );
				// //Mlog::addone($cm. '::createEventCache results for friends...' , $events_result, 'p');
				// //Mlog::addone ( $cm . '::warmMemreasSet Friends Count --->', count ( $events_result ) );
				foreach ( $events_result as $eventIndex ) {
					// Mlog::addone ( 'friend event after createEventCache top of redis add loop $event ---> ', $event, 'p' );
					$event_id = $event ['event_id'];
					$event_owner = $event ['user_id'];
					// //Mlog::addone($cm. '::for loop $event--->' , $event, 'p');
					if ($event ['user_id'] != $user_id) {
						
						/**
						 * - Check for viewable from / to since db date is string
						 */
						if ((! empty ( $event ['viewable_from'] )) && (! empty ( $event ['viewable_to'] ))) {
							$now = time ();
							/**
							 * check if now is outside dates
							 */
							if (($now < $event ['viewable_from']) || ($now > $event ['viewable_to'])) {
								// Mlog::addone ( "warmMemreasSet::checking viewable from to bounds event_id is out of bounds for now $now --> ", $event ['event_id'] );
								continue;
							}
						}
						/**
						 * - Check for self_destruct/ghost since db date is string
						 */
						if (! empty ( $event ['self_destuct'] )) {
							$now = time ();
							/**
							 * check if now is outside dates
							 */
							if (($now > $event ['self_destruct'])) {
								// Mlog::addone ( "warmMemreasSet::checking ghost is out of bounds for now $now --> ", $event ['event_id'] );
								continue;
							}
						}
						
						/**
						 * Add to sorted set here
						 * Note: event_owner_key is set to ensure uniqueness of key (user_id_event_id)
						 * owner_name_key needs to have create time as part of key since same owner can create event with same name
						 */
						
						$event_name_key = $event ['name'] . '{' . $event_owner . '_' . $event ['create_time'] . '}';
						$event_owner_key = $event_owner . '_' . $event_id;
						$reply = $this->cache->zadd ( '!memreas_friends_events_' . $user_id, 0, $event_name_key );
						$reply = $this->cache->hset ( "!memreas_meta_hash", $event_name_key, $event_owner_key );
						Mlog::addone ( '!memreas_eid_hash adding $eventIndex', $eventIndex, 'p' );
						$reply = $this->cache->hset ( "!memreas_eid_hash", $event_owner_key, json_encode ( $eventIndex ) );
					}
				} // end for
				/**
				 * Adding here would reduce REDIS hits but not working
				 */
				// $this->cache->zadd ( '!memreas_friends_events_'.$user_id, $friends_event_names );
				// $this->cache->hmset ( '!memreas_friends_events_meta_hash', $friends_event_meta_hash );
				// $this->cache->hmset ( '!memreas_friends_events_eid_hash', $friends_event_hash );
				
				/**
				 * Check the counts
				 */
				$reply = $this->cache->executeRaw ( array (
						'ZCARD',
						'!memreas_friends_events_' . $user_id 
				) );
				// Mlog::addone ( $cm, '::Friends ZCARD !memreas_friends_events_' . $user_id . ' result --->' . $reply );
				
				$reply = $this->cache->executeRaw ( array (
						'HLEN',
						'!memreas_meta_hash' 
				) );
				// Mlog::addone ( $cm, '::public and friends HLEN !memreas_meta_hash result --->' . $reply );
				
				$reply = $this->cache->executeRaw ( array (
						'HLEN',
						'!memreas_eid_hash' 
				) );
				// Mlog::addone ( $cm, '::HLEN !memreas_eid_hash result --->' . $reply );
				/**
				 * Set expire for each set
				 */
				$reply = $this->setExpire ( '!memreas' );
				// Mlog::addone ( $cm . __LINE__, '::!memreas setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '!memreas_friends_events_' . $user_id );
				// Mlog::addone ( $cm . __LINE__, '::!memreas_friends_events_' . $user_id . ' setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '!memreas_eid_hash' );
				// Mlog::addone ( $cm . __LINE__, '::!memreas_eid_hash setExpire reply ---> ' . $reply );
				$reply = $this->setExpire ( '!memreas_meta_hash' );
				// Mlog::addone ( $cm . __LINE__, '::!memreas_meta_hash setExpire reply ---> ' . $reply );
			} // end if (! $this->sExists ( '!memreas_friends_events_' . $user_id ))
			
			$warming = $this->cache->set ( 'warming_memreas', '0' );
			// Mlog::addone ( $cm, '::cache warming @warming_memreas finished...' . date ( 'Y-m-d H:i:s.u' ) );
		} // end if (! $warming_memreas || ($warming_memreas == "(nil)"))
	}
	
	/**
	 * Redis cache for all users
	 */
	public function warmPersonSet() {
		$cm = __CLASS__ . __METHOD__;
		sleep ( 1 );
		$warming = $this->cache->get ( 'warming' );
		if (! $warming) {
			error_log ( "cache warming @person started..." . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
			$warming = $this->cache->set ( 'warming', '1' );
			
			$url_signer = new MemreasSignedURL ();
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u.user_id', 'u.username', 'u.email_address', 'm.metadata' );
			$qb->from ( 'Application\Entity\User', 'u' );
			// $qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id' );
			$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
			
			// error_log("Inside warming qb ----->".$qb->getDql().PHP_EOL);
			// create index for catch;
			$userIndexArr = $qb->getQuery ()->getResult ();
			$person_meta_hash = array ();
			$person_uid_hash = array ();
			foreach ( $userIndexArr as $row ) {
				$json_array = json_decode ( $row ['metadata'], true );
				if (empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) {
					$url1 = $this->url_signer->signArrayOfUrls ( null );
				} else {
					$url1 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] );
				}
				// decode here because result will be encoded
				// error_log('$url1---->'.$url1);
				$url1 = json_decode ( $url1 );
				$url1 = $url1 [0];
				
				$person_json = json_encode ( array (
						'username' => $row ['username'],
						'user_id' => $row ['user_id'],
						'email_address' => $row ['email_address'],
						'profile_photo' => $url1 
				) );
				/*
				 * TODO: need to send this in one shot
				 */
				$person_meta_hash [$row ['username']] = $person_json;
				$person_uid_hash [$row ['user_id']] = $row ['username'];
				$usernames [$row ['username']] = 0;
				// $result = $this->cache->zadd ( '@person', 0, $row ['username'] );
				// error_log ( "Inside warming zadd result " . $result . " username--->" . $row ['username'] . " user_id--->" . $row ['user_id'] . PHP_EOL );
			}
			// $result = $this->cache->zadd ( '@person', 0, $usernames );
			$result = $this->cache->zadd ( '@person', $usernames );
			// error_log ( 'zadd array $result--->' . print_r ( $result, true ) . PHP_EOL );
			$reply = $this->cache->hmset ( '@person_meta_hash', $person_meta_hash );
			$reply = $this->cache->hmset ( '@person_uid_hash', $person_uid_hash );
			
			// Finished warming so reset flag
			$warming = $this->cache->set ( 'warming', '0' );
			
			/**
			 * Set expire for each set
			 */
			$reply = $this->setExpire ( '@person' );
			// Mlog::addone ( $cm . __LINE__, '::@person setExpire reply ---> ' . $reply );
			$reply = $this->setExpire ( '@person_meta_hash' );
			// Mlog::addone ( $cm . __LINE__, '::@person_meta_hash setExpire reply ---> ' . $reply );
			$reply = $this->setExpire ( '@person_uid_hash' );
			// Mlog::addone ( $cm . __LINE__, '::@person_uid_hash setExpire reply ---> ' . $reply );
		}
	}
	public function findSet($set, $match) {
		$cm = __CLASS__ . __METHOD__;
		error_log ( "Inside findSet.... set $set match $match" . PHP_EOL );
		// Scan the hash and return 0 or the sub-array
		$result = $this->cache->executeRaw ( array (
				'ZRANGEBYLEX',
				$set,
				"[" . $match,
				"(" . $match . "~" 
		) );
		if ($result != "(empty list or set)") {
			$matches = $result;
		} else {
			$matches = 0;
		}
		return $matches;
	}
	
	/**
	 * Check if set exists
	 */
	public function sExists($set) {
		$cm = __CLASS__ . __METHOD__;
		// //Mlog::addone($cm,'::inside sExists for $set-->'.$set);
		$result = $this->cache->executeRaw ( array (
				'EXISTS',
				$set 
		) );
		// //Mlog::addone ( 'sExists($set)', $set . ' result::' . $result );
		return $result;
	}
	
	/**
	 * Check if hash exists
	 */
	public function hExists($hash, $field) {
		$cm = __CLASS__ . __METHOD__;
		$this->cache->executeRaw ( array (
				'HEXISTS',
				$hash,
				$field 
		) );
		// //Mlog::addone ( 'hExists($hash, $field)', '::$set' . $hash . '::$field' . $field . ' result::' . $result );
		return $result;
	}
	public function addSet($set, $key, $val = null) {
		$cm = __CLASS__ . __METHOD__;
		if (is_null ( $val ) && ! $this->cache->executeRaw ( array (
				'ZCARD',
				$set,
				$key 
		) )) {
			return $this->cache->zadd ( "$set", "$key" );
		} else if (! is_null ( $val )) {
			// error_log("addSet $set:$key:$val".PHP_EOL);
			return $this->cache->hset ( "$set", "$key", "$val" );
		} else {
			// do nothing key exists...
			// error_log("addSet $set:$key already exists...".PHP_EOL);
		}
	}
	public function hasSet($set, $hash = false) {
		$cm = __CLASS__ . __METHOD__;
		// Scan the hash and return 0 or the sub-array
		if ($hash) {
			$result = $this->cache->executeRaw ( array (
					'HLEN',
					$set 
			) );
		} else {
			$result = $this->cache->executeRaw ( array (
					'ZCARD',
					$set 
			) );
		}
		// error_log ( "hasSet result set $set ------> " . json_encode ( $result ) . PHP_EOL );
		// Debugging
		// if ($set = "@person") {
		// return 0;
		// }
		
		return $result;
	}
	public function getSet($set) {
		$cm = __CLASS__ . __METHOD__;
		return $this->cache->smembers ( $set, true );
	}
	public function remSet($set) {
		$cm = __CLASS__ . __METHOD__;
		$this->cache->executeRaw ( array (
				'DEL',
				$set 
		) );
	}
	public function remSetKeys($set) {
		$cm = __CLASS__ . __METHOD__;
		$i = 0;
		foreach ( $set as $cacheKey ) {
			$i += $this->cache->del ( $cacheKey );
		}
		return $i;
	}
	public function getCache($key) {
		$cm = __CLASS__ . __METHOD__;
		if (! $this->isCacheEnable) {
			// error_log("isCacheEnable ----> ".$this->isCacheEnable.PHP_EOL);
			return false;
		}
		
		$result = $this->cache->get ( $key );
		
		//if ($result) {
		//	error_log('JUST FETCHED THIS KEY ----> ' . $key . PHP_EOL);
		//} else {
		//	error_log ( 'COULD NOT FIND THIS KEY GOING TO DB ----> ' . $key . PHP_EOL );
		//}
		
		return $result;
	}
	public function invalidateCache($key) {
		$cm = __CLASS__ . __METHOD__;
		if (! $this->isCacheEnable) {
			return false;
		}
		
		$result = $this->cache->del ( $key );
		// if ($result) {
		// // error_log('JUST DELETED THIS KEY ----> ' . $key . PHP_EOL);
		// } else {
		// error_log ( 'COULD NOT DELETE THIS KEY ----> ' . $key . PHP_EOL );
		// }
	}
	public function invalidateCacheMulti($keys) {
		$cm = __CLASS__ . __METHOD__;
		if (! $this->isCacheEnable) {
			return false;
		}
		
		return $this->cache->deleteMulti ( $keys );
		// $result = $this->cache->deleteMulti ( $keys );
		// if ($result) {
		// // error_log('JUST DELETED THESE KEYS ----> ' . json_encode($keys) . PHP_EOL);
		// } else {
		// error_log ( 'COULD NOT DELETE THES KEYS ----> ' . json_encode ( $keys ) . PHP_EOL );
		// }
		// return $result;
	}
	
	/*
	 * Add function to invalidate cache for media
	 */
	public function invalidateMedia($user_id, $event_id = null, $media_id = null) {
		$cm = __CLASS__ . __METHOD__;
		// error_log("Inside invalidateMedia".PHP_EOL);
		// error_log('Inside invalidateMedia $user_id ----> *' . $user_id . '*' . PHP_EOL);
		// error_log('Inside invalidateMedia $event_id ----> *' . $event_id . '*' . PHP_EOL);
		// error_log('Inside invalidateMedia $media_id ----> *' . $media_id . '*' . PHP_EOL);
		// write functions for media
		// - add media event (key is event_id or user_id)
		// - mediainappropriate (key is user id for invalidate)
		// - deletePhoto (key is user id for invalidate)
		// - update media
		// - removeeventmedia
		$cache_keys = array ();
		$event_id = trim ( $event_id );
		if (! empty ( $event_id )) {
			$cache_keys [] = "listallmedia_" . $event_id;
			$cache_keys [] = "geteventdetails_" . $event_id;
		}
		$media_id = trim ( $media_id );
		if (! empty ( $media_id )) {
			$cache_keys [] = "viewmediadetails_" . $media_id;
		}
		$user_id = trim ( $user_id );
		if (! empty ( $user_id )) {
			// countviewevent can return me / friends / public
			$cache_keys [] = "listallmedia_" . $user_id;
			$cache_keys [] = "viewevents_is_my_event_" . $user_id;
			$cache_keys [] = "viewevents_is_friend_event_" . $user_id;
		}
		// Mecached - deleteMulti...
		$result = $this->remSetKeys ( $cache_keys );
		if ($result) {
			$now = date ( 'Y-m-d H:i:s.u' );
			error_log ( 'invalidateCacheMulti JUST DELETED THESE KEYS ----> ' . json_encode ( $cache_keys ) . " time: " . $now . PHP_EOL );
		} else {
			$now = date ( 'Y-m-d H:i:s.u' );
			error_log ( 'invalidateCacheMulti COULD NOT DELETE THES KEYS ----> ' . json_encode ( $cache_keys ) . " time: " . $now . PHP_EOL );
		}
	} // End invalidateMedia
	
	/*
	 * Add function to invalidate cache for events
	 */
	public function invalidateEvents($user_id) {
		$cm = __CLASS__ . __METHOD__;
		error_log ( "Inside invalidateEvents" . PHP_EOL );
		// write functions for media
		// - add event (key is event_id)
		// - removeevent
		if (! empty ( $user_id )) {
			// countviewevent can return me / friends / public
			$cache_keys = array (
					"viewevents_is_my_event_" . $user_id,
					"viewevents_is_friend_event_" . $user_id 
			);
			$this->invalidateCacheMulti ( $cache_keys );
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
		$cm = __CLASS__ . __METHOD__;
		// write functions for media
		// - add event friend
		// - remove event friend
		if (! empty ( $event_id )) {
			// countviewevent can return me / friends / public
			$cache_keys = array (
					"geteventpeople_" . $event_id,
					"viewevents_is_my_event_" . $user_id,
					"viewevents_is_friend_event_" . $user_id 
			);
			$this->invalidateCacheMulti ( $cache_keys );
		}
	}
	
	/*
	 * Add function to invalidate cache for friends
	 */
	public function invalidateFriends($user_id) {
		$cm = __CLASS__ . __METHOD__;
		// write functions for media
		// - add friend
		// - remove friend
		if (! empty ( $event_id )) {
			// countviewevent can return me / friends / public
			$cache_keys = array (
					"listmemreasfriends_" . $user_id,
					"getfriends_" . $user_id 
			);
			$this->invalidateCacheMulti ( $cache_keys );
			$this->invalidateEvents ( $user_id );
		}
	}
	
	/*
	 * Add function to invalidate cache for notifications
	 */
	public function invalidateNotifications($user_id) {
		$cm = __CLASS__ . __METHOD__;
		// write functions for groups
		// - list notification (key is user_id)
		if (! empty ( $user_id )) {
			$this->invalidateCache ( "listnotification_" . $user_id );
		}
	}
	
	/*
	 * Add function to invalidate cache for user
	 */
	public function invalidateUser($user_id) {
		$cm = __CLASS__ . __METHOD__;
		// write functions for groups
		// - list group (key is user_id)
		if (! empty ( $user_id )) {
			$this->invalidateCache ( "getuserdetails_" . $user_id );
		}
	}
	
	/*
	 * Add function to invalidate cache for groups
	 */
	public function invalidateGroups($user_id) {
		$cm = __CLASS__ . __METHOD__;
		// write functions for groups
		// - list group (key is user_id)
		if (! empty ( $user_id )) {
			$this->invalidateCache ( "listgroup_" . $user_id );
		}
	}
}

?>
		