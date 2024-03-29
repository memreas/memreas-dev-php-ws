<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\Model\MemreasConstants;

class AWSMemreasRedisSessionHandler implements \SessionHandlerInterface {
	private $ttl = 1800; // 30 minutes default
	private $db;
	private $prefix;
	private $mRedis;
	private $dbAdapter;
	private $url_signer;
	private $aws_manager;
	protected $service_locator;
	private static $handle;
	private static $isInitialized;
	public function __construct($redis, $service_locator) {
		$cm = __CLASS__ . __METHOD__;
		$this->service_locator = $service_locator;
		// Mlog::addone ( $cm . __LINE__ . '::', 'enter __construct' );
		try {
			$this->db = new \Predis\Client ( [ 
					'scheme' => 'tcp',
					'host' => MemreasConstants::REDIS_SERVER_ENDPOINT,
					'port' => 6379 
			] );
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__, '::predis connection exception ---> ' . $e->getMessage () );
		}
		
		$this->prefix = '';
		$this->mRedis = $redis;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
		self::$isInitialized = true;
		self::$handle = $this;
		// Mlog::addone ( $cm . __LINE__ . '::', 'exit __construct' );
	}
	public static function getHandle() {
		if (! empty ( self::$isInitialized )) {
			return self::$handle;
		}
	}
	public function open($savePath, $sessionName) {
		// No action necessary because connection is injected
		// in constructor and arguments are not applicable.
	}
	public function close() {
		$this->db = null;
		unset ( $this->db );
	}
	public function read($id) {
		$id = $this->prefix . $id;
		$sessData = $this->db->get ( $id );
		$this->db->expire ( $id, $this->ttl );
		return $sessData;
	}
	public function write($id, $data) {
		$id = $this->prefix . $id;
		$this->db->set ( $id, $data );
		$this->db->expire ( $id, $this->ttl );
	}
	public function destroy($id) {
		$this->db->del ( $this->prefix . $id );
		//$this->storeSession ( false );
	}
	public function gc($maxLifetime) {
		// no action necessary because using EXPIRE
	}
	public function startSessionWithSID($sid) {
		session_id ( $sid );
		$result = session_start ();
		if ($result) {
			//
			// store to reset ttl
			//
			$rMemreasSidSession = $this->mRedis->getCache ( $sid );
			$result = $this->mRedis->setCache ( $sid, $rMemreasSidSession, MemreasConstants::REDIS_CACHE_SESSION_DEVICE_TTL );
		}
		
		return $result;
	}
	public function startSessionWithMemreasCookie($memreascookie, $x_memreas_chameleon = '', $actionname = '') {
		$rMemreasCookieSession = $this->mRedis->getCache ( 'memreascookie::' . $memreascookie );
		$result = false;
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$rMemreasCookieSession-->', $rMemreasCookieSession );
		if ($rMemreasCookieSession) {
			$rMemreasCookieSessionArr = json_decode ( $rMemreasCookieSession, true );
			session_id ( $rMemreasCookieSessionArr ['sid'] );
			session_start ();
			// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::startSessionWithMemreasCookie sid is-->', $rMemreasCookieSessionArr ['sid'] );
			$result = true;
			
			$fetchChameleon = new FetchChameleon ();
			$fetchChameleon->setChameleon ();
			
			// set back to cache to reset ttl
			$result = $this->mRedis->setCache ( 'memreascookie::' . $memreascookie, $rMemreasCookieSession, MemreasConstants::REDIS_CACHE_SESSION_TTL );
			
			return $result;
		} else {
			//
			// Can't find session so logout
			//
			$logout = new LogOut ();
			$result = $logout->exec ( $this );
		}
		
		// not worknig ---reset the cache with the updated data...
		// $this->setMemreasCookieLookup ( true );
		
		// $_SESSION ['x_memreas_chameleon'] = $fetchChameleon->setChameleon();
		// if ($actionname == 'login') {
		// $fetchChameleon->setChameleon ();
		// } else {
		// if (! $fetchChameleon->checkChameleon ( $x_memreas_chameleon )) {
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::failed fetchChameleon->checkChameleon($x_memreas_chameleon)-->', $x_memreas_chameleon );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::failed fetchChameleon->checkChameleon($x_memreas_chameleon) $_SESSION[x_memreas_chameleon]-->', $_SESSION ['x_memreas_chameleon'] );
		// }
		// }
		// $chameleon_pass = $fetchChameleon->checkChameleon ( $x_memreascookie_chameleon );
		// if ($chameleon_pass) {
		// $x_memreascookie_chameleon = $fetchChameleon->setChameleon();
		// } else {
		// // suspicious - failed chameleon test
		// $this->closeSessionWithMemreasCookie ();
		// }
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . ':: after startSessionWithMemreasCookie $_SESSION[memreascookie]--->', $_SESSION ['memreascookie'] );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . ':: after startSessionWithMemreasCookie $_SESSION[x_memreas_chameleon]--->', $_SESSION ['x_memreas_chameleon'] );
	}
	public function startSessionWithUID($data) {
		// Mlog::addone(__CLASS__.__METHOD__, __LINE__);
		if (! empty ( $data->uid )) {
			$rUIDSession = $this->mRedis->getCache ( 'uid::' . $data->uid );
		} else if (! empty ( $data->username )) {
			$rUIDSession = $this->mRedis->getCache ( 'username::' . $data->username );
		}
		if ($rUIDSession) {
			// error_log ( 'startSessionWithUID pulling from redis...' . PHP_EOL );
			$rUIDSessionArr = json_decode ( $rUIDSession, true );
			if (! session_id ()) {
				session_id ( $rUIDSessionArr ['sid'] );
				session_start ();
			}
			// error_log ( 'rUIDSessionArr vars after uid start...' . print_r ( $rUIDSessionArr, true ) . PHP_EOL );
		} else {
			// error_log ( 'startSessionWithUID pulling from db...' . PHP_EOL );
			if (! empty ( $data->uid )) {
				$sql = "SELECT u  FROM Application\Entity\User as u  where u.user_id = '{$data->uid}'";
			} else {
				$sql = "SELECT u  FROM Application\Entity\User as u  where u.username = '{$data->username}'";
			}
			$statement = $this->dbAdapter->createQuery ( $sql );
			$row = $statement->getResult ();
			if (! empty ( $row )) {
				/*
				 * Set the session for the user data...
				 */
				$this->setSession ( $row [0], '', 'web', '', '127.0.0.1' );
			}
		}
		// error_log ( '_SESSION vars after uid start...' . print_r ( $_SESSION, true ) . PHP_EOL );
	}
	public function fetchProfilePicMeta($uid) {
		/*
		 * Check for profile photo
		 */
		$sql = "SELECT m FROM Application\Entity\Media as m  where m.user_id = '$uid' and m.is_profile_pic=1";
		$statement = $this->dbAdapter->createQuery ( $sql );
		$profile = $statement->getResult ();
		
		$meta = '';
		if ($profile) {
			$meta = $profile [0]->metadata;
		}
		return $meta;
	}
	public function setSession($user, $device_id = '', $device_type = '', $memreascookie = '', $clientIPAddress = '') {
		session_start ();
		Mlog::addone(__CLASS__.__METHOD__.__LINE__,'Inside setSession');
		Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$memreascookie', $memreascookie);
		Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$clientIPAddress', $clientIPAddress);
		if (empty ( session_id () )) {
			session_regenerate_id ();
		}
		
		//
		// Check Headers sent
		//
		
		//
		// Set Session vars
		//
		$_SESSION ['user_id'] = $user->user_id;
		$_SESSION ['username'] = $user->username;
		$_SESSION ['sid'] = session_id ();
		$_SESSION ['email_address'] = $user->email_address;
		$_SESSION ['device_id'] = $device_id;
		$_SESSION ['device_type'] = $device_type;
		$_SESSION ['memreascookie'] = $memreascookie;
		$_SESSION ['ipAddress'] = $clientIPAddress;
		$_SESSION ['profile_pic_meta'] = $this->fetchProfilePicMeta ( $user->user_id );
		
		//
		// Set Profile Pic Url
		//
		$json_pic_meta = json_decode ( $_SESSION ['profile_pic_meta'], true );
		if ($_SESSION ['profile_pic_meta']) {
			if (isset ( $json_pic_meta ['S3_files'] ['thumbnails'] ['79x80'] )) {
				$_SESSION ['profile_pic'] = $this->url_signer->signArrayOfUrls ( $json_pic_meta ['S3_files'] ['thumbnails'] ['79x80'] );
			} else {
				$_SESSION ['profile_pic'] = $this->url_signer->signArrayOfUrls ( $json_pic_meta ['S3_files'] ['full'] );
			}
		} else {
			$_SESSION ['profile_pic'] = $this->url_signer->signArrayOfUrls ( null );
		}
		
		//
		// Store to user session table
		//
		$this->storeSession ( true );
		
		//
		// handle x_memreas_chameleon on login $_SESSION is set in function
		//
		$fetchChameleon = new FetchChameleon ();
		$fetchChameleon->setChameleon ();
		
		// Mlog::addone(__CLASS__.__METHOD__.':: $_SESSION[profile_pic]', $_SESSION['profile_pic']);
		$this->setUIDLookup ();
		if (! empty ( $memreascookie )) {
			// Mlog::addone ( __CLASS__ . __METHOD__ . ':: about to set $this->setMemreasCookieLookup for cookie::', $_SESSION ['memreascookie'] );
			$this->setMemreasCookieLookup ( true );
		}
	}
	public function setUIDLookup() {
		// error_log ( 'Inside setUserNameLookup' . PHP_EOL );
		$userNameArr = array ();
		$userNameArr ['user_id'] = $_SESSION ['user_id'];
		$userNameArr ['username'] = $_SESSION ['username'];
		$userNameArr ['sid'] = $_SESSION ['sid'];
		$userNameArr ['device_id'] = $_SESSION ['device_id'];
		$userNameArr ['device_type'] = $_SESSION ['device_type'];
		$userNameArr ['ipAddress'] = $_SESSION ['ipAddress'];
		$userNameArr ['profile_pic_meta'] = $_SESSION ['profile_pic_meta'];
		$userNameArr ['profile_pic'] = $_SESSION ['profile_pic'];
		$this->mRedis->setCache ( 'uid::' . $_SESSION ['user_id'], json_encode ( $userNameArr ) );
		$this->mRedis->setCache ( 'username::' . $_SESSION ['username'], json_encode ( $userNameArr ) );
	}
	public function setMemreasCookieLookup($create = false) {
		$memreascookieArr = array ();
		$memreascookieArr ['user_id'] = $_SESSION ['user_id'];
		$memreascookieArr ['username'] = $_SESSION ['username'];
		$memreascookieArr ['sid'] = $_SESSION ['sid'];
		$memreascookieArr ['device_id'] = $_SESSION ['device_id'];
		$memreascookieArr ['device_type'] = $_SESSION ['device_type'];
		$memreascookieArr ['ipAddress'] = $_SESSION ['ipAddress'];
		$memreascookieArr ['profile_pic_meta'] = $_SESSION ['profile_pic_meta'];
		$memreascookieArr ['profile_pic'] = $_SESSION ['profile_pic'];
		$memreascookieArr ['x_memreas_chameleon'] = $_SESSION ['x_memreas_chameleon'];
		
		$this->mRedis->setCache ( 'memreascookie::' . $_SESSION ['memreascookie'], json_encode ( $memreascookieArr ) );
	}
	public function closeSessionWithSID() {
		$this->mRedis->invalidateCache ( 'uid::' . $_SESSION ['user_id'] );

		//
		// Store to user session table - end
		//
		$this->storeSession ( false );
		
		session_destroy ();
	}
	public function closeSessionWithMemreasCookie() {
		$this->mRedis->invalidateCache ( 'memreascookie::' . $_SESSION ['memreascookie'] );
		$this->mRedis->invalidateCache ( 'uid::' . $_SESSION ['user_id'] );
		
		//
		// Store to user session table - end
		//
		$this->storeSession ( false );
		
		session_destroy ();
	}
	public function storeSession($start) {
		try {
			$now = date ( "Y-m-d H:i:s" );
			if ($start) {
				/**
				 * Start Session
				 */
				$meta = array ();
				$meta ['username'] = $_SESSION ['username'];
				$meta ['device_type'] = $_SESSION ['device_type'];
				$meta ['memreascookie'] = $_SESSION ['memreascookie'];
				$tblUserSession = new \Application\Entity\UserSession ();
				$tblUserSession->session_id = session_id ();
				$tblUserSession->user_id = $_SESSION ['user_id'];
				$tblUserSession->ipaddress = $_SESSION ['ipAddress'];
				$tblUserSession->device_id = $_SESSION ['device_id'];
				$tblUserSession->meta = json_encode ( $meta );
				$tblUserSession->start_time = $now;
				
				$this->dbAdapter->persist ( $tblUserSession );
				$this->dbAdapter->flush ();
			} else {
				/**
				 * End Session
				 */
				$sessionObj = $this->dbAdapter->getRepository ( "\Application\Entity\UserSession" )->findOneBy ( array (
						'session_id' => session_id () 
				) );
				$sessionObj->end_time = $now;
				
				$this->dbAdapter->persist ( $sessionObj );
				$this->dbAdapter->flush ();
			}
		} catch ( \Exception $e ) {
			Mlog::addone(__CLASS__.__METHOD__.__LINE__, $e->getMessage());
		}
	}
	/*
	public function endSession() {
		$now = date ( "Y-m-d H:i:s" );
		$q_update = "UPDATE Application\Entity\UserSession u
		SET u.end_time = '$now'
		WHERE u.session_id ='" . session_id () . "'
		and u.user_id = '" . $_SESSION ['user_id'] . "'";
		$statement = $this->dbAdapter->createQuery ( $q_update );
		return $statement->getResult ();
	}
	*/
}