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
	public function __construct($redis, $service_locator) {
		try {
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
			$this->db = new \Predis\Client ( [ 
					'scheme' => 'tcp',
					'host' => MemreasConstants::REDIS_SERVER_ENDPOINT,
					'port' => 6379 
			] );
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__.__METHOD__, '::predis connection exception ---> ' . $e->getMessage () );
		}
		
		// $this->prefix = $prefix;
		$this->prefix = '';
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		$this->mRedis = $redis;
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		$this->url_signer = new MemreasSignedURL ();
		Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		
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
		$this->storeSession ( false );
	}
	public function gc($maxLifetime) {
		// no action necessary because using EXPIRE
	}
	public function startSessionWithSID($sid) {
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
			Mlog::addone ( __CLASS__ . __METHOD__ .__LINE__, 'sid--->'.$sid);
			session_id ( $sid );
			Mlog::addone ( __CLASS__ . __METHOD__ .__LINE__, 'session_id set--->'.$sid);
			Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		session_start ();
			Mlog::addone ( __CLASS__ . __METHOD__ .__LINE__, 'session_start() complete');
		Mlog::addone ( __CLASS__ . __METHOD__ ,__LINE__);
		// error_log ( '_SESSION vars after sid start...' . print_r ( $_SESSION, true ) . PHP_EOL );
	}
	public function startSessionWithMemreasCookie($memreascookie) {
		$rMemreasCookieSession = $this->mRedis->getCache ( 'memreascookie::' . $memreascookie );
		$rMemreasCookieSessionArr = json_decode ( $rMemreasCookieSession, true );
		Mlog::addone ( __CLASS__ . __METHOD__ . ':: fetch $rMemreasCookieSessionArr-->', $rMemreasCookieSessionArr );
		if (! session_id ()) {
			session_id ( $rMemreasCookieSessionArr ['sid'] );
			session_start ();
		}

		/*
		if ($reset) {
			$chameleon_value = $this->checkChameleon ( $x_memreascookie_chameleon );
			if ($chameleon_value) {
				$rMemreasCookieSessionArr ['x_memreas_chameleon'] = $_SESSION ['x_memreas_chameleon'] = $chameleon_value;
				$this->mRedis->setCache ( 'memreascookie::' . $_SESSION ['memreascookie'], json_encode ( $rMemreasCookieSessionArr ) );
			} else {
				// suspicious - failed chameleon test
				$this->closeSessionWithMemreasCookie ();
			}
		}
		Mlog::addone ( __CLASS__ . __METHOD__ . ':: after startSessionWithMemreasCookie $_SESSION[memreascookie]--->', $_SESSION ['memreascookie'] );
		Mlog::addone ( __CLASS__ . __METHOD__ . ':: after startSessionWithMemreasCookie $_SESSION[x_memreas_chameleon]--->', $_SESSION ['x_memreas_chameleon'] );
		*/
	}
	public function startSessionWithUID($data) {
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
		$sql = "SELECT m  FROM Application\Entity\Media as m  where m.user_id = '$uid' and m.is_profile_pic=1";
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
		error_log ( 'Inside setSession' . PHP_EOL );
		if (empty ( session_id () )) {
			session_regenerate_id ();
		}
		
		Mlog::addone ( __CLASS__ . __METHOD__ . '::user---->', $user );
		//
		// Check Headers sent
		//
		// error_log ( __CLASS__ . __METHOD__ . __LINE__ . "headers_list()" . print_r ( headers_list (), true ) . PHP_EOL );
		
		// Set Session vars
		$_SESSION ['user_id'] = $user->user_id;
		$_SESSION ['username'] = $user->username;
		$_SESSION ['sid'] = session_id ();
		$_SESSION ['email_address'] = $user->email_address;
		$_SESSION ['device_id'] = $device_id;
		$_SESSION ['device_type'] = $device_type;
		$_SESSION ['memreascookie'] = $memreascookie;
		$_SESSION ['ipAddress'] = $clientIPAddress;
		$_SESSION ['profile_pic_meta'] = $this->fetchProfilePicMeta ( $user->user_id );
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
		// Mlog::addone(__CLASS__.__METHOD__.':: $_SESSION[profile_pic]', $_SESSION['profile_pic']);
		
		// error_log ( 'setSession(...) _SESSION vars --->' . print_r ( $_SESSION, true ) . PHP_EOL );
		$this->setUIDLookup ();
		$this->storeSession ( true );
		if (! empty ( $memreascookie )) {
			Mlog::addone ( __CLASS__ . __METHOD__ . ':: about to set $this->setMemreasCookieLookup for cookie::', $_SESSION ['memreascookie'] );
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
		error_log ( 'Inside setMemreasCookieLookup' . PHP_EOL );
		$memreascookieArr = array ();
		$memreascookieArr ['user_id'] = $_SESSION ['user_id'];
		$memreascookieArr ['username'] = $_SESSION ['username'];
		$memreascookieArr ['sid'] = $_SESSION ['sid'];
		$memreascookieArr ['device_id'] = $_SESSION ['device_id'];
		$memreascookieArr ['device_type'] = $_SESSION ['device_type'];
		$memreascookieArr ['ipAddress'] = $_SESSION ['ipAddress'];
		$memreascookieArr ['profile_pic_meta'] = $_SESSION ['profile_pic_meta'];
		$memreascookieArr ['profile_pic'] = $_SESSION ['profile_pic'];
		$memreascookieArr ['profile_pic'] = $_SESSION ['profile_pic'];
		
		$this->mRedis->setCache ( 'memreascookie::' . $_SESSION ['memreascookie'], json_encode ( $memreascookieArr ) );
		
		Mlog::addone ( __CLASS__ . __METHOD__ . ':: after setSession $_SESSION--->', $_SESSION ['x_memreas_chameleon'] );
	}
	public function closeSessionWithSID() {
		$this->mRedis->invalidateCache ( 'uid::' . $_SESSION ['user_id'] );
		session_destroy ();
	}
	public function closeSessionWithMemreasCookie() {
		// $this->destroy(session_id());
		$this->mRedis->invalidateCache ( 'memreascookie::' . $_SESSION ['memreascookie'] );
		$this->mRedis->invalidateCache ( 'uid::' . $_SESSION ['user_id'] );
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
				$result = $this->endSession ();
			}
		} catch ( \Exception $e ) {
			/**
			 * End Session
			 */
			$result = $this->endSession ();
		}
	}
	public function endSession() {
		$now = date ( "Y-m-d H:i:s" );
		$q_update = "UPDATE Application\Entity\UserSession u
		SET u.end_time = '$now'
		WHERE u.session_id ='" . session_id () . "'
		and u.user_id = '" . $_SESSION ['user_id'] . "'";
		// error_log ( 'logout update sql ---->' . $q_update . PHP_EOL );
		$statement = $this->dbAdapter->createQuery ( $q_update );
		return $statement->getResult ();
	}
}