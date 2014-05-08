<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants AS MC;
use Application\Facebook\Facebook;
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
	protected $media_id;
	protected $fb;
	protected $twitter;
	public function __construct($service_locator) {
		$config = $service_locator->get('Config');
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
		$this->userIds [] = $userid;
	}
	public function addFriend($friendid) {
		$this->friends  = $friendid;
	}
	public function send() {
		try {
			// mobile notification.
			if (count ( $this->userIds ) > 0) {
error_log("Inside mobile notification...".PHP_EOL);				
				
		 $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select ( 'f' );
        $qb->from ( 'Application\Entity\Device', 'f' );
          $qb->andWhere('f.user_id IN (:x)')
         		->setParameter('x', $this->userIds );

        $users = $qb->getQuery ()->getArrayResult ();
				
				
				if (count ( $users ) > 0) {
error_log("Inside mobile notification - count ( users ) > 0 ...".PHP_EOL);
						
					foreach ( $users as $user ) {
						error_log ( 'user-id- ' . $user ['user_id'] . '  devicetype-' . $user ['device_type'] . PHP_EOL );
						if ($user ['device_type'] == \Application\Entity\Device::ANROID) {
							$this->gcm->addDevice ( $user ['device_token'] );
						} else if ($user ['device_type'] == \Application\Entity\Device::APPLE) {
							$this->apns->addDevice ( $user ['device_token'] );
						}
					}
					$x = '';
					if ($this->gcm->getDeviceCount () > 0) {
						
						$x = $this->gcm->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
						error_log ( 'SENDING-ANROID' . print_r ( $x, true ) . PHP_EOL );
					}
					$x = '';
					if ($this->apns->getDeviceCount () > 0) {
						
						$x = $this->apns->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
						error_log ( 'SENDING-Apple' . print_r ( $x, true ) . PHP_EOL );
					}
				}
				// memreas user fb twitter
				$this->webNotification ();
			}
			// non memras users fb twitter
			$this->webNotification ();
		} catch ( \Exception $exc ) {error_log('exp-notifcation class'.$exc->getMessage());
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
	public function webNotification() {
error_log ( "Inside web notification...".PHP_EOL );
		if (count ( $this->friends ) > 0) {
error_log ( "Inside web notification - count ( this->friends ) > 0...".PHP_EOL );
			// web notification
			$get_user = "SELECT f  FROM  Application\Entity\Friend f where f.friend_id in(?1)";
			$statement = $this->dbAdapter->createQuery ( $get_user );
			$statement->setParameter(1, $this->friends);
error_log ( "Inside web notification - statement ---> $get_user . ".$this->friends.PHP_EOL );
			$users = $statement->getArrayResult ();
			
			if (count ( $users ) > 0) {
error_log ( "Inside web notification - count ( users ) > 0...".PHP_EOL );
				$fbparams = array (
						'href' => $this->fbhref,
						'template' => $this->message 
				);
				$twparams ['text'] = $this->message;
				foreach ( $users as $user ) {
					switch (strtolower ( $user ['network'] )) {
						case 'facebook' :
							error_log ( 'SENDING-FB'.$user ['friend_id'] );
							$result = $this->fb->api ( '/' . $user ['friend_id'] . '/notifications/', 'post', $fbparams );
error_log ( 'FB-PARAMS--->'.$fbparams );
error_log ( 'FB-RESULT--->'.print_r($result,true).PHP_EOL );
							break;
						case 'twitter' :
							error_log ( 'SENDING-TWITTER'.$user ['friend_id'] );
							$twparams ['user_id'] = $user ['friend_id'];
							$result = $this->twitter->post ( 'direct_messages/new', $twparams );
error_log ( 'TWITTER-PARAMS--->'.$fbparams );
error_log ( 'FB-RESULT--->'.print_r($result,true).PHP_EOL );
							break;
						default :
							break;
					}
				}
			}
		}
	}


}

?>
