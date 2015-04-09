<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants as MC;
use Application\Facebook\Facebook;
// use Facebook\FacebookSession;
// use Facebook\FacebookCanvasLoginHelper;
use Application\TwitterOAuth\TwitterOAuth;
use \Exception;

class Notification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $userIds;
	protected $friends;
	protected $message;
	protected $gcm;
	protected $apns;
	protected $type;
	protected $event_id;
	protected $event_name;
	protected $media_id;
	protected $fb;
	protected $session;
	protected $twitter;
	protected $device_id;
	public function __construct($service_locator) {
		$config = $service_locator->get ( 'Config' );
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		if (! $this->gcm) {
			$this->gcm = new gcm ( $service_locator );
		}
		if (! $this->apns) {
			$this->apns = new apns ( $service_locator );
		}
		
		if (! $this->fb) {
			$config = array ();
			$config ['appId'] = MC::FB_APPID;
			$config ['secret'] = MC::FB_SECRET;
			$this->fbhref = MC::FB_FBHREF;
			$this->fb = new Facebook ( $config );
			$this->fb->setAccessToken ( $config ['appId'] . '|' . $config ['secret'] );
		}
		
		if (! $this->twitter) {
			
			$config = array ();
			$config ['consumer_key'] = MC::TW_CONSUMER_KEY;
			$config ['consumer_secret'] = MC::TW_CONSUMER_SECRET;
			$config ['oauth_token'] = MC::TW_OAUTH_TOKEN;
			$config ['oauth_token_secret'] = MC::TW_OAUTH_TOKEN_SECRET;
			$config ['output_format'] = 'object';
			$this->twitter = new TwitterOAuth ( $config );
		}
	}
	public function add($userid) {
		error_log ( "Notification add userid-->" . $userid . PHP_EOL );
		$this->userIds [] = $userid;
	}
	public function addFriend($friendid) {
		error_log ( "Notification add friendid-->" . $friendid . PHP_EOL );
		$this->friends [] = $friendid;
	}
	public function send() {
		try {
			error_log ( "Notification::Inside send()" . PHP_EOL );
			// mobile notification.
			if (count ( $this->userIds ) > 0) {
error_log ( "Notification::Inside send() count ( this->userIds ) " . count ( $this->userIds )  . PHP_EOL );
				
				/*
				 * Find the device tokens by user_id
				 */
				$qb = $this->dbAdapter->createQueryBuilder ();
				$qb->select ( 'd' );
				$qb->from ( 'Application\Entity\Device', 'd' );
				$qb->andWhere ( 'd.user_id IN (:x)' )->setParameter ( 'x', $this->userIds );
				$devices = $qb->getQuery ()->getArrayResult ();
				
				foreach ( $devices as $device ) {
error_log ( "device_id->" . $device ['device_id'] . "::user_id->" . $device ['user_id'] . "::device_token->" . $device ['device_token'] . "::device_type->" . $device ['device_type'] . PHP_EOL );
					if ($device ['device_type'] == \Application\Entity\Device::ANDROID) {
error_log ( "Notification::Inside send()->adding to Android list" . PHP_EOL );
						$this->gcm->addDevice (  $device ['device_token'] );
					} else if ($user ['device_type'] == \Application\Entity\Device::APPLE) {
error_log ( "Notification::Inside send()->adding to Apple list" . PHP_EOL );
						$this->apns->addDevice (  $device ['device_token'] );
					}
					$gcm_push_notification_result = '';
					if ($this->gcm->getDeviceCount () > 0) {
						$push_notification_result = $this->gcm->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
error_log ( 'SENDING-ANROID' . print_r ( $push_notification_result, true ) . PHP_EOL );
					}
					$ios_push_notification_result = '';
					if ($this->apns->getDeviceCount () > 0) {
						$iox_push_notification_result = $this->apns->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
error_log ( 'SENDING-Apple' . print_r ( $push_notification_result, true ) . PHP_EOL );
					}
				}
			}
		} catch ( \Exception $exc ) {
			error_log ( 'exp-notifcation class' . $exc->getMessage () );
		}
	}
	public function setUpdateMessage($notification_type, $data = '') {
		switch ($notification_type) {
			case 1 :
				$this->message = "Friend Request";
				break;
			case 2 :
				$this->message = "Add Friend to Event";
				break;
			case 3 :
				$this->message = "Add Media ";
				break;
			case 4 :
				$this->message = "Add Comment";
				break;
			case 5 :
				$this->message = "Update on Event";
				break;
		}
		
		// echo '<pre>';print_r($notification_type);exit;
	}
	public function setMessage($message) {
		$this->message = $message;
	}
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}

?>
