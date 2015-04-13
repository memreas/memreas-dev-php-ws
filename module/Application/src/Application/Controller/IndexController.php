<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Session\SessionManager;
use Zend\Session\Container;
use Zend\Session\Config\SessionConfig;
use Application\Model;
use Application\Form;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use GuzzleHttp\Client;
use Application\Model\MemreasConstants;
use Application\memreas\Login;
use Application\memreas\Registration;
use Application\memreas\AddComment;
use Application\memreas\AddMediaEvent;
use Application\memreas\ChkUname;
use Application\memreas\LikeMedia;
use Application\memreas\MediaInappropriate;
use Application\memreas\CountListallmedia;
use Application\memreas\ListGroup;
use Application\memreas\DeletePhoto;
use Application\memreas\ListPhotos;
use Application\memreas\ForgotPassword;
use Application\memreas\Download;
use Application\memreas\ViewAllfriends;
use Application\memreas\CreateGroup;
use Application\memreas\ListAllmedia;
use Application\memreas\CountViewevent;
use Application\memreas\EditEvent;
use Application\memreas\AddEvent;
use Application\memreas\ViewEvents;
use Application\memreas\AWSManagerSender;
use Application\memreas\AWSMemreasRedisCache;
use Application\memreas\AWSMemreasRedisSessionHandler;
use Application\memreas\AddFriendtoevent;
use Application\memreas\ViewMediadetails;
use Application\memreas\snsProcessMediaPublish;
use Application\memreas\Memreastvm;
use Application\memreas\MemreasSignedURL;
use Application\memreas\UploadMedia;
use Application\memreas\UploadAdvertisement;
use Application\memreas\AddNotification;
use Application\memreas\ChangePassword;
use Application\memreas\ListNotification;
use Application\memreas\UpdateNotification;
use Application\memreas\FindTag;
use Application\memreas\LogOut;
use Application\memreas\ClearAllNotification;
use Application\memreas\GetSession;
use Application\memreas\RegisterDevice;
use Application\memreas\RegisterCanonicalDevice;
use Application\memreas\ListComments;
use Application\memreas\Memreas;
use Application\memreas\MemreasTables;
use Application\memreas\GetEventLocation;
use Application\memreas\GetEventCount;
use Application\memreas\GetUserDetails;
use Application\memreas\SaveUserDetails;
use Application\memreas\GetUserGroups;
use Application\memreas\GetGroupFriends;
use Application\memreas\AddFriendToGroup;
use Application\memreas\RemoveFriendGroup;
use Application\memreas\GetEventPeople;
use Application\memreas\AddExistMediaToEvent;
use Application\memreas\GetMediaLike;
use Application\memreas\CheckExistMedia;
use Application\memreas\ListMemreasFriends;
use Application\memreas\GetSocialCredentials;
use Application\memreas\UpdateMedia;
use Application\memreas\FeedBack;
use Application\memreas\GetEventDetails;
use Application\memreas\RemoveEventMedia;
use Application\memreas\RemoveEventFriend;
use Application\memreas\RemoveFriends;
use Application\memreas\GetFriends;
use Application\memreas\Utility;
use Application\memreas\RemoveGroup;
use Application\memreas\CheckEvent;
use Application\memreas\VerifyEmailAddress;
use Application\memreas\UpdatePassword;
use Application\Storage\DBStorage;
use Zend\Db\TableGateway\TableGateway;
use Application\Model\DbTableGatewayOptions;
use Application\memreas\GetDiskUsage;
use Application\memreas\StripeWS\ListPayees;

// Stripe Web Services
use Application\memreas\StripeWS\GetPlans;
use Application\memreas\StripeWS\GetPlansStatic;
use Application\memreas\StripeWS\GetOrderHistory;
use Application\memreas\StripeWS\GetOrder;
use Application\memreas\StripeWS\GetAccountDetail;
use Application\memreas\StripeWS\Refund;
use Application\memreas\StripeWS\MakePayout;

