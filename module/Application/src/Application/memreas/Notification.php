<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants as MC;
use Application\Facebook\Facebook;
//use Facebook\FacebookSession;
//use Facebook\FacebookCanvasLoginHelper;
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
	public function addDeviceId($deviceId) {
		$this->device_id = $deviceId;
	}
	public function add($userid) {
error_log("Notification add userid-->".$userid.PHP_EOL);
		$this->userIds[] = $userid;
	}
	public function addFriend($friendid) {
error_log("Notification add friendid-->".$friendid.PHP_EOL);
		$this->friends [] = $friendid;
	}
	public function send() {
		try {
error_log("Notification::Inside send()".PHP_EOL);
			// mobile notification.
			if (count ( $this->userIds ) > 0) {
								
				foreach ($this->userIds as $user) {		
						if ($user ['device_type'] == \Application\Entity\Device::ANROID) {
							$this->gcm->addDevice ( $user ['device_token'] );
						} else if ($user ['device_type'] == \Application\Entity\Device::APPLE) {
							$this->apns->addDevice ( $user ['device_token'] );
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
				// to do memreas user fb twitter
				// $this->webNotification ();
			}
			// non memras users fb twitter
			$this->webNotification ();
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
	public function webNotification() {
		if (count ( $this->friends ) > 0) {

error_log ( 'Inside webNotification event_id is ----> ' . $this->event_id.PHP_EOL );
error_log ( 'Inside webNotification event_name ----> ' . $this->event_name.PHP_EOL );
			// web notification
			/*
			 * TODO: this should be web service so we can cache results.
			 * get list of Friends
			 */
			$get_user = "SELECT f  FROM  Application\Entity\Friend f where f.friend_id in(?1)";
			$statement = $this->dbAdapter->createQuery ( $get_user );
			$statement->setParameter ( 1, $this->friends );
			$users = $statement->getArrayResult ();
			
			if (count ( $users ) > 0) {
				$fburl = $this->fbhref."?event_id=".$this->event_id."&event_name=".$this->event_name;
				//it should look like https://apps.facebook.com/462180953876554/?event=060b20d2-c2d5-403c-b058-c71061a69983
error_log ( '$fburl ----> ' . $fburl.PHP_EOL );
error_log ( '$this->message ----> ' . $this->message.PHP_EOL );
				$fbparams = array (
						'href' => $fburl,
						'template' => $this->message 
				);


				$twparams ['text'] = $this->message .'   /fe.memreas.com/'."index?event_id=".$this->event_id."&event_name=".$this->event_name;
				/*
				 * TODO: Check to see if this sends out to all facebook friends - should only be from list
				 */
				foreach ( $users as $user ) {
					switch (strtolower ( $user ['network'] )) {
						case 'facebook' :
							try {
error_log ( 'calling this->fb->api.. '.PHP_EOL );
error_log ( 'calling  user[friend_id].. '. $user['friend_id'].PHP_EOL );
error_log ( 'calling  user[friend_id].. '. $user['friend_id'].PHP_EOL );
error_log ( 'calling  fbparams.. '. json_encode($fbparams).PHP_EOL );
								$result = $this->fb->api ( '/' . $user ['friend_id'] . '/notifications/', 'post', $fbparams );
error_log ( 'called this->fb->api.. '.PHP_EOL );
error_log ( '$result ----> ' . print_r($result,true).PHP_EOL );
								

								/*
								 * 25-SEP-2014 Attempt to upgrade to Facebook SDK 4.x
								 *
error_log("About to check Facebook session...".PHP_EOL);
								if ($this->session) {
									// Logged in
error_log("Facebook session received...".PHP_EOL);
									// PHP SDK v4.0.0 
									// make the API call 
									$request = new FacebookRequest(
											$session,
											'POST',
											'/'.$user ['friend_id'].'/notifications',
											array (
													'href' => $fburl,
													'template' => $this->message,
											)
									);
									$response = $request->execute();
									$graphObject = $response->getGraphObject();
								}
								*/
								
								
								
								error_log ( 'SENDING-FB:' . print_r ( $result, true) . PHP_EOL );
							} catch ( \Exception $exc ) {
								error_log ( 'SENDING-FB:' . print_r ( $exc->getMessage (), true) . PHP_EOL );
							}
							break;
						case 'twitter' :
							$twparams ['user_id'] = $user ['friend_id'];
							try {
								$result = $this->twitter->post ( 'direct_messages/new', $twparams );
								error_log ( 'SENDING-TWITTER:' . print_r ( $result, true . PHP_EOL ) );
							} catch ( \Exception $exc ) {
								error_log ( 'SENDING-tw:'.print_r($exc->getMessage(),true .PHP_EOL) );
							}
							
							break;
						default :
							break;
					}
				}
			}
		}
	}


}



// public function send() {
// 	try {
// 		error_log("Notification::Inside send()".PHP_EOL);
// 		// mobile notification.
// 		if (count ( $this->userIds ) > 0) {
// 			error_log("Notification::Inside send() user_ids>0".PHP_EOL);

// 			/*
// 			 * Fetch devices based on Device table and userIds
// 			*/
// 			//$qb = $this->dbAdapter->createQueryBuilder ();
// 			//$qb->select ( 'f' );
// 			//$qb->from ( 'Application\Entity\Device', 'f' );
// 			//$qb->andWhere ( 'f.user_id IN (:x)' )->setParameter ( 'x', $this->userIds );

// 			//$users = $qb->getQuery ()->getArrayResult ();
// 			//error_log("Notification::Inside send() device query-->".$qb->getQuery ()->getSql().PHP_EOL);
// 			/*
// 			 * Send out push notifications if user array exists
// 			 */
// 			//if (count ( $users ) > 0) {
// 			//	foreach ( $users as $user ) {
// 			foreach ($this->userIds as $user) {
// 				error_log ( 'user-id- ' . $user ['user_id'] . '  devicetype-' . $user ['device_type'] . PHP_EOL );
// 				if ($user ['device_type'] == \Application\Entity\Device::ANROID) {
// 					error_log("Notification::Inside send() this->gcm->addDevice---> ".$user ['device_token'].PHP_EOL);
// 					$this->gcm->addDevice ( $user ['device_token'] );
// 				} else if ($user ['device_type'] == \Application\Entity\Device::APPLE) {
// 					error_log("Notification::Inside send() this->apns->addDevice---> ".$user ['device_token'].PHP_EOL);
// 					$this->apns->addDevice ( $user ['device_token'] );
// 				}
// 			}
// 			$x = '';
// 			if ($this->gcm->getDeviceCount () > 0) {

// 				$x = $this->gcm->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
// 				error_log ( 'SENDING-ANROID' . print_r ( $x, true ) . PHP_EOL );
// 			}
// 			$x = '';
// 			if ($this->apns->getDeviceCount () > 0) {

// 				$x = $this->apns->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
// 				error_log ( 'SENDING-Apple' . print_r ( $x, true ) . PHP_EOL );
// 			}
// 			}
// 			// to do memreas user fb twitter
// 			// $this->webNotification ();
// 		}
// 		// non memras users fb twitter
// 		$this->webNotification ();
// 	} catch ( \Exception $exc ) {
// 		error_log ( 'exp-notifcation class' . $exc->getMessage () );
// 	}
// }

?>
