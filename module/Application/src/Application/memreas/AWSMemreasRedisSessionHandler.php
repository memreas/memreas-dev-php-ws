<?php

namespace Application\memreas;

use Application\Model\MemreasConstants;

class AWSMemreasRedisSessionHandler implements \SessionHandlerInterface {
	public $ttl = 1800; // 30 minutes default
	protected $db;
	protected $prefix;
	protected $mRedis;
	protected $dbAdapter;
	
	// public function __construct($prefix = 'PHPSESSID:') {
	public function __construct($redis, $service_locator) {
		$this->db = new \Predis\Client ( [ 
				'scheme' => 'tcp',
				'host' => MemreasConstants::REDIS_SERVER_ENDPOINT,
				'port' => 6379 
		] );
		// $this->prefix = $prefix;
		$this->prefix = '';
		$this->mRedis = $redis;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
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
	
	/**
	 * User defined
	 */
	public function startSessionWithSID($sid) {
		session_id ( $sid );
		session_start ();
error_log('_SESSION vars after sid start...'.print_r($_SESSION, true).PHP_EOL);		
	}
	public function startSessionWithFECookie($fecookie) {
		$rFESession = $this->mRedis->getCache ( 'fecookie::'.$fecookie );
		$rFESessionArr = json_decode ( $rFESession, true );
		session_id ( $rFESessionArr ['sid'] );
		session_start ();
error_log('_SESSION vars after fecookie start...'.print_r($_SESSION, true).PHP_EOL);		
	}
	public function setSession($user, $device_id = '', $device_type = '', $fecookie = '', $clientIPAddress = '') {
		error_log ( 'Inside setSession' . PHP_EOL );
		session_start ();
		$_SESSION ['user_id'] = $user->user_id;
		$_SESSION ['username'] = $user->username;
		$_SESSION ['sid'] = session_id ();
		$_SESSION ['email_address'] = $user->email_address;
		$_SESSION ['device_id'] = $device_id;
		$_SESSION ['device_type'] = $device_type;
		$_SESSION ['fecookie'] = $fecookie;
		$_SESSION ['ipAddress'] = $clientIPAddress;
		//error_log ( 'setSession(...) _SESSION vars --->'.print_r($_SESSION, true) . PHP_EOL );
		$this->setUserNameLookup();
		$this->storeSession ( true );
		if (!empty ($fecookie)) {
			$this->setFELookup();
		}
	}
	
	public function setUserNameLookup() {
		error_log ( 'Inside setUserNameLookup' . PHP_EOL );
		$userNameArr = array ();
		$userNameArr ['user_id'] = $_SESSION ['user_id'];
		$userNameArr ['username'] = $_SESSION ['username'];
		$userNameArr ['sid'] = $_SESSION ['sid'];
		$userNameArr ['device_id'] = $_SESSION ['device_id'];
		$userNameArr ['device_type'] = $_SESSION ['device_type'];
		$userNameArr ['ipAddress'] = $_SESSION ['ipAddress'];
		$this->mRedis->setCache ( 'uid::'.$_SESSION ['user_id'], json_encode ( $userNameArr ) );
	}
	
	public function setFELookup() {
		error_log ( 'Inside setFELookup' . PHP_EOL );
		$fecookieArr = array ();
		$fecookieArr ['user_id'] = $_SESSION ['user_id'];
		$fecookieArr ['username'] = $_SESSION ['username'];
		$fecookieArr ['sid'] = $_SESSION ['sid'];
		$fecookieArr ['device_id'] = $_SESSION ['device_id'];
		$fecookieArr ['device_type'] = $_SESSION ['device_type'];
		$fecookieArr ['ipAddress'] = $_SESSION ['ipAddress'];
		//error_log ( 'setFELookup() _SESSION vars --->'.print_r($_SESSION, true) . PHP_EOL );
		$this->mRedis->setCache ( 'fecookie::'.$_SESSION ['fecookie'], json_encode ( $fecookieArr ) );
	}
	public function closeSessionWithSID() {
		$this->mRedis->invalidateCache ( 'uid::'.$_SESSION ['user_id'] );
		session_destroy ();
	}
	
	public function closeSessionWithFECookie() {
		// $this->destroy(session_id());
		$this->mRedis->invalidateCache ( 'fecookie::'.$_SESSION ['fecookie'] );
		$this->mRedis->invalidateCache ( 'uid::'.$_SESSION ['user_id'] );
		session_destroy ();
	}
	public function storeSession($start) {
		$now = date ( "Y-m-d H:i:s" );
		if ($start) {
			/**
			 * Start Session
			 */
			$meta = array ();
			$meta ['username'] = $_SESSION ['username'];
			$meta ['device_type'] = $_SESSION ['device_type'];
			$meta ['fecookie'] = $_SESSION ['fecookie'];
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
			$q_update = "UPDATE Application\Entity\UserSession u  
				SET u.end_time = '$now' 
				WHERE u.session_id ='" . session_id () . "' 
				and u.user_id = '" . $_SESSION ['user_id'] . "'";
			error_log ( 'logout update sql ---->' . $q_update . PHP_EOL );
			$statement = $this->dbAdapter->createQuery ( $q_update );
			$r = $statement->getResult ();
		}
	}
}