class IndexController extends AbstractActionController {
	protected $xml_in;
	protected $url;
	protected $user_id;
	protected $storage;
	protected $authservice;
	protected $userTable;
	protected $eventTable;
	protected $mediaTable;
	protected $eventmediaTable;
	protected $friendmediaTable;
	protected $redis;
	protected $aws;
	protected $sid;
	protected $ipAddress;
	public function xml2array($xmlstring) {
		$xml = simplexml_load_string ( $xmlstring );
		$json = json_encode ( $xml );
		$arr = json_decode ( $json, TRUE );
		
		return $arr;
	}
	public function array2xml($array, $xml = false) {
		if ($xml === false) {
			$xml = new \SimpleXMLElement ( '<?xml version=\'1.0\' encoding=\'utf-8\'?><' . key ( $array ) . '/>' );
			$array = $array [key ( $array )];
		}
		foreach ( $array as $key => $value ) {
			if (is_array ( $value )) {
				array2xml ( $value, $xml->addChild ( $key ) );
			} else {
				$xml->addChild ( $key, $value );
			}
		}
		return $xml->asXML ();
	}
	public function fetchXML($action, $xml) {
		$guzzle = new Client ();
		
		$request = $guzzle->post ( $this->url, null, array (
				'action' => $action,
				'cache_me' => true,
				'xml' => $xml 
		) );
		$response = $request->send ();
		return $data = $response->getBody ( true );
	}
	public function indexAction() {
		error_log ( "inside indexAction..." . PHP_EOL );
		// Checking headers for cookie info
		// $headers = apache_request_headers ();
		// foreach ( $headers as $header => $value ) {
		// error_log ( "WS header: $header :: value: $value" . PHP_EOL );
		// }
		// End Checking headers for cookie info
		$path = "application/index/ws_tester.phtml";
		$output = '';
		
		$callback = isset ( $_REQUEST ['callback'] ) ? $_REQUEST ['callback'] : '';
		// error_log("inside indexAction callback ...".$callback.PHP_EOL);
		
		if (isset ( $_REQUEST ['json'] )) {
			// Fetch parms
			$json = $_REQUEST ['json'];
			$jsonArr = json_decode ( $json, true );
			$actionname = $jsonArr ['action'];
			$type = $jsonArr ['type'];
			$message_data = $jsonArr ['json'];
			$_POST ['xml'] = $message_data ['xml'];
		} else {
			$actionname = isset ( $_REQUEST ['action'] ) ? $_REQUEST ['action'] : '';
			$message_data ['xml'] = '';
		}
		
		error_log ( "Inside indexAction---> actionname ---> $actionname " . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
		$actionname = $this->fetchSession ( $actionname, $this->requiresSecureAction ( $actionname ) );
		error_log ( "Inside indexAction---> actionname after security ---> $actionname " . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
		
		if (isset ( $actionname ) && ! empty ( $actionname )) {
			$cache_me = false;
			$cache_id = null;
			$invalidate = false;
			$invalidate_me = false;
			
			// Capture the echo from the includes in case we need to convert back to json
			ob_start ();
			
			// if (isset($_POST ['xml']) && !empty($_POST ['xml'])) { error_log("Input data as xml ----> ".$_POST ['xml'].PHP_EOL); }
			
			$memreas_tables = new MemreasTables ( $this->getServiceLocator () );
			
			if ($actionname == 'notlogin') {
				$result = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$result .= "<xml><error>Please Login </error></xml>";
				
				/*
				 * Cache approach - N/a
				 */
			} else if ($actionname == "login") {
				error_log ( 'login action this->ipAddress' . $this->fetchUserIPAddress () . PHP_EOL );
				$login = new Login ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $login->exec ( $this->fetchUserIPAddress () );
				
				/*
				 * If web store fesid lookup
				 */
				if ($login->isWeb) {
					error_log ( 'isWeb is true calling setFELookup...' . PHP_EOL );
					
					$login->setFELookup ( new AWSMemreasRedisCache ( $this->getServiceLocator () ) );
				}
				$this->ipAddress = $_SESSION ['ipAddress'];
				
				error_log ( 'login session variables user_id-->' . $_SESSION ['user_id'] . PHP_EOL );
				error_log ( 'login session variables username-->' . $_SESSION ['username'] . PHP_EOL );
				error_log ( 'login session variables sid-->' . $_SESSION ['sid'] . PHP_EOL );
				error_log ( 'login session variables email_address-->' . $_SESSION ['email_address'] . PHP_EOL );
				error_log ( 'login session variables device_id-->' . $_SESSION ['device_id'] . PHP_EOL );
				error_log ( 'login session variables device_type-->' . $_SESSION ['device_type'] . PHP_EOL );
				error_log ( 'login session variables fesid-->' . $_SESSION ['fesid'] . PHP_EOL );
				error_log ( 'login session variables ipAddress-->' . $_SESSION ['ipAddress'] . PHP_EOL );
				
				/*
				 * Cache approach - warm @person if not set here
				 */
				if ((MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
					if ($this->redis->hasSet ( '@person' )) {
						
						// $time_start = microtime(true);
						// error_log("cache warming @person ended... @ ".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
						$matches = $this->redis->findSet ( '@person', "ch-1tuser-" );
						// error_log("cache warming @person ended... @ ".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
						// error_log("matches json -----> ".json_encode($matches).PHP_EOL);
						
						// $time_end = microtime(true);
						// $time = $time_end - $time_start;
						// error_log("findset ended... @ ".$time_end." duration->".$time.PHP_EOL);
						
						/*
						 * TODO: Add the user only if s/he doesn't exist in the hash (i.e. 1st login will force cache to warm)
						 */
						$mc [$username] = array (
								'user_id' => $user_id,
								'profile_photo' => '' 
						);
						// error_log ("set array" . PHP_EOL);
						$this->redis->addSet ( "@person_meta_hash", $username, json_encode ( $mc [$username] ) );
						// error_log ("addSet meta hash" . PHP_EOL);
						$this->redis->addSet ( "@person_uid_hash", $username, $user_id );
						// error_log ("addSet uid hash" . PHP_EOL);
						$this->redis->addSet ( "@person", $username );
						// error_log ("addSet person" . PHP_EOL);
						// error_log ("$username added - @person_hash set now holds --> ". $this->redis->hasSet('@person') . " users@ " . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL);
					}
				}
			} else if ($actionname == "registration") {
				$registration = new Registration ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $registration->exec ();
				
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->registration->username );
			} else if ($actionname == "addcomments") {
				$addcomment = new AddComment ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addcomment->exec ();
				
				/*
				 * Cache approach - Write Operation - Invalidate listcomment here
				 */
				// $data = simplexml_load_string($_POST ['xml']);
				// if (isset($data->addcomment->event_id)) {
				// //Invalidate existing cache
				// $this->redis->invalidateCache("listcomments_" . $data->addcomment->event_id);
				// }
			} else if ($actionname == "verifyemailaddress") {
				$verifyemailaddress = new VerifyEmailAddress ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $verifyemailaddress->exec ();
				if ($result) {
					if (! empty ( $_GET ['perf'] )) {
						return true;
					} else {
						$redirect = MemreasConstants::WEB_URL . "index?email_verified=1";
						return $this->redirect ()->toUrl ( $redirect );
					}
				} else {
					$redirect = MemreasConstants::WEB_URL . "index?email_verified=0";
					return $this->redirect ()->toUrl ( $redirect );
				}
				/*
				 * Cache approach - N/a
				 */
			} else if ($actionname == "checkusername" || $actionname == "chkuname") {
				$chkuname = new ChkUname ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $chkuname->exec ();
				
				/*
				 * Cache approach - read operation - pass for now
				 */
			} else if ($actionname == "addmediaevent") {
				// error_log("inside indexAction addmediaevent ...".$callback.PHP_EOL);
				
				$addmediaevent = new AddMediaEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addmediaevent->exec ();
				
				/*
				 * Cache approach - Write Operation - Invalidate existing cache here
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$this->redis->invalidateMedia ( $data->addmediaevent->user_id, $data->addmediaevent->event_id, $data->addmediaevent->media_id );
			} else if ($actionname == "likemedia") {
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->likemedia->user_id );
				/*
				 * Cache approach - write operation - pass for now
				 */
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				if (! $result || empty ( $result )) {
					$likemedia = new LikeMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $likemedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "mediainappropriate") {
				$data = simplexml_load_string ( $_POST ['xml'] );
				$mediainappropriate = new MediaInappropriate ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $mediainappropriate->exec ();
				/*
				 * Cache approach - Write Operation - Invalidate existing cache here
				 */
				$this->redis->invalidateMedia ( $data->mediainappropriate->user_id, $data->mediainappropriate->event_id );
			} else if ($actionname == "countlistallmedia") {
				
				/*
				 * Cache approach - read operation - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->countlistallmedia->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$countlistallmedia = new CountListallmedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $countlistallmedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "listgroup") {
				/*
				 * Cache approach - read operation - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listgroup->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result) {
					$listgroup = new ListGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $listgroup->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "deletephoto") {
				$deletephoto = new DeletePhoto ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $deletephoto->exec ();
				
				/*
				 * TODO: Cache approach - Write Operation - Invalidate existing cache here -- need user_id or event_id --- this is based on media_id
				 */
				
				// $session = new Container("user");
				// $this->redis->invalidateCache("listallmedia_" . $session->user_id);
				// $this->redis->invalidateCache("viewevents_" . $session->user_id);
			} else if ($actionname == "listphotos") {
				/*
				 * Cache approach - read operation - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listphotos->userid );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result) {
					$listphotos = new ListPhotos ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $listphotos->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "forgotpassword") {
				$forgotpassword = new ForgotPassword ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $forgotpassword->exec ();
				/*
				 * Cache approach - N/a
				 */
			} else if ($actionname == "download") {
				$download = new Download ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $download->exec ();
				/*
				 * Cache approach - N/a
				 */
			} else if ($actionname == "viewallfriends") {
				/*
				 * Cache approach - read operation - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->viewallfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$viewallfriends = new ViewAllfriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $viewallfriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "creategroup") {
				$creategroup = new CreateGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $creategroup->exec ();
				
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->creategroup->user_id );
				
				/*
				 * Cache approach - write operation - invalidate listgroup
				 */
				$this->redis->invalidateGroups ( $uid );
			} else if ($actionname == "listallmedia") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache... if event_id then return that cache else user_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->listallmedia->event_id )) {
					$cache_id = $data->listallmedia->event_id;
				} else if (! empty ( $data->listallmedia->user_id )) {
					$cache_id = $data->listallmedia->user_id;
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				if (! $result || empty ( $result )) {
					$listallmedia = new ListAllmedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $listallmedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "countviewevent") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->countviewevent->is_public_event ) && $data->countviewevent->is_public_event) {
					$cache_id = "public";
				} else {
					$cache_id = trim ( $data->countviewevent->user_id );
				}
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$countviewevent = new CountViewevent ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $countviewevent->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "editevent") {
				$editevent = new EditEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $editevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$event_id = trim ( $data->editevent->event_id );
				
				/*
				 * Cache approach - write operation - invalidate events
				 */
				$this->redis->invalidateEvents ( $event_id );
			} else if ($actionname == "addevent") {
				$addevent = new AddEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * Cache approach - write operation - hold for now
				 */
				$this->redis->invalidateEvents ( $data->addevent->user_id );
			} else if ($actionname == "viewevents") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->viewevent->is_public_event ) && $data->viewevent->is_public_event) {
					$cache_id = "public";
				} else if (! empty ( $data->viewevent->is_friend_event ) && $data->viewevent->is_friend_event) {
					$cache_id = "is_friend_event_" . trim ( $data->viewevent->user_id );
				} else if (! empty ( $data->viewevent->is_my_event ) && $data->viewevent->is_my_event) {
					$cache_id = "is_my_event_" . trim ( $data->viewevent->user_id );
				}
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$viewevents = new ViewEvents ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $viewevents->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "addfriendtoevent") {
				$addfriendtoevent = new AddFriendtoevent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addfriendtoevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->addfriendtoevent->user_id );
				
				/*
				 * Cache approach - write operation - hold for now
				 */
				$this->redis->invalidateEvents ( $uid );
				$this->redis->invalidateGroups ( $uid );
			} else if ($actionname == "viewmediadetails") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache... if event_id then return then event_id_media_id else cache media_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->viewmediadetails->event_id ) && ! empty ( $data->viewmediadetails->media_id )) {
					$cache_id = trim ( $data->viewmediadetails->event_id ) . "_" . trim ( $data->viewmediadetails->media_id );
				} else if (! empty ( $data->viewmediadetails->media_id )) {
					$cache_id = trim ( $data->viewmediadetails->media_id );
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$viewmediadetails = new ViewMediadetails ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $viewmediadetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "snsProcessMediaPublish") {
				$snsProcessMediaPublish = new snsProcessMediaPublish ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $snsProcessMediaPublish->exec ();
			} else if ($actionname == "memreas_tvm") {
				$memreastvm = new Memreastvm ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $memreastvm->exec ();
			} else if ($actionname == "uploadmedia") {
				/*
				 * TODO: See if this is used - if not remove
				 */
				$uploadmedia = new UploadMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $uploadmedia->exec ();
			} else if ($actionname == "uploadadvertisement") {
				$uploadadvertisement = new UploadAdvertisement ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $uploadadvertisement->exec ();
			} else if ($actionname == "addNotification") {
				$addNotification = new AddNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addNotification->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->addNotification->user_id );
				
				/*
				 * Cache approach - write operation - invalidate listnotification
				 */
				$this->redis->invalidateNotifications ( $uid );
			} else if ($actionname == "changepassword") {
				$changepassword = new ChangePassword ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $changepassword->exec ();
			} else if ($actionname == "listnotification") {
				
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = ! empty ( $_POST ['xml'] ) ? simplexml_load_string ( $_POST ['xml'] ) : null;
				$user_id = $data->listnotification->user_id;
				$cache_id = ! empty ( $data ) ? trim ( $data->listnotification->user_id ) : null;
				try {
					$result = ! empty ( $cache_id ) ? $this->redis->getCache ( $actionname . '_' . $cache_id ) : false;
				} catch ( \Exception $e ) {
					$result = false;
				}
				
				if (! $result || empty ( $result )) {
					$listnotification = new ListNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $listnotification->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "updatenotification") {
				$updatenotification = new UpdateNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $updatenotification->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = $updatenotification->user_id;
				
				/*
				 * Cache approach - write operation - invalidate listnotification
				 */
				$this->redis->invalidateNotifications ( $uid );
			} else if ($actionname == "findtag") {
				
				/*
				 * fetch parameters
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$tag = (trim ( $data->findtag->tag ));
				$user_id = (trim ( $data->findtag->user_id ));
				$user_id = empty ( $user_id ) ? 0 : $user_id;
				$a = $tag [0];
				$search = substr ( $tag, 1 );
				
				/*
				 * set paging and limits
				 */
				$page = trim ( $data->findtag->page );
				if (empty ( $page )) {
					$page = 1;
				}
				
				$limit = trim ( $data->findtag->limit );
				if (empty ( $limit )) {
					$limit = 20;
				}
				
				$from = ($page - 1) * $limit;
				$rc = 0;
				
				$search_result = array ();
				switch ($a) {
					/*
					 * @person search
					 */
					case '@':

                    	/*
                    	 * TODO: Migrate to redis search - see example below
                    	 */
						$user_ids = array ();
						if ((MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
							error_log ( "redis @ fetch..." . PHP_EOL );
							// Redis - this codes fetches usernames by the search term then gets the hashes
							$usernames = $this->redis->findSet ( '@person', $search );
							$person_meta_hash = $this->redis->cache->hmget ( "@person_meta_hash", $usernames );
							$person_uid_hash = $this->redis->cache->hmget ( '@person_uid_hash', $usernames );
							if (in_array ( $user_id, $person_uid_hash )) {
								$username = $person_uid_hash ["$user_id"];
								// now remove current user
								unset ( $person_meta_hash [$username [$user_id]] );
								unset ( $person_uid_hash [$user_id] );
								$index = array_search ( $username, $usernames );
								unset ( $usernames [$index] );
							}
							$user_ids = array_keys ( $person_uid_hash );
							$search_result = array_values ( $person_meta_hash );
							$rc = count ( $search_result );
						} else {
							error_log ( "@ fetch get regindex..." . PHP_EOL );
							$registration = new registration ( $message_data, $memreas_tables, $this->getServiceLocator () );
							$registration->createUserCache ();
							$person_meta_hash = $registration->userIndex;
							// Remove current user
							// All entries in this hash match the search key
							foreach ( $person_meta_hash as $username => $usermeta ) {
								// $meta_arr = json_decode($usermeta,true);
								$meta_arr = $usermeta;
								$uid = $meta_arr ['user_id'];
								// Remove existing user
								error_log ( "username" . $username . " --- uid-->" . $uid . PHP_EOL );
								if ($uid == $user_id)
									continue;
									/*
								 * TODO: 6-NOV-2014 Paging isn't working correctly? Removing for now...
								 */
									// if ($rc >= $from && $rc < ($from + $limit)) {
								error_log ( "meta_arr ['username']--->" . $meta_arr ['username'] . " --- search-->" . $search . PHP_EOL );
								if (stripos ( $meta_arr ['username'], $search ) !== false) {
									$meta_arr ['username'] = '@' . $meta_arr ['username'];
									$search_result [] = $meta_arr;
									$user_ids [] = $uid;
									error_log ( "user_ids-->" . json_encode ( $user_ids ) . PHP_EOL );
								}
								// }
								$rc += 1;
							}
							// error_log("query user_ids------> " . json_encode($user_ids) . PHP_EOL);
							// error_log("query search_result count------> " . count($search_result) . PHP_EOL);
						}
						/*
						 * TODO: need to document filter rules
						 */
						$em = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' );
						
						// This query fetches user's friends
						$qb = $em->createQueryBuilder ();
						$qb->select ( 'f.friend_id,uf.user_approve' );
						$qb->from ( 'Application\Entity\Friend', 'f' );
						$qb->join ( 'Application\Entity\UserFriend', 'uf', 'WITH', 'uf.friend_id = f.friend_id' );
						$qb->where ( "f.network='memreas'" );
						$qb->andwhere ( "uf.user_approve = '1'" );
						$qb->andwhere ( "uf.user_id = '$user_id'" );
						$qb->andwhere ( 'uf.friend_id IN (:f)' );
						$qb->setParameter ( 'f', $user_ids );
						// error_log("qb->getQuery()->getSql()------> " . $qb->getDQL() . PHP_EOL);
						error_log ( "qb->getQuery()->getSql()------> " . $qb->getQuery ()->getSql () . PHP_EOL );
						
						$UserFriends = $qb->getQuery ()->getResult ();
						
						// this code checks if friend request already sent...
						$chkUserFriend = array ();
						foreach ( $UserFriends as $ufRow ) {
							$chkUserFriend [$ufRow ['friend_id']] = $ufRow ['user_approve'];
						}
						
						foreach ( $search_result as $k => &$srRow ) {
							if (isset ( $chkUserFriend [$user_ids [$k]] )) {
								$srRow ['friend_request_sent'] = $chkUserFriend [$user_ids [$k]];
								continue;
							}
						}
						
						// error_log("Past mc person_meta_hash.....".json_encode($person_meta_hash). PHP_EOL);
						// $result['totalPage'] =ceil($rc / $limit);
						$result ['totalPage'] = 1;
						$result ['count'] = $rc;
						$result ['search'] = $search_result;
						// hide pagination
						
						// echo '<pre>';print_r($result);
						
						echo json_encode ( $result );
						error_log ( "result------> " . json_encode ( $result ) . PHP_EOL );
						$result = '';
						
						break;
					
					/*
					 * !event search
					 */
					case '!' :

						/*
						 * Fetch Event Repository
						 */
						$mc = $this->redis->getCache ( '!event' );
						$eventRep = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
						if (! $mc || empty ( $mc )) {
							error_log ( "findtag empty redis -> createEventCache..." . PHP_EOL );
							
							$mc = $eventRep->createEventCache ();
							$this->redis->setCache ( "!event", $mc );
						}
						$search_result = array ();
						$event_ids = array ();
						foreach ( $mc as $er ) {
							if (stripos ( $er ['name'], $search ) === 0) {
								if ($rc >= $from && $rc < ($from + $limit)) {
									$er ['name'] = '!' . $er ['name'];
									$er ['created_on'] = Utility::formatDateDiff ( $er ['create_time'] );
									$event_creator = $eventRep->getUser ( $er ['user_id'], 'row' );
									$er ['event_creator_name'] = '@' . $event_creator ['username'];
									$er ['event_creator_pic'] = $event_creator ['profile_photo'];
									error_log ( "event_creator ['username']------> " . $event_creator ['username'] . PHP_EOL );
									error_log ( "event_creator ['profile_photo']------> " . $event_creator ['profile_photo'] . PHP_EOL );
									
									$search_result [] = $er;
									$event_ids [] = $er ['event_id'];
								}
								$rc += 1;
							}
						}
						// filter record !event should show public events and events you've been invited to
						$em = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' );
						// $user_id = empty($_POST['user_id'])?0:$_POST['user_id'];
						
						// Fetch friends
						$qb = $em->createQueryBuilder ();
						$qb->select ( 'ef' );
						$qb->from ( 'Application\Entity\EventFriend', 'ef' );
						$qb->andWhere ( 'ef.event_id IN (:e)' )->andWhere ( 'ef.friend_id =:f' );
						// $qb->andWhere('ef.user_approve = 1');
						$qb->setParameter ( 'f', $user_id );
						$qb->setParameter ( 'e', $event_ids );
						$EventFriends = $qb->getQuery ()->getArrayResult ();
						
						// Check if event request sent
						$chkEventFriend = array ();
						foreach ( $EventFriends as $efRow ) {
							$chkEventFriend [$efRow ['event_id']] = $efRow ['user_approve'];
						}
						foreach ( $search_result as $k => &$srRow ) {
							if (isset ( $chkEventFriend [$event_ids [$k]] )) {
								$srRow ['event_request_sent'] = $chkEventFriend [$event_ids [$k]];
							}
						}
						
						$comments = $eventRep->createDiscoverCache ( $tag );
						
						$result ['count'] = $rc;
						$result ['search'] = $search_result;
						$result ['comments'] = empty ( $comments ) ? array () : $comments;
						$result ['page'] = $page;
						$result ['totalPage'] = ceil ( $rc / $limit );
						
						// $result = preg_grep("/$search/", $mc);
						// echo '<pre>';print_r($result);
						
						echo json_encode ( $result );
						error_log ( "result------> " . json_encode ( $result ) . PHP_EOL );
						$result = '';
						break;
					
					/*
					 * #hashtag comment search
					 */
					case '#':
                    	/*
                    	 * TODO: Migrate to redis search - see example below
                    	 */
						$search_result = array ();
						if ((MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
							error_log ( "redis hashtag fetch TODO..." . PHP_EOL );
							// Redis - ...
							error_log ( "Inside findTag # for tag $search" . PHP_EOL );
							$tags_public = $this->redis->findSet ( '#hashtag', $search );
							$tags_uid = $this->redis->findSet ( '#hashtag_' . $user_id, $search );
							$tags_unique = array_unique ( array_merge ( $tags_public, $tags_uid ) );
							error_log ( "Inside findTag # tags_unique--->" . json_encode ( $tags_unique ) . PHP_EOL );
							$hashtag_public_eid_hash = $this->redis->cache->hmget ( "#hashtag_public_eid_hash", $tags_unique );
							error_log ( "Inside findTag # hashtag_public_eid_hash--->" . json_encode ( $hashtag_public_eid_hash ) . PHP_EOL );
							$hashtag_friends_hash = $this->redis->cache->hmget ( '#hashtag_friends_hash_' . $user_id, $tags_unique );
							error_log ( "Inside findTag # hashtag_friends_hash--->" . json_encode ( $hashtag_friends_hash ) . PHP_EOL );
							
							$eventRep = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
							$mc = $eventRep->createDiscoverCache ( $search );
							// $usernames = $this->redis->findSet( '@person', $search );
							// $person_meta_hash = $this->redis->cache->hmget("@person_meta_hash", $usernames);
							// $person_uid_hash = $this->redis->cache->hmget( '@person_uid_hash', $usernames );
							// $user_ids = $usernames;
						} else {
							$eventRep = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
							error_log ( "createDiscoverCache------>$tag" . PHP_EOL );
							$hashtag_cache = $eventRep->createDiscoverCache ( $tag );
						}
						
						foreach ( $hashtag_cache as $tag => $cache_entry ) {
							error_log ( "tag------>$tag" . PHP_EOL );
							error_log ( "cache_entry------>" . json_encode ( $cache_entry ) . PHP_EOL );
							if (stripos ( $cache_entry ['tag_name'], $search ) !== false) {
								// if ($rc >= $from && $rc < ($from + $limit)) {
								$cache_entry ['updated_on'] = Utility::formatDateDiff ( $cache_entry ['update_time'] );
								$cache_entry ['update_time'] = Utility::toDateTime ( $cache_entry ['update_time'] );
								$search_result [$tag] = $cache_entry;
								// }
								$rc += 1;
							}
						}
						
						$result ['count'] = $rc;
						$result ['search'] = $search_result;
						$result ['page'] = $page;
						$result ['totalPage'] = ceil ( $rc / $limit );
						
						// $result = preg_grep("/$search/", $mc);
						// echo '<pre>';print_r($result);
						
						echo json_encode ( $result );
						$result = '';
						break;
					default :
						// $findtag = new FindTag($message_data, $memreas_tables, $this->getServiceLocator());
						// $result = $findtag->exec();
						// $result = preg_grep("/$search/", $mc);
						$result ['count'] = 0;
						$result ['search'] = array ();
						$result ['totalPage'] = 0;
						
						echo json_encode ( $result );
						$result = '';
						break;
				}
			} else if ($actionname == "findevent") {
				/*
				 * TODO: This is covered by findtag??
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$tag = (trim ( $data->findevent->tag ));
				$search = substr ( $tag, 1 );
				$eventRep = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
				$mc = $this->redis->getCache ( '!event' );
				if (! $mc || empty ( $mc )) {
					$mc = $eventRep->createEventCache ();
					$this->redis->setCache ( "!event", $mc );
				}
				
				$search_result = array ();
				$page = trim ( $data->findevent->page );
				if (empty ( $page )) {
					$page = 1;
				}
				
				$limit = trim ( $data->findevent->limit );
				if (empty ( $limit )) {
					$limit = 20;
				}
				
				$from = ($page - 1) * $limit;
				$rc = 0;
				foreach ( $mc as $eid => $er ) {
					if (stripos ( $er ['name'], $search ) === 0) {
						if ($rc >= $from && $rc < ($from + $limit)) {
							$er ['name'] = '!' . $er ['name'];
							$er ['comment_count'] = $eventRep->getCommentCount ( $eid );
							$er ['like_count'] = $eventRep->getLikeCount ( $eid );
							$er ['friends'] = $eventRep->getEventFriends ( $eid );
							$search_result [] = $er;
						}
						
						$rc += 1;
					}
				}
				$result ['count'] = $rc;
				$result ['page'] = $page;
				$result ['totalPage'] = ceil ( $rc / $limit );
				$result ['search'] = $search_result;
				// $result = preg_grep("/$search/", $mc);
				// echo '<pre>';print_r($result);
				echo json_encode ( $result );
				$result = '';
			} else if ($actionname == "getDiscover") {
				/*
				 * TODO: Is this covered by findTag?
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$tag = (trim ( $data->getDiscover->tag ));
				$search = $tag;
				$eventRep = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
				$mc = $this->redis->getCache ( '#tag' );
				if (! $mc || empty ( $mc )) {
					$mc = $eventRep->createDiscoverCache ( $tag );
					$this->redis->setCache ( "#tag", $mc );
				}
				
				$search_result = array ();
				$page = trim ( $data->getDiscover->page );
				if (empty ( $page )) {
					$page = 1;
				}
				
				$limit = trim ( $data->getDiscover->limit );
				if (empty ( $limit )) {
					$limit = 20;
				}
				
				$from = ($page - 1) * $limit;
				$rc = 0;
				foreach ( $mc as $eid => $er ) {
					
					if (stripos ( $er ['name'], $search ) === 0) {
						
						if ($rc >= $from && $rc < ($from + $limit)) {
							$er ['name'] = $er ['name'];
							// $er['comment_count'] = $eventRep->getLikeCount($eid);
							// $er['like_count'] = $eventRep->getLikeCount($eid);
							// $er['friends'] = $eventRep->getEventFriends($eid);
							$search_result [] = $er;
						}
						
						$rc += 1;
					}
				}
				$result ['count'] = $rc;
				$result ['page'] = $page;
				$result ['totalPage'] = ceil ( $rc / $limit );
				$result ['search'] = $search_result;
				// $result = preg_grep("/$search/", $mc);
				// echo '<pre>';print_r($result);
				echo json_encode ( $result );
				$result = '';
			} else if ($actionname == "signedurl") {
				/*
				 * Cache Approach: N/a
				 */
				$signedurl = new MemreasSignedURL ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $signedurl->exec ();
			} else if ($actionname == "showlog") {
				/*
				 * Cache Approach: N/a
				 */
				echo '<pre>' . file_get_contents ( getcwd () . '/php_errors.log' );
				exit ();
			} else if ($actionname == "clearlog") {
				/*
				 * Cache Approach: N/a
				 */
				unlink ( getcwd () . '/php_errors.log' );
				error_log ( "Log has been cleared!" );
				echo '<pre>' . file_get_contents ( getcwd () . '/php_errors.log' );
				exit ();
			} else if ($actionname == "logout") {
				/*
				 * Cache Approach: N/a
				 */
				error_log ( 'IndexController -> logout...' . PHP_EOL );
				$logout = new LogOut ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
			} else if ($actionname == "clearallnotification") {
				/*
				 * TODO: Cache Approach: write operation do later
				 */
				$logout = new ClearAllNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->clearallnotification->user_id );
				/*
				 * Cache approach - write operation - invalidate listnotification
				 */
				$this->redis->invalidateNotifications ( $uid );
			} else if ($actionname == "getsession") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listnotification->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$getsession = new GetSession ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $getsession->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "registerdevice") {
				$register_device = new RegisterDevice ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $register_device->exec ();
			} else if ($actionname == "registercanonicaldevice") {
				$register_canonical_device = new RegisterCanonicalDevice ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $register_canonical_device->exec ();
			} else if ($actionname == "listcomments") {
				
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache... if event_id then return then event_id_media_id else cache media_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->listcomments->event_id ) && ! empty ( $data->listcomments->media_id )) {
					$cache_id = trim ( $data->listcomments->event_id ) . "_" . trim ( $data->listcomments->media_id );
				} else if (! empty ( $data->listcomments->media_id )) {
					$cache_id = trim ( $data->listcomments->media_id );
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$listcomments = new ListComments ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $listcomments->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "verifyemail") {
				/*
				 * Cache Approach: N/a
				 */
				$aws_manager = new AWSManagerSender ( $this->service_locator );
				$client = $aws_manager->ses ();
				$client->verifyEmailAddress ( array (
						'EmailAddress' => $_GET ['email'] 
				) );
				// echo 'Please Cheack email validate you email to receive emails';
			} else if ($actionname == "geteventlocation") {
				
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventlocation->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventLocation = new GetEventLocation ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetEventLocation->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "geteventcount") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventcount->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventLocation = new GetEventCount ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetEventLocation->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getuserdetails") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getuserdetails->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetUserDetails = new GetUserDetails ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetUserDetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "saveuserdetails") {
				/*
				 * TODO: Invalidation needed
				 */
				$SaveUserDetails = new SaveUserDetails ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $SaveUserDetails->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * Cache approach - write operation - invalidate listnotification
				 */
				$this->redis->invalidateUser ( $data->saveuserdetails->user_id );
			} else if ($actionname == "getusergroups") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getusergroups->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetUserGroups = new GetUserGroups ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetUserGroups->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getgroupfriends") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache... if group_id then return then network_group_id else network
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$group_id = trim ( $data->getgroupfriends->group_id );
				$network = trim ( $data->getgroupfriends->network );
				$cache_id = $group_id;
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetGroupFriends = new GetGroupFriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetGroupFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "addfriendtogroup") {
				/*
				 * TODO: Invalidation needed
				 */
				$AddFriendToGroup = new AddFriendToGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $AddFriendToGroup->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateGroups ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "removefriendgroup") {
				$RemoveFriendGroup = new RemoveFriendGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $RemoveFriendGroup->exec ();
				
				/*
				 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateGroups ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "geteventpeople") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventpeople->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventPeople = new GetEventPeople ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetEventPeople->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "addexistmediatoevent") {
				$AddExistMediaToEvent = new AddExistMediaToEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $AddExistMediaToEvent->exec ();
				
				/*
				 * Cache approach - write operation - need to invalidate invalidateEvents
				 */
				$session = new Container ( "user" );
				$data = simplexml_load_string ( $_POST ['xml'] );
				$event_id = $data->addexistmediatoevent->event_id;
				$this->redis->invalidateMedia ( $session->offsetGet ( 'user_id' ), $event_id );
			} else if ($actionname == "getmedialike") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getmedialike->media_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetMediaLike = new GetMediaLike ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetMediaLike->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "checkexistmedia") {
				/*
				 * TODO: Query inside needs caching...
				 */
				$CheckExistMedia = new CheckExistMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $CheckExistMedia->exec ();
			} else if ($actionname == "listmemreasfriends") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listmemreasfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$ListMemreasFriends = new ListMemreasFriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $ListMemreasFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getsocialcredentials") {
				/*
				 * TODO: Cache Approach: Not necessary - no sql query
				 */
				$GetSocialCredentials = new GetSocialCredentials ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetSocialCredentials->exec ();
			} else if ($actionname == "updatemedia") {
				/*
				 * TODO: Invalidation needed.
				 */
				$UpdateMedia = new UpdateMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $UpdateMedia->exec ();
				
				/*
				 * TODO: Cache approach - write operation - need to invalidate invalidateMedia
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateMedia ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "feedback") {
				/*
				 * Cache Approach - N/a
				 */
				$FeedBack = new FeedBack ( $this->getServiceLocator () );
				$result = $FeedBack->exec ();
			} else if ($actionname == "geteventdetails") {
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventdetails->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventDetails = new GetEventDetails ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetEventDetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "removeeventmedia") {
				$RemoveEventMedia = new RemoveEventMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $RemoveEventMedia->exec ();
				
				/*
				 * Cache approach - write operation - invalidateMedia
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateMedia ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "removeeventfriend") {
				/*
				 * TODO: Invalidation needed
				 */
				$RemoveEventFriend = new RemoveEventFriend ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $RemoveEventFriend->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * Cache approach - write operation - invalidateMedia
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateEventFriends ( $data->removeeventfriend->event_id, $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "removefriends") {
				
				$RemoveFriends = new RemoveFriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $RemoveFriends->exec ();
				
				/*
				 * Cache approach - write operation - invalidateFriends
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateFriends ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "getfriends") {
				
				/*
				 * Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetFriends = new GetFriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
					$result = $GetFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getplans") {
				/*
				 * Cache Approach: N/a for now
				 */
				$GetPlans = new GetPlans ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetPlans->exec ();
			} else if ($actionname == "getplansstatic") {
				/*
				 * Cache Approach: N/a for now
				 */
				$GetPlansStatic = new GetPlansStatic ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetPlansStatic->exec ();
			} else if ($actionname == "getorderhistory") {
				/*
				 * Cache Approach: N/a for now
				 */
				$GetOrderHistory = new GetOrderHistory ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetOrderHistory->exec ();
			} else if ($actionname == "getorder") {
				/*
				 * Cache Approach: N/a for now
				 */
				$GetOrder = new GetOrder ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetOrder->exec ();
			} else if ($actionname == "removegroup") {
				$RemoveGroup = new RemoveGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $RemoveGroup->exec ();
				
				/*
				 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
				 */
				$session = new Container ( "user" );
				$this->redis->invalidateGroups ( $session->offsetGet ( 'user_id' ) );
			} else if ($actionname == "checkevent") {
				/*
				 * TODO: Query inside needs to cached
				 */
				$CheckEvent = new CheckEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $CheckEvent->exec ();
			} else if ($actionname == "updatepassword") {
				/*
				 * Cache Approach: N/a
				 */
				$UpdatePassword = new UpdatePassword ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $UpdatePassword->exec ();
			} else if ($actionname == "getaccountdetail") {
				/*
				 * Cache Approach: N/a for now
				 */
				$GetAccountDetail = new GetAccountDetail ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetAccountDetail->exec ();
			} else if ($actionname == "getdiskusage") {
				/*
				 * Cache Approach: N/a for now
				 */
				$getdiskusage = new GetDiskUsage ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $getdiskusage->exec ();
			} else if ($actionname == "refund") {
				/*
				 * Cache Approach: N/a
				 */
				$Refund = new Refund ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $Refund->exec ();
			} else if ($actionname == "listpayees") {
				/*
				 * Cache Approach: N/a
				 */
				$ListPayees = new ListPayees ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $ListPayees->exec ();
			} else if ($actionname == "makepayout") {
				$MakePayout = new MakePayout ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $MakePayout->exec ();
			}
			
			/*
			 * Successfully retrieved from cache so echo
			 */
			if ($cache_me == false && ! empty ( $result )) {
				echo $result;
			}
			$output = ob_get_clean ();
			
			/*
			 * TODO - Cache here due to ob_get_clean
			 */
			if ($cache_me && (MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
				$this->redis->setCache ( $actionname . '_' . $cache_id, $output );
			}
			
			/*
			 * TODO - Invalidate cache in if statements (id is all that is needed)
			 */
			
			// if ($invalidate_me && (MemreasConstants::REDIS_SERVER_USE) && (!MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
			// error_log("Invalidate Cache_id ----> ".$invalidate_action . '_' . $uid.PHP_EOL);
			// $this->redis->invalidateCache($invalidate_action . '_' . $cache_id);
			// }
		}
		
		if (! empty ( $callback )) {
			$message_data ['data'] = $output;
			
			$json_arr = array (
					"data" => $message_data ['data'] 
			);
			$json = json_encode ( $json_arr );
			
			header ( "Content-type: plain/text" );
			// header('Content-Type: application/json');
			// callback json
			echo $callback . "(" . $json . ")";
		} else if (isset ( $_GET ['view'] ) && empty ( $actionname )) {
			$view = new ViewModel ();
			$view->setTemplate ( $path ); // path to phtml file under view folder
			return $view;
		} else {
			// json output
			echo $output;
			// error_log("output ----> $output".PHP_EOL);
		}
		
		/*
		 * Cache Warming section...
		 */
		if (($actionname != 'listnotification') && (MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
			error_log ( "Inside Redis warmer @..." . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
			
			// Return the status code here so this process continues and the user receives response
			try {
				http_response_code ( 200 );
				header ( 'Connection: close' );
				header ( 'Content-Length: ' . ob_get_length () );
				ob_end_flush (); // Strange behaviour, will not work
				flush (); // Unless both are called !
			} catch ( Exception $e ) {
				// Do nothing...
			}
			
			// Debugging
			$result = $this->redis->cache->executeRaw ( array (
					'DEL',
					'@person' 
			) );
			$result = $this->redis->cache->executeRaw ( array (
					'SET',
					'warming',
					'0' 
			) );
			// End Debugging
			if (! $this->redis->hasSet ( '@person' )) {
				error_log ( "Inside Redis warmer @person..." . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
				// Now continue processing and warm the cache for @person
				$registration = new Registration ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$this->redis->warmPersonSet ();
			}
			
			if (! $this->redis->hasSet ( '#hashtag' )) {
				error_log ( "Inside Redis warmer #hashtag..." . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
				// warm the cache for #hashtag
				$session = new Container ( 'user' );
				$user_id = $session->offsetGet ( 'user_id' );
				error_log ( "Inside Redis warmer user_id ---> $user_id" . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
				$this->redis->warmHashTagSet ( $user_id );
			}
		}
		// Need to exit here to avoid ZF2 framework view.
		exit ();
	} // end indexcontroller...
	public function loginAction() {
		// Fetch the post data
		$request = $this->getRequest ();
		$postData = $request->getPost ()->toArray ();
		$username = $postData ['username'];
		$password = $postData ['password'];
		
		// Original Web Service Call...
		// Setup the URL and action
		$action = 'login';
		$xml = "<xml><login><username>$username</username><password>$password</password></login></xml>";
		$redirect = 'gallery';
		
		// Guzzle the LoginWeb Service
		$result = $this->fetchXML ( $action, $xml );
		$data = simplexml_load_string ( $result );
		
		// ZF2 Authenticate
		if ($data->loginresponse->status == 'success') {
			$this->setSession ( $username );
			// Redirect here
			return $this->redirect ()->toRoute ( 'index', array (
					'action' => $redirect 
			) );
		} else {
			return $this->redirect ()->toRoute ( 'index', array (
					'action' => "index" 
			) );
		}
	}
	public function logoutAction() {
		$this->getSessionStorage ()->forgetMe ();
		$this->getAuthService ()->clearIdentity ();
		$session = new Container ( 'user' );
		$session->getManager ()->destroy ();
		
		$view = new ViewModel ();
		$view->setTemplate ( 'application/index/index.phtml' ); // path to phtml file under view folder
		return $view;
	}
	public function setSession($username) {
		// Fetch the user's data and store it in the session...
		$user = $this->getUserTable ()->getUserByUsername ( $username );
		unset ( $user->password );
		unset ( $user->disable_account );
		unset ( $user->create_date );
		unset ( $user->update_time );
		$session = new Container ( 'user' );
		$session->offsetSet ( 'user_id', $user->user_id );
		$session->offsetSet ( 'username', $username );
		$session->offsetSet ( 'sid', session_id () );
		$session->offsetSet ( 'user', json_encode ( $user ) );
	}
	public function registrationAction() {
		// Fetch the post data
		$postData = $this->getRequest ()->getPost ()->toArray ();
		$email = $postData ['email'];
		$username = $postData ['username'];
		$password = $postData ['password'];
		
		// Setup the URL and action
		$action = 'registration';
		$xml = "<xml><registration><email>$email</email><username>$username</username><password>$password</password></registration></xml>";
		$redirect = 'event';
		
		// Guzzle the Registration Web Service
		$result = $this->fetchXML ( $action, $xml );
		$data = simplexml_load_string ( $result );
		
		// ZF2 Authenticate
		if ($data->registrationresponse->status == 'success') {
			$this->setSession ( $username );
			
			// If there's a profile pic upload it...
			if (isset ( $_FILES ['file'] )) {
				$file = $_FILES ['file'];
				$fileName = $file ['name'];
				$filetype = $file ['type'];
				$filetmp_name = $file ['tmp_name'];
				$filesize = $file ['size'];
				
				// echo "filename ----> $fileName<BR>";
				// echo "filetype ----> $filetype<BR>";
				// echo "filetmp_name ----> $filetmp_name<BR>";
				// echo "filesize ----> $filesize<BR>";
				
				$url = MemreasConstants::ORIGINAL_URL;
				$guzzle = new Client ();
				$session = new Container ( 'user' );
				$request = $guzzle->post ( $url )->addPostFields ( array (
						'user_id' => $session->offsetGet ( 'user_id' ),
						'filename' => $fileName,
						'event_id' => "",
						'device_id' => "",
						'is_profile_pic' => 1,
						'is_server_image' => 0 
				) )->addPostFiles ( array (
						'f' => $filetmp_name 
				) );
			}
			$response = $request->send ();
			$data = $response->getBody ( true );
			$xml = simplexml_load_string ( $result );
			
			// ZF2 Authenticate
			error_log ( "addmediaevent result -----> " . $data );
			if ($xml->addmediaeventresponse->status == 'success') {
				// Do nothing even if it fails...
			}
			
			// Redirect here
			return $this->redirect ()->toRoute ( 'index', array (
					'action' => $redirect 
			) );
		} else {
			return $this->redirect ()->toRoute ( 'index', array (
					'action' => "index" 
			) );
		}
	}
	public function getUserTable() {
		if (! $this->userTable) {
			$sm = $this->getServiceLocator ();
			$this->userTable = $sm->get ( 'Application\Model\UserTable' );
		}
		return $this->userTable;
	}
	public function getAuthService() {
		if (! $this->authservice) {
			$this->authservice = $this->getServiceLocator ()->get ( 'AuthService' );
		}
		
		return $this->authservice;
	}
	public function getSessionStorage() {
		if (! $this->storage) {
			$this->storage = $this->getServiceLocator ()->get ( 'application\Model\MyAuthStorage' );
		}
		
		return $this->storage;
	}
	public function requiresSecureAction($actionname) {
		/*
		 * Check action to see if session is needed...
		 */
		$public = array (
				'login',
				'registration',
				'forgotpassword',
				'checkusername',
				'verifyemailaddress',
				// For stripe
				'getplans',
				'getplansstatic',
				'getorderhistory',
				'getorder',
				'getaccountdetail',
				'refund',
				'listpayees',
				'makepayout',
				'getdiskusage' 
		);
		if (in_array ( $actionname, $public )) {
			error_log ( 'Inside else in_array actionname ->' . $actionname . PHP_EOL );
			return false;
		}
		return true;
	}
	public function fetchSession($actionname, $requiresExistingSession) {
		/*
		 * Setup Redis and the session save handle
		 */
		$sid_success = 0;
		try {
			$this->redis = new AWSMemreasRedisCache ( $this->getServiceLocator () );
			// $sessionManager = new SessionManager ();
			// $config = new SessionConfig ();
			// $saveHandler = new MemreasRedisSaveHandler ( $this->redis );
			// $sessionManager->setSaveHandler ( $saveHandler );
			// $sessionManager->getSessionStorage()->setMetadata()
			// Container::setDefaultManager ( $sessionManager );
			$sessHandler = new AWSMemreasRedisSessionHandler ();
			session_set_save_handler ( $sessHandler );
			
			/*
			 * Check sid against logged in sid
			 */
			if ($requiresExistingSession) {
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				if (! empty ( $data->sid )) {
					/*
					 * SetId for the session and start...
					 */
					session_id ( $data->sid );
					session_start ();
					if (session_id () == $data->sid) {
						$sid_success = 1;
					}
					
					// $session = new Container ( 'user' );
					// error_log ( 'rSession login session variables user_id-->' . $session->offsetGet ( 'user_id' ) . PHP_EOL );
					// error_log ( 'rSession login session variables username-->' . $session->offsetGet ( 'username' ) . PHP_EOL );
					// error_log ( 'rSession login session variables sid-->' . $session->offsetGet ( 'sid' ) . PHP_EOL );
					// error_log ( 'rSession login session variables email_address-->' . $session->offsetGet ( 'email_address' ) . PHP_EOL );
					// error_log ( 'rSession login session variables device_id-->' . $session->offsetGet ( 'device_id' ) . PHP_EOL );
					// error_log ( 'rSession login session variables device_type-->' . $session->offsetGet ( 'device_type' ) . PHP_EOL );
					// error_log ( 'rSession login session variables fesid-->' . $session->offsetGet ( 'fesid' ) . PHP_EOL );
					// error_log ( 'rSession login session variables ipAddress-->' . $session->offsetGet ( 'ipAddress' ) . PHP_EOL );
				} else if (! empty ( $data->fesid )) {
					$rFESession = $this->redis->getCache ( $data->fesid );
					error_log ( '$rSession for fesid lookup ----->' . $rFESession . PHP_EOL );
					$rFESessionArr = json_decode ( $rFESession, true );
					error_log ( '$rFESessionArr for fesid lookup ----->' . print_r ( $rFESessionArr, true ) . PHP_EOL );
					// $sessionManager->writeClose ();
					// $sessionManager->destroy ();
					// $sessionManager->setId ( $rFESessionArr ['sid'] );
					// $sessionManager->start ( true );
					// $session = new Container ( 'user' );
					// error_log ( '$rFESession login session variables user_id-->' . $session->offsetGet ( 'user_id' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables username-->' . $session->offsetGet ( 'username' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables sid-->' . $session->offsetGet ( 'sid' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables email_address-->' . $session->offsetGet ( 'email_address' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables device_id-->' . $session->offsetGet ( 'device_id' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables device_type-->' . $session->offsetGet ( 'device_type' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables fesid-->' . $session->offsetGet ( 'fesid' ) . PHP_EOL );
					// error_log ( '$rFESession login session variables ipAddress-->' . $session->offsetGet ( 'ipAddress' ) . PHP_EOL );
					session_id ( $rFESessionArr ['sid'] );
					session_start ();
					if (session_id () == $rFESessionArr ['sid']) {
						$sid_success = 1;
					}
				}
				
				$currentIPAddress = $this->fetchUserIPAddress ();
				if ($currentIPAddress != $_SESSION['ipAddress']) {
					error_log("ERROR::User IP Address has changed!!");
				}
				$_SESSION ['user'] ['HTTP_USER_AGENT'] = "";
				if (! empty ( $_SERVER ['HTTP_USER_AGENT'] )) {
					$_SESSION ['user'] ['HTTP_USER_AGENT'] = $_SERVER ['HTTP_USER_AGENT'];
				}
				
				if (!$sid_success) {
					error_log ( 'SID IS NOT SET !!!!!' . PHP_EOL );
					return 'notlogin';
				}
			} // end if ($requiresExistingSession)
		} catch ( \Exception $e ) {
			// echo 'Caught exception: ', $e->getMessage(), "\n";
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
		
		return $actionname;
	}
	public function fetchUserIPAddress() {
		/*
		 * Fetch the user's ip address
		 */
		$this->ipAddress = $this->getServiceLocator ()->get ( 'Request' )->getServer ( 'REMOTE_ADDR' );
		if (! empty ( $_SERVER ['HTTP_CLIENT_IP'] )) {
			$this->ipAddress = $_SERVER ['HTTP_CLIENT_IP'];
		} else if (! empty ( $_SERVER ['HTTP_X_FORWARDED_FOR'] )) {
			$this->ipAddress = $_SERVER ['HTTP_X_FORWARDED_FOR'];
		} else {
			$this->ipAddress = $_SERVER ['REMOTE_ADDR'];
		}
		error_log ( 'ip is ' . $this->ipAddress );
		
		return $this->ipAddress;
	}
}
// end class IndexController
