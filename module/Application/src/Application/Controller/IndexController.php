<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Controller;

use Application\memreas\AddComment;
use Application\memreas\AddEvent;
use Application\memreas\AddExistMediaToEvent;
use Application\memreas\AddFriend;
use Application\memreas\AddFriendtoevent;
use Application\memreas\AddFriendToGroup;
use Application\memreas\AddMediaEvent;
use Application\memreas\AddNotification;
use Application\memreas\AWSManagerSender;
use Application\memreas\AWSMemreasRedisCache;
use Application\memreas\AWSMemreasRedisSessionHandler;
use Application\memreas\ChangePassword;
use Application\memreas\CheckEvent;
use Application\memreas\CheckExistMedia;
use Application\memreas\ChkUname;
use Application\memreas\ClearAllNotification;
use Application\memreas\CountListallmedia;
use Application\memreas\CountViewevent;
use Application\memreas\CreateGroup;
use Application\memreas\DeletePhoto;
use Application\memreas\Download;
use Application\memreas\EditEvent;
use Application\memreas\FeedBack;
use Application\memreas\FetchCopyRightBatch;
use Application\memreas\ForgotPassword;
use Application\memreas\GenerateMediaId;
use Application\memreas\GetDiskUsage;
use Application\memreas\GetEventCount;
use Application\memreas\GetEventDetails;
use Application\memreas\GetEventLocation;
use Application\memreas\GetEventPeople;
use Application\memreas\GetFriends;
use Application\memreas\GetGroupFriends;
use Application\memreas\GetMediaLike;
use Application\memreas\GetSession;
use Application\memreas\GetSocialCredentials;
use Application\memreas\GetUserDetails;
use Application\memreas\GetUserGroups;
use Application\memreas\LikeMedia;
use Application\memreas\ListAllmedia;
use Application\memreas\ListComments;
use Application\memreas\ListGroup;
use Application\memreas\ListMemreasFriends;
use Application\memreas\ListNotification;
use Application\memreas\ListPhotos;
use Application\memreas\Login;
use Application\memreas\LogOut;
use Application\memreas\MediaDeviceTracker;
use Application\memreas\MediaInappropriate;
use Application\memreas\MemreasSignedURL;
use Application\memreas\MemreasTables;
use Application\memreas\Memreastvm;
use Application\memreas\Mlog;
use Application\memreas\RegisterCanonicalDevice;
use Application\memreas\RegisterDevice;
use Application\memreas\Registration;
use Application\memreas\RemoveEventFriend;
use Application\memreas\RemoveEventMedia;
use Application\memreas\RemoveFriendGroup;
use Application\memreas\RemoveFriends;
use Application\memreas\RemoveGroup;
use Application\memreas\ReTransCoder;
use Application\memreas\SaveUserDetails;
use Application\memreas\snsProcessMediaPublish;
use Application\memreas\StripeWS\ListPayees;
use Application\memreas\UpdateMedia;
use Application\memreas\UpdateNotification;
use Application\memreas\UpdatePassword;
use Application\memreas\UploadAdvertisement;
use Application\memreas\Utility;
use Application\memreas\VerifyEmailAddress;
use Application\memreas\ViewAllfriends;
use Application\memreas\ViewEvents;
use Application\memreas\ViewMediadetails;
use Application\Model\MemreasConstants;
use GuzzleHttp\Client;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\Session\Container;
use Zend\View\Model\ViewModel;
use Application\memreas\DcmaList;
use Application\memreas\DcmaCounterClaim;
use Application\memreas\DcmaReportViolation;

// Stripe Web Services
use Application\memreas\StripeWS\GetAccountDetail;
use Application\memreas\StripeWS\GetOrder;
use Application\memreas\StripeWS\GetOrderHistory;
use Application\memreas\StripeWS\GetPlans;
use Application\memreas\StripeWS\GetPlansStatic;
use Application\memreas\StripeWS\MakePayout;
use Application\memreas\StripeWS\PaymentsProxy;
use Application\memreas\StripeWS\Refund;
use Application\memreas\FetchChameleon;

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
	protected $sessHandler;
	protected $sm;
	public function __construct($sm) {
		$this->sm = $sm;
	}
	public function isJSON($string) {
		return is_string ( $string ) && is_array ( json_decode ( $string, true ) ) && (json_last_error () == JSON_ERROR_NONE) ? true : false;
	}
	public function setupSaveHandler() {
		$this->redis = new AWSMemreasRedisCache ( $this->sm );
		$this->sessHandler = new AWSMemreasRedisSessionHandler ( $this->redis, $this->sm );
		session_set_save_handler ( $this->sessHandler );
	}
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
		$guzzle = new \GuzzleHttp\Client ();
		$response = $guzzle->post ( $this->url, [ 
				'form_params' => [ 
						'action' => $action,
						'cache_me' => true,
						'xml' => $xml 
				] 
		] );
		
		return $response->getBody ();
	}
	public function inputToObject($string) {
		$in_data = trim ( $string );
		if (empty ( $in_data )) {
			return null;
		}
		
		if ($in_data [0] == '<') {
			$data = simplexml_load_string ( $in_data );
		} else {
			$data = json_decode ( $in_data );
		}
		return $data;
	}
	public function indexAction() {
		$cm = __CLASS__ . __METHOD__;
		// Start capture so we control what is sent back...
		ob_start ();
		
		$path = "application/index/ws_tester.phtml";
		$output = '';
		
		$callback = isset ( $_REQUEST ['callback'] ) ? $_REQUEST ['callback'] : '';
		
		Mlog::addone ( $cm . __LINE__ . '::IndexController $_REQUEST', $_REQUEST );
		Mlog::addone ( $cm . __LINE__ . '::IndexController $_POST', $_POST );
		Mlog::addone ( $cm . __LINE__ . '::IndexController $_COOKIE', $_COOKIE );
		if (isset ( $_REQUEST ['json'] )) {
			// Handle JSon
			$reqArr = json_decode ( $_REQUEST ['json'], true );
			$actionname = $_REQUEST ['action'] != 'ws_tester' ? $_REQUEST ['action'] : $reqArr ['action'];
			$type = $reqArr ['type'];
			$data = $message_data = $reqArr ['json'];
			
			if (isset ( $message_data ['xml'] )) {
				// is requied by next serving classes
				$_POST ['xml'] = $message_data ['xml'];
				
				$data = $this->inputToObject ( $message_data ['xml'] );
			} else {
				$data = ( object ) $data;
			}
		} else {
			// assuming xml if not json
			$data = simplexml_load_string ( $_POST ['xml'] );
			$actionname = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
			// dont remove just to be safe relying on $_POST data
			$message_data ['xml'] = '';
		}
		// Mlog::addone ( $cm . __LINE__ . '::IndexController $data', $data );
		
		/**
		 * Setup save handler
		 */
		$this->setupSaveHandler ();
		
		/**
		 * Bypass section - handle with care!!
		 */
		Mlog::addone ( $cm . __LINE__ . '::input data as object---> ', $data );
		
		if (($actionname == 'addmediaevent') && ((( int ) $data->addmediaevent->is_profile_pic) == 1) && ((( int ) $data->addmediaevent->is_registration) == 1)) {
			// do nothing - profile pic upload for registration
		} else if (($actionname == 'memreas_tvm') && isset ( $data->user_id )) {
			// do nothing - fetching token to upload profile pic
		} else if ($this->requiresSecureAction ( $actionname )) {
			$actionname = $this->fetchSession ( $actionname, true, $data );
		}
		
		/**
		 * For testing only...
		 */
		if ($actionname == "ws_tester") {
			// error_log ( "path--->" . $path );
			$view = new ViewModel ();
			$view->setTemplate ( $path ); // path to phtml file under view
			                              // folder
			return $view;
		}
		if ($actionname == "stripe_ws_tester") {
			// error_log ( "path--->" . $path );
			$view = new ViewModel ();
			$user_id = (isset ( $_SESSION ['user_id'] )) ? $_SESSION ['user_id'] : '';
			$username = (isset ( $_SESSION ['username'] )) ? $_SESSION ['username'] : '';
			$view->setVariable ( "user_id", $user_id );
			$view->setVariable ( "username", $username );
			$view->setTemplate ( "application/index/stripe_ws_tester.phtml" ); // path to phtml file under view
			                                                                   // folder
			return $view;
		}
		
		if (isset ( $actionname ) && ! empty ( $actionname )) {
			$cache_me = false;
			$cache_id = null;
			$invalidate = false;
			$invalidate_me = false;
			
			if (isset ( $_POST ['xml'] ) && ! empty ( $_POST ['xml'] )) {
				error_log ( "Input data as xml ----> " . $_POST ['xml'] . PHP_EOL );
			}
			
			$memreas_tables = new MemreasTables ( $this->sm );
			
			if ($actionname == 'notlogin') {
				$result = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$result .= "<xml><error>Please Login </error></xml>";
				
				/*
				 * Cache approach - N/a
				 */
			} else if ($actionname == 'fetchchameleon') {
				
				$fetchChameleon = new FetchChameleon ();
				$fetchChameleon->exec ();
			} else if ($actionname == "login") {
				$login = new Login ( $message_data, $memreas_tables, $this->sm );
				$login->exec ( $this->sessHandler, $this->fetchUserIPAddress () );
				
				/*
				 * Cache approach - warm @person if not set here
				 */
				// if ((MemreasConstants::REDIS_SERVER_USE) && (!
				// MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
				if (MemreasConstants::REDIS_SERVER_USE) {
					if ($this->redis->hasSet ( '@person' )) {
						
						/*
						 * TODO: Add the user (login from email verification) only if s/he doesn't exist in
						 * the hash (i.e. 1st login will force cache to
						 * warm)
						 */
						// $mc [$_SESSION['username']] = array (
						// 'user_id' => $_SESSION['user_id'],
						// 'email_address' => $_SESSION ['email_address'] ,
						// 'profile_photo' => $_SESSION
						// ['profile_pic_meta'],
						// );
						// $this->redis->addSet ( "@person_meta_hash",
						// $username, json_encode ( $mc [$username] ) );
						// $this->redis->addSet ( "@person_uid_hash",
						// $username, $user_id );
						// $this->redis->addSet ( "@person", $username );
						// error_log ("$username added - @person_hash set
						// now holds --> ". $this->redis->hasSet('@person')
						// . "
						// users@ " . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL);
					}
				}
			} else if ($actionname == "registration") {
				$registration = new Registration ( $this->sessHandler, $message_data, $memreas_tables, $this->sm );
				$result = $registration->exec ();
				
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->registration->username );
			} else if ($actionname == "addcomments") {
				$addcomment = new AddComment ( $message_data, $memreas_tables, $this->sm );
				$result = $addcomment->exec ();
				
				/*
				 * Cache approach - Write Operation - Invalidate
				 * listcomment here
				 */
				// $data = simplexml_load_string($_POST ['xml']);
				// if (isset($data->addcomment->event_id)) {
				// //Invalidate existing cache
				// $this->redis->invalidateCache("listcomments_" .
				// $data->addcomment->event_id);
				// }
			} else if ($actionname == "verifyemailaddress") {
				$verifyemailaddress = new VerifyEmailAddress ( $message_data, $memreas_tables, $this->sm );
				$verified_user_id = $verifyemailaddress->exec ();
				if ($verified_user_id) {
					// udpade catch
					$this->redis->updatePersonCache ( $verified_user_id );
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
				 * suggested update user catch
				 */
			} else if ($actionname == "checkusername" || $actionname == "chkuname") {
				$chkuname = new ChkUname ( $message_data, $memreas_tables, $this->sm );
				$result = $chkuname->exec ();
				
				/*
				 * Cache approach - read operation - pass
				 * for now
				 */
			} else if ($actionname == "fetchcopyrightbatch") {
				$fetchcopyrightbatch = new FetchCopyRightBatch ( $message_data, $memreas_tables, $this->sm );
				$result = $fetchcopyrightbatch->exec ();
			} else if ($actionname == "generatemediaid") {
				$generatemediaid = new GenerateMediaId ( $message_data, $memreas_tables, $this->sm );
				$result = $generatemediaid->exec ();
			} else if ($actionname == "addmediaevent") {
				
				$addmediaevent = new AddMediaEvent ( $message_data, $memreas_tables, $this->sm );
				$result = $addmediaevent->exec ();
				// $this->sessHandler->startSessionWithSID($_SESSION['sid']);
				
				/*
				 * - Cache approach
				 * - write operation
				 * - TODO: invalideate cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				// $this->redis->invalidateMedia
				// (
				// $data->addmediaevent->user_id,
				// $data->addmediaevent->event_id,
				// $data->addmediaevent->media_id
				// );
			} else if ($actionname == "likemedia") {
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->likemedia->user_id );
				/*
				 * - Cache approach
				 * - write operation
				 * - TODO: invalideate cache
				 */
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				if (! $result || empty ( $result )) {
					$likemedia = new LikeMedia ( $message_data, $memreas_tables, $this->sm );
					$result = $likemedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "mediainappropriate") {
				$data = simplexml_load_string ( $_POST ['xml'] );
				$mediainappropriate = new MediaInappropriate ( $message_data, $memreas_tables, $this->sm );
				$result = $mediainappropriate->exec ();
				/*
				 * -
				 * Cache approach
				 * - write operation
				 * - TODO: invalideate cache
				 */
				// $this->redis->invalidateMedia
				// (
				// $data->mediainappropriate->user_id,
				// $data->mediainappropriate->event_id
				// );
			} else if ($actionname == "countlistallmedia") {
				
				/*
				 * - Cache approach
				 * - read operation
				 * - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->countlistallmedia->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$countlistallmedia = new CountListallmedia ( $message_data, $memreas_tables, $this->sm );
					$result = $countlistallmedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "listgroup") {
				/*
				 * - Cache approach
				 * - read operation
				 * - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listgroup->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result) {
					$listgroup = new ListGroup ( $message_data, $memreas_tables, $this->sm );
					$result = $listgroup->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "deletephoto") {
				$deletephoto = new DeletePhoto ( $message_data, $memreas_tables, $this->sm );
				$result = $deletephoto->exec ();
				
				/*
				 * -
				 * TODO:
				 * Cache approach
				 * - Write Operation
				 * - Invalidate existing cache here
				 * -- need user_id or event_id
				 * --- this is based on media_id
				 */
				
				// $session
				// =
				// new
				// Container("user");
				// $this->redis->invalidateCache("listallmedia_"
				// .
				// $session->user_id);
				// $this->redis->invalidateCache("viewevents_"
				// .
				// $session->user_id);
			} else if ($actionname == "listphotos") {
				/*
				 * - Cache approach
				 * - read operation
				 * - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listphotos->userid );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result) {
					$listphotos = new ListPhotos ( $message_data, $memreas_tables, $this->sm );
					$result = $listphotos->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "forgotpassword") {
				$forgotpassword = new ForgotPassword ( $message_data, $memreas_tables, $this->sm );
				$result = $forgotpassword->exec ();
				/* - Cache approach - N/a - */
			} else if ($actionname == "download") {
				$download = new Download ( $message_data, $memreas_tables, $this->sm );
				$result = $download->exec ();
				/* - Cache approach - N/a - */
			} else if ($actionname == "viewallfriends") {
				/*
				 * - Cache approach
				 * - read operation
				 * - cache
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->viewallfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$viewallfriends = new ViewAllfriends ( $message_data, $memreas_tables, $this->sm );
					$result = $viewallfriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "creategroup") {
				$creategroup = new CreateGroup ( $message_data, $memreas_tables, $this->sm );
				$result = $creategroup->exec ();
				
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->creategroup->user_id );
			
			/**
			 * Cache Approach:
			 * - Check cache first if not there then fetch and cache...
			 * - if event_id then return that cache else user_id
			 */
			} else if ($actionname == "listallmedia") {
				/*
				 * - Cache Approach: Check cache first if not there then fetch and cache...
				 * if event_id then return that cache else user_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->listallmedia->event_id )) {
					$cache_id = $data->listallmedia->event_id;
				} else if (! empty ( $data->listallmedia->user_id )) {
					$cache_id = $data->listallmedia->user_id;
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				if (! $result || empty ( $result )) {
					$listallmedia = new ListAllmedia ( $message_data, $memreas_tables, $this->sm );
					$result = $listallmedia->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "countviewevent") {
				/*
				 * - Cache Approach:
				 * TODO: invalide - hold for now
				 * Check cache first
				 * if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->countviewevent->is_public_event ) && $data->countviewevent->is_public_event) {
					$cache_id = "public";
				} else {
					$cache_id = trim ( $data->countviewevent->user_id );
				}
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$countviewevent = new CountViewevent ( $message_data, $memreas_tables, $this->sm );
					$result = $countviewevent->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "editevent") {
				$editevent = new EditEvent ( $message_data, $memreas_tables, $this->sm );
				$result = $editevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$event_id = trim ( $data->editevent->event_id );
				
				/*
				 * -
				 * Cache Approach:
				 * TODO: invalidate - hold for now
				 */
				// $this->redis->invalidateEvents
				// (
				// $event_id
				// );
			} else if ($actionname == "addevent") {
				$addevent = new AddEvent ( $message_data, $memreas_tables, $this->sm );
				$result = $addevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * -
				 * Cache Approach:
				 * TODO: invalide - hold for now
				 */
				
				// $this->redis->invalidateEvents
				// (
				// $data->addevent->user_id
				// );
			} else if ($actionname == "viewevents") {
				/*
				 * - Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->viewevent->is_public_event ) && $data->viewevent->is_public_event) {
					$cache_id = "public";
				} else if (! empty ( $data->viewevent->is_friend_event ) && $data->viewevent->is_friend_event) {
					/*
					 * -
					 * friend events includes public
					 */
					$cache_id = "is_friend_event_" . trim ( $data->viewevent->user_id );
				} else if (! empty ( $data->viewevent->is_my_event ) && $data->viewevent->is_my_event) {
					$cache_id = "is_my_event_" . trim ( $data->viewevent->user_id );
				}
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					Mlog::addone ( $cm . __LINE__, 'COULD NOT FIND REDIS viewevents::$this->redis->getCache ( $actionname . _ . $cache_id ) for ---->' . $actionname . '_' . $cache_id );
					$viewevents = new ViewEvents ( $message_data, $memreas_tables, $this->sm );
					$result = $viewevents->exec ();
					$cache_me = true;
				} else {
					Mlog::addone ( $cm . __LINE__, 'FETCHING viewevents FROM REDIS::$this->redis->getCache ( $actionname . _ . $cache_id ) for ---->' . $actionname . '_' . $cache_id );
				}
			} else if ($actionname == "addfriend") {
				
				$addfriend = new AddFriend ( $message_data, $memreas_tables, $this->sm );
				$result = $addfriend->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->addfriend->user_id );
				$fid = trim ( $data->addfriend->friend_id );
				/*
				 * -
				 * Cache approach
				 * - write operation
				 * - hold for now
				 */
				// $this->redis->invalidateEvents
				// (
				// $uid
				// );
				// $this->redis->invalidateGroups
				// (
				// $uid
				// );
			} else if ($actionname == "addfriendtoevent") {
				$addfriendtoevent = new AddFriendtoevent ( $message_data, $memreas_tables, $this->sm );
				$result = $addfriendtoevent->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->addfriendtoevent->user_id );
				
				/*
				 * -
				 * Cache approach
				 * - write operation
				 * - hold for now
				 */
				// $this->redis->invalidateEvents
				// (
				// $uid
				// );
				// $this->redis->invalidateGroups
				// (
				// $uid
				// );
			} else if ($actionname == "viewmediadetails") {
				/*
				 * - Cache Approach: Check cache first if not there then fetch and cache...
				 * if event_id then return then event_id_media_id else cache media_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->viewmediadetails->event_id ) && ! empty ( $data->viewmediadetails->media_id )) {
					$cache_id = trim ( $data->viewmediadetails->event_id ) . "_" . trim ( $data->viewmediadetails->media_id );
				} else if (! empty ( $data->viewmediadetails->media_id )) {
					$cache_id = trim ( $data->viewmediadetails->media_id );
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$viewmediadetails = new ViewMediadetails ( $message_data, $memreas_tables, $this->sm );
					$result = $viewmediadetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "snsProcessMediaPublish") {
				$snsProcessMediaPublish = new snsProcessMediaPublish ( $message_data, $memreas_tables, $this->sm );
				$result = $snsProcessMediaPublish->exec ();
			} else if ($actionname == "memreas_tvm") {
				Mlog::addone ( $cm . __LINE__, 'Enter ' . $actionname );
				$memreastvm = new Memreastvm ( $message_data, $memreas_tables, $this->sm );
				$result = $memreastvm->exec ();
				Mlog::addone ( $cm . __LINE__ . '::Exit ' . $actionname . ' result--->', $result );
			} else if ($actionname == "uploadadvertisement") {
				$uploadadvertisement = new UploadAdvertisement ( $message_data, $memreas_tables, $this->sm );
				$result = $uploadadvertisement->exec ();
			} else if ($actionname == "addNotification") {
				$addNotification = new AddNotification ( $message_data, $memreas_tables, $this->sm );
				$result = $addNotification->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->addNotification->user_id );
			/**
			 * Cache approach
			 * - write operation
			 * - invalidate listnotification
			 */
				// $this->redis->invalidateNotifications($uid);
			} else if ($actionname == "changepassword") {
				$changepassword = new ChangePassword ( $message_data, $memreas_tables, $this->sm );
				$result = $changepassword->exec ();
			} else if ($actionname == "retranscoder") {
				$retranscoder = new ReTransCoder ( $message_data, $memreas_tables, $this->sm );
				$result = $retranscoder->exec ();
			} else if ($actionname == "listnotification") {
				
				/*
				 * - Cache Approach: Check cache first if not there then fetch and cache...
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
					$listnotification = new ListNotification ( $message_data, $memreas_tables, $this->sm );
					$result = $listnotification->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "updatenotification") {
				$updatenotification = new UpdateNotification ( $message_data, $memreas_tables, $this->sm );
				$result = $updatenotification->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = $updatenotification->user_id;
			
			/**
			 * Cache approach
			 * - write operation
			 * - invalidate listnotification
			 */
				// $this->redis->invalidateNotifications($uid);
			} else if ($actionname == "findtag") {
				
				/*
				 * - fetch parameters
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$tag = (trim ( $data->findtag->tag ));
				$user_id = (trim ( $data->findtag->user_id ));
				$user_id = empty ( $user_id ) ? 0 : $user_id;
				$a = $tag [0];
				$search = substr ( $tag, 1 );
				
				/*
				 * - set paging and limits
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
					/**
					 * -
					 * @person search
					 */
					case '@':

                    	/*
                    	 * TODO: Migrate to redis search - see example below
                    	 */
						$user_ids = array ();
						if (MemreasConstants::REDIS_SERVER_USE) {
							Mlog::addone ( $cm . __LINE__, "::@person search initiating search from REDIS" );
							/*
							 * -
							 * Redis - this code fetches usernames by the search term then gets the hashes
							 */
							$usernames = $this->redis->findSet ( '@person', $search );
							/*
							 * -
							 * remove self and update indices
							 */
							$index = array_search ( $_SESSION ['username'], $usernames );
							unset ( $usernames [$index] );
							$usernames = array_values ( $usernames );
							$person_meta_hash = $this->redis->cache->hmget ( "@person_meta_hash", $usernames );
							
							/*
							 * -
							 * remove self and update indices
							 */
							$user_ids = array ();
							$search_array_values = array_values ( $person_meta_hash );
							$search_result = array ();
							foreach ( $search_array_values as $entry ) {
								$entry_arr = json_decode ( $entry, true );
								$user_ids [] = $entry_arr ['user_id'];
								$search_result [] = $entry_arr;
							}
							$rc = count ( $search_result );
							Mlog::addone ( $cm . __LINE__ . "::@person search completed search from REDIS result count--->", $rc );
							Mlog::addone ( $cm . __LINE__ . "::@person search completed search from REDIS result --->", $search_result, 'p' );
						} else {
							Mlog::addone ( $cm . __LINE__, "::@person search initiating build cache and search in db" );
							$registration = new registration ( $message_data, $memreas_tables, $this->sm );
							$registration->createUserCache ();
							$person_meta_hash = $registration->userIndex;
							/*
							 * -
							 * Remove current user - All entries in this hash match the search key
							 */
							foreach ( $person_meta_hash as $username => $usermeta ) {
								$meta_arr = json_decode ( $usermeta );
								$uid = $meta_arr ['user_id'];
								/*
								 * -
								 * Remove existing user
								 */
								Mlog::addone ( $cm . __LINE__ . "remove existing user for seach key username" . $username . " --- uid-->", $uid );
								if ($uid == $user_id) {
									continue;
								}
								
								/*
								 * - TODO: Fix Paging
								 */
								/*
								 * -
								 * if ($rc >= $from && $rc < ($from + $limit)) {
								 * Mlog::addone($cm.__LINE__."meta_arr ['username']--->" . $meta_arr ['username'] . " --- search-->" , $search);
								 * }
								 */
								if (stripos ( $meta_arr ['username'], $search ) !== false) {
									$meta_arr ['username'] = '@' . $meta_arr ['username'];
									$search_result [] = $meta_arr;
									$user_ids [] = $uid;
									Mlog::addone ( $cm . __LINE__ . "user_ids-->", json_encode ( $user_ids ) );
								}
								// }
							}
							$rc = count ( $search_result );
						}
						
						/*
						 * -
						 * This section filter friend requests sent...
						 */
						$em = $this->sm->get ( 'doctrine.entitymanager.orm_default' );
						/*
						 * -
						 * This query fetches user's friends
						 */
						$qb = $em->createQueryBuilder ();
						$qb->select ( 'f.friend_id,uf.user_approve' );
						$qb->from ( 'Application\Entity\Friend', 'f' );
						$qb->join ( 'Application\Entity\UserFriend', 'uf', 'WITH', 'uf.friend_id = f.friend_id' );
						$qb->where ( "f.network='memreas'" );
						$qb->andwhere ( "uf.user_approve = '1'" );
						$qb->andwhere ( "uf.user_id = '$user_id'" );
						$qb->andwhere ( 'uf.friend_id IN (:f)' );
						$qb->setParameter ( 'f', $user_ids );
						
						$UserFriends = $qb->getQuery ()->getResult ();
						if ($UserFriends) {
							/*
							 * -
							 * this code checks if friend request already sent...
							 */
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
						}
						
						/*
						 * -
						 * hide pagination
						 */
						$result = Array ();
						$result ['totalPage'] = 1;
						$result ['count'] = $rc;
						$result ['search'] = $search_result;
						
						echo json_encode ( $result );
						$result = '';
						break;
					
					/**
					 * -
					 * !memreas search
					 */
					case '!' :
						if (MemreasConstants::REDIS_SERVER_USE) {
							Mlog::addone ( '$this->redis->getCache ( "warming_memreas" )--->', $this->redis->getCache ( "warming_memreas" ) );
							
							Mlog::addone ( $cm . __LINE__ . '$this->redis->getCache("warming_memreas")', $this->redis->getCache ( "warming_memreas" ) );
							Mlog::addone ( $cm . __LINE__, "::!memreas search initiating search from REDIS" );
							/*
							 * -
							 * Redis - this code fetches usernames by the search term then gets the hashes
							 * - check public then friends...
							 */
							$search_result = $this->redis->findSet ( '!memreas', '!' . $search );
							Mlog::addone ( 'findSet public result-->', $search_result, 'p' );
							$search_result_friends = $this->redis->findSet ( '!memreas_friends_events_' . $user_id, $search );
							Mlog::addone ( 'findSet friends result-->', $search_result, 'p' );
							$search_result = array_merge ( $search_result, $search_result_friends );
							
							Mlog::addone ( 'findSet result-->', $search_result, 'p' );
							
							/*
							 * -
							 * fetch from hash
							 */
							$event_ids_from_search = $this->redis->cache->hmget ( "!memreas_meta_hash", $search_result );
							Mlog::addone ( 'hmget $event_ids_from_search -->', $event_ids_from_search, 'p' );
							$events_from_search = $this->redis->cache->hmget ( "!memreas_eid_hash", $event_ids_from_search );
							
							$rc = count ( $events_from_search );
							/**
							 * Decode because we have encode below...
							 */
							
							Mlog::addone ( $cm . __LINE__ . "::!memreas search completed search from REDIS result count--->", $rc );
							Mlog::addone ( $cm . __LINE__ . '::gettype($events_from_search)--->', gettype ( $events_from_search ) );
							$result = Array ();
							$result ['totalPage'] = 1;
							$result ['count'] = $rc;
							// $result ['search'] = $events_from_search;
							/**
							 * Need to decode json to avoid double encode
							 */
							foreach ( $events_from_search as $event ) {
								$result ['search'] [] = json_decode ( $event );
							}
							
							echo json_encode ( $result );
							// Mlog::addone ( $cm . __LINE__ . "::!memreas search completed search from REDIS result --->", json_encode ( $result ) );
						}
						$result = '';
						break;
					
					/*
					 * -
					 * #hashtag comment search
					 */
					case '#':
                    	/*
                    	 * TODO: Migrate to redis search - see example below
                    	 */
						$search_result = array ();
						if ((MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
							Mlog::addone ( $cm . __LINE__, "redis hashtag fetch TODO..." );
							Mlog::addone ( $cm . __LINE__, "Inside findTag # for tag $search" );
							$tags_public = $this->redis->findSet ( '#hashtag', $search );
							$tags_uid = $this->redis->findSet ( '#hashtag_' . $user_id, $search );
							$tags_unique = array_unique ( array_merge ( $tags_public, $tags_uid ) );
							Mlog::addone ( $cm . __LINE__, "Inside findTag # tags_unique--->" . json_encode ( $tags_unique ) );
							$hashtag_public_eid_hash = $this->redis->cache->hmget ( "#hashtag_public_eid_hash", $tags_unique );
							Mlog::addone ( $cm . __LINE__, "Inside findTag # hashtag_public_eid_hash--->" . json_encode ( $hashtag_public_eid_hash ) );
							$hashtag_friends_hash = $this->redis->cache->hmget ( '#hashtag_friends_hash_' . $user_id, $tags_unique );
							Mlog::addone ( $cm . __LINE__, "Inside findTag # hashtag_friends_hash--->" . json_encode ( $hashtag_friends_hash ) );
							
							$eventRep = $this->sm->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
							$mc = $eventRep->createDiscoverCache ( $search );
							$usernames = $this->redis->findSet ( '@person', $search );
							$person_meta_hash = $this->redis->cache->hmget ( "@person_meta_hash", $usernames );
							$person_uid_hash = $this->redis->cache->hmget ( '@person_uid_hash', $usernames );
							$user_ids = $usernames;
						} else {
							/*
							 * -
							 * Fetch from db
							 */
							$eventRep = $this->sm->get ( 'doctrine.entitymanager.orm_default' )->getRepository ( 'Application\Entity\Event' );
							
							/*
							 * -
							 * error_log ( "createDiscoverCache------>$tag" . PHP_EOL );
							 */
							$hashtag_cache = $eventRep->createDiscoverCache ( $tag );
						}
						
						foreach ( $hashtag_cache as $tag => $cache_entry ) {
							Mlog::addone ( $cm . __LINE__, "tag------>$tag" );
							Mlog::addone ( $cm . __LINE__, "cache_entry------>" . json_encode ( $cache_entry ) );
							if (stripos ( $cache_entry ['tag_name'], $search ) !== false) {
								/*
								 * -
								 * if ($rc >= $from && $rc < ($from + $limit)) {
								 */
								$cache_entry ['updated_on'] = Utility::formatDateDiff ( $cache_entry ['update_time'] );
								$cache_entry ['update_time'] = Utility::toDateTime ( $cache_entry ['update_time'] );
								$search_result [$tag] = $cache_entry;
								/*
								 * -
								 * }
								 * $rc += 1;
								 */
								//
							}
						}
						
						$result ['count'] = $rc;
						$result ['search'] = $search_result;
						$result ['page'] = $page;
						$result ['totalPage'] = ceil ( $rc / $limit );
						
						echo json_encode ( $result );
						$result = '';
						break;
					default :
						$result ['count'] = 0;
						$result ['search'] = array ();
						$result ['totalPage'] = 0;
						
						echo json_encode ( $result );
						$result = '';
						break;
				}
			} else if ($actionname == "signedurl") {
				/* - Cache Approach: N/a - */
				$signedurl = new MemreasSignedURL ( $message_data, $memreas_tables, $this->sm );
				$result = $signedurl->exec ();
			} else if ($actionname == "showlog") {
				/* - Cache Approach: N/a - */
				echo '<pre>' . file_get_contents ( getcwd () . '/php_errors.log' );
				ob_end_flush ();
				exit ();
			} else if ($actionname == "clearlog") {
				/* - Cache Approach: N/a - */
				unlink ( getcwd () . '/php_errors.log' );
				error_log ( "Log has been cleared!" );
				echo '<pre>' . file_get_contents ( getcwd () . '/php_errors.log' );
				ob_end_flush ();
				exit ();
			} else if ($actionname == "logout") {
				/* - Cache Approach: N/a - */
				$logout = new LogOut ( $message_data, $memreas_tables, $this->sm );
				$result = $logout->exec ( $this->sessHandler );
			} else if ($actionname == "clearallnotification") {
				/* - TODO: Cache Approach: write operation do later - */
				$logout = new ClearAllNotification ( $message_data, $memreas_tables, $this->sm );
				$result = $logout->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				$uid = trim ( $data->clearallnotification->user_id );
			
			/**
			 * -
			 * Cache approach
			 * - write operation
			 * - invalidate listnotification
			 */
				// $this->redis->invalidateNotifications
				// (
				// $uid
				// );
			} else if ($actionname == "getsession") {
				/*
				 * - Cache Approach: Check cache first if not there then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listnotification->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$getsession = new GetSession ( $message_data, $memreas_tables, $this->sm );
					$result = $getsession->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "registerdevice") {
				$register_device = new RegisterDevice ( $message_data, $memreas_tables, $this->sm );
				$result = $register_device->exec ();
			} else if ($actionname == "registercanonicaldevice") {
				$register_canonical_device = new RegisterCanonicalDevice ( $message_data, $memreas_tables, $this->sm );
				$result = $register_canonical_device->exec ();
			} else if ($actionname == "listcomments") {
				
				/*
				 * - Cache Approach: Check cache first if not there then fetch and cache...
				 * if event_id then return then event_id_media_id
				 * else cache media_id
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				if (! empty ( $data->listcomments->event_id ) && ! empty ( $data->listcomments->media_id )) {
					$cache_id = trim ( $data->listcomments->event_id ) . "_" . trim ( $data->listcomments->media_id );
				} else if (! empty ( $data->listcomments->media_id )) {
					$cache_id = trim ( $data->listcomments->media_id );
				}
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$listcomments = new ListComments ( $message_data, $memreas_tables, $this->sm );
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
			} else if ($actionname == "geteventlocation") {
				
				/*
				 * Cache Approach: Check cache first if not there
				 * then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventlocation->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventLocation = new GetEventLocation ( $message_data, $memreas_tables, $this->sm );
					$result = $GetEventLocation->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "geteventcount") {
				/*
				 * Cache Approach: Check cache first if not there
				 * then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventcount->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventLocation = new GetEventCount ( $message_data, $memreas_tables, $this->sm );
					$result = $GetEventLocation->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getuserdetails") {
				/*
				 * Cache Approach: Check cache first if not there
				 * then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getuserdetails->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetUserDetails = new GetUserDetails ( $message_data, $memreas_tables, $this->sm );
					$result = $GetUserDetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "saveuserdetails") {
				/*
				 * - TODO: Invalidation needed
				 */
				$SaveUserDetails = new SaveUserDetails ( $message_data, $memreas_tables, $this->sm );
				$result = $SaveUserDetails->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * - Cache approach
				 * - write operation
				 * - invalidate listnotification
				 */
				$this->redis->invalidateUser ( $data->saveuserdetails->user_id );
			} else if ($actionname == "getusergroups") {
				/*
				 * Cache Approach: Check cache first if not there
				 * then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getusergroups->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetUserGroups = new GetUserGroups ( $message_data, $memreas_tables, $this->sm );
					$result = $GetUserGroups->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getgroupfriends") {
				/*
				 * Cache Approach: Check cache first if not there
				 * then fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$group_id = trim ( $data->getgroupfriends->group_id );
				$network = trim ( $data->getgroupfriends->network );
				$cache_id = $group_id;
				
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetGroupFriends = new GetGroupFriends ( $message_data, $memreas_tables, $this->sm );
					$result = $GetGroupFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "addfriendtogroup") {
				/*
				 * TODO: Invalidation needed
				 */
				$AddFriendToGroup = new AddFriendToGroup ( $message_data, $memreas_tables, $this->sm );
				$result = $AddFriendToGroup->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * TODO: Cache approach
				 * - write operation
				 */
				$this->redis->invalidateGroups ( $_SESSION ['user_id'] );
			} else if ($actionname == "removefriendgroup") {
				$RemoveFriendGroup = new RemoveFriendGroup ( $message_data, $memreas_tables, $this->sm );
				$result = $RemoveFriendGroup->exec ();
				
				/*
				 * TODO: Cache approach
				 * - write operation
				 * - need to invalidate listgroup but dont have user_id
				 */
				
				$this->redis->invalidateGroups ( $_SESSION ['user_id'] );
			} else if ($actionname == "geteventpeople") {
				/*
				 * Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventpeople->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventPeople = new GetEventPeople ( $message_data, $memreas_tables, $this->sm );
					$result = $GetEventPeople->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "addexistmediatoevent") {
				$AddExistMediaToEvent = new AddExistMediaToEvent ( $message_data, $memreas_tables, $this->sm );
				$result = $AddExistMediaToEvent->exec ();
				
				/*
				 * Cache approach
				 * - write operation
				 * - need to invalidate invalidateEvents
				 */
				
				$data = simplexml_load_string ( $_POST ['xml'] );
				$event_id = $data->addexistmediatoevent->event_id;
				
				$this->redis->invalidateMedia ( $_SESSION ['user_id'], $event_id );
			} else if ($actionname == "getmedialike") {
				/*
				 * Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getmedialike->media_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetMediaLike = new GetMediaLike ( $message_data, $memreas_tables, $this->sm );
					$result = $GetMediaLike->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "checkexistmedia") {
				/*
				 * TODO:
				 * Query
				 * inside
				 * needs
				 * caching...
				 */
				$CheckExistMedia = new CheckExistMedia ( $message_data, $memreas_tables, $this->sm );
				$result = $CheckExistMedia->exec ();
			} else if ($actionname == "listmemreasfriends") {
				/*
				 * Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->listmemreasfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$ListMemreasFriends = new ListMemreasFriends ( $message_data, $memreas_tables, $this->sm );
					$result = $ListMemreasFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getsocialcredentials") {
				/*
				 * TODO:
				 * Cache Approach:
				 * Not necessary
				 * - no sql query
				 */
				$GetSocialCredentials = new GetSocialCredentials ( $message_data, $memreas_tables, $this->sm );
				$result = $GetSocialCredentials->exec ();
			} else if ($actionname == "updatemedia") {
				/*
				 * TODO: Invalidation needed.
				 */
				$UpdateMedia = new UpdateMedia ( $message_data, $memreas_tables, $this->sm );
				$result = $UpdateMedia->exec ();
				
				/*
				 * TODO: Cache approach
				 * - write operation
				 * - need to invalidate invalidateMedia
				 */
				
				// $this->redis->invalidateMedia ( $_SESSION ['user_id'] );
			} else if ($actionname == "mediadevicetracker") {
				/*
				 * TODO: Invalidation needed.
				 */
				$MediaDeviceTracker = new MediaDeviceTracker ( $message_data, $memreas_tables, $this->sm );
				$result = $MediaDeviceTracker->exec ();
				Mlog::addone ( __METHOD__ . __LINE__ . '::action::mediadevicetracker::result', $result );
				/*
				 * TODO: Cache approach
				 * - write operation
				 * - need to invalidate invalidateMedia
				 */
				
				// $this->redis->invalidateMedia ( $_SESSION ['user_id'] );
			} else if ($actionname == "feedback") {
				/*
				 * Cache Approach
				 * - N/a
				 */
				$FeedBack = new FeedBack ( $this->sm );
				$result = $FeedBack->exec ();
			} else if ($actionname == "geteventdetails") {
				/*
				 * Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->geteventdetails->event_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetEventDetails = new GetEventDetails ( $message_data, $memreas_tables, $this->sm );
					$result = $GetEventDetails->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "removeeventmedia") {
				$RemoveEventMedia = new RemoveEventMedia ( $message_data, $memreas_tables, $this->sm );
				$result = $RemoveEventMedia->exec ();
				
				/*
				 * Cache approach
				 * - write operation
				 * - invalidateMedia
				 */
				
				// TODO: invalidate event details media
				// $this->redis->invalidateMedia ( $_SESSION ['user_id'] );
			} else if ($actionname == "removeeventfriend") {
				/*
				 * TODO: Invalidation needed
				 */
				$RemoveEventFriend = new RemoveEventFriend ( $message_data, $memreas_tables, $this->sm );
				$result = $RemoveEventFriend->exec ();
				$data = simplexml_load_string ( $_POST ['xml'] );
				
				/*
				 * Cache approach
				 * - write operation
				 * - invalidate event details friends
				 */
				
				$this->redis->invalidateEvents ( $_SESSION ['user_id'] );
			} else if ($actionname == "removefriends") {
				
				$RemoveFriends = new RemoveFriends ( $message_data, $memreas_tables, $this->sm );
				$result = $RemoveFriends->exec ();
				
				/*
				 * Cache approach
				 * - write operation
				 * - invalidateFriends
				 */
				
				$this->redis->invalidateFriends ( $_SESSION ['user_id'] );
			} else if ($actionname == "getfriends") {
				
				/*
				 * Cache Approach:
				 * Check cache first if not there then
				 * fetch and cache...
				 */
				$data = simplexml_load_string ( $_POST ['xml'] );
				$cache_id = trim ( $data->getfriends->user_id );
				$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
				
				if (! $result || empty ( $result )) {
					$GetFriends = new GetFriends ( $message_data, $memreas_tables, $this->sm );
					$result = $GetFriends->exec ();
					$cache_me = true;
				}
			} else if ($actionname == "getplans") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$GetPlans = new GetPlans ( $message_data, $memreas_tables, $this->sm );
				$result = $GetPlans->exec ();
			} else if ($actionname == "getplansstatic") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$GetPlansStatic = new GetPlansStatic ( $message_data, $memreas_tables, $this->sm );
				$result = $GetPlansStatic->exec ();
			} else if ($actionname == "getorderhistory") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$GetOrderHistory = new GetOrderHistory ( $message_data, $memreas_tables, $this->sm );
				$result = $GetOrderHistory->exec ();
			} else if ($actionname == "getorder") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$GetOrder = new GetOrder ( $message_data, $memreas_tables, $this->sm );
				$result = $GetOrder->exec ();
			} else if ($actionname == "removegroup") {
				$RemoveGroup = new RemoveGroup ( $message_data, $memreas_tables, $this->sm );
				$result = $RemoveGroup->exec ();
				
				/*
				 * TODO:
				 * Cache approach
				 * - write operation
				 */
				
				$this->redis->invalidateGroups ( $_SESSION ['user_id'] );
			} else if ($actionname == "checkevent") {
				/*
				 * TODO: Query inside needs to be cached
				 */
				$CheckEvent = new CheckEvent ( $message_data, $memreas_tables, $this->sm );
				$result = $CheckEvent->exec ();
			} else if ($actionname == "updatepassword") {
				/*
				 * Cache Approach:N/a
				 */
				$UpdatePassword = new UpdatePassword ( $message_data, $memreas_tables, $this->sm );
				$result = $UpdatePassword->exec ();
			} else if ($actionname == "getaccountdetail") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$GetAccountDetail = new GetAccountDetail ( $message_data, $memreas_tables, $this->sm );
				$result = $GetAccountDetail->exec ();
			} else if ($actionname == "getdiskusage") {
				/*
				 * Cache Approach:
				 * N/a for now
				 */
				$getdiskusage = new GetDiskUsage ( $message_data, $memreas_tables, $this->sm );
				$result = $getdiskusage->exec ();
			} else if ($actionname == "refund") {
				/*
				 * Cache Approach: N/a
				 */
				$Refund = new Refund ( $message_data, $memreas_tables, $this->sm );
				$result = $Refund->exec ();
			} else if ($actionname == "listpayees") {
				/*
				 * Cache Approach:N/a
				 */
				$ListPayees = new ListPayees ( $message_data, $memreas_tables, $this->sm );
				$result = $ListPayees->exec ();
			} else if ($actionname == "makepayout") {
				$MakePayout = new MakePayout ( $this->sm );
				$result = $MakePayout->exec ();
			} else if ($actionname == "dcmareportviolation") {
				$dcmaReportViolation = new DcmaReportViolation ( $this->sm );
				$result = $dcmaReportViolation->exec ();
			} else if ($actionname == "dcmacounterclaim") {
				$dcmaCounterClaim = new DcmaCounterClaim ( $this->sm );
				$result = $dcmaCounterClaim->exec ();
			} else if ($actionname == "dcmalist") {
				$dcmaList = new DcmaList ( $this->sm );
				$result = $dcmaList->exec ();
			} else if (strpos ( $actionname, "stripe_" ) !== false) {
				/**
				 * -
				 * Payments should not be cached - will be small portion of usage
				 */
				Mlog::addone ( $cm . __LINE__ . '::$actionname', $actionname );
				$PaymentsProxy = new PaymentsProxy ( $this->sm );
				$cache_me = false;
				$message_data ['ip_address'] = $this->fetchUserIPAddress ();
				$message_data ['user_agent'] = $_SERVER ['HTTP_USER_AGENT'];
				$result = $PaymentsProxy->exec ( $actionname, $message_data );
			}
			
			/*
			 * Successfully retrieved from cache so echo
			 */
			if ($cache_me == false && ! empty ( $result )) {
				// Mlog::addone ( __METHOD__ . __LINE__ . 'Successfully retrieved from cache so echo:OUTPUT', $result );
				echo $result;
			}
			$output = trim ( ob_get_clean () );
			
			/*
			 * TODO - Cache here due to ob_get_clean
			 */
			if ($cache_me && (MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
				$this->redis->setCache ( $actionname . '_' . $cache_id, $output );
				Mlog::addone ( __METHOD__ . __LINE__ . '$this->redis->setCache ( $actionname_$cache_id, $output )::', $actionname . '_' . $cache_id . '::' . $output );
			}
			
			/*
			 * TODO - Invalidate cache in if statements (id is all that is needed)
			 */
			
			if ($invalidate_me && (MemreasConstants::REDIS_SERVER_USE) && (! MemreasConstants::REDIS_SERVER_SESSION_ONLY)) {
				$this->redis->invalidateCache ( $invalidate_action . '_' . $cache_id );
				Mlog::addone ( __METHOD__ . __LINE__ . '$this->redis->invalidateCache ( $invalidate_action_$cache_id )::', $invalidate_action . '_' . $cache_id );
			}
		} // end if (isset ( $actionname ) && ! empty ( $actionname ))
		
		if (! empty ( $callback )) {
			$message_data ['data'] = $output;
			if (isset ( $output ) && isset ( $_SESSION ['x_memreas_chameleon'] )) {
				$message_data ['x_memreas_chameleon'] = $_SESSION ['x_memreas_chameleon'];
			}
			$json_arr = array (
					"data" => $message_data ['data'] 
			);
			$json = json_encode ( $json_arr );
			
			header ( 'Content-Type: application/json' );
			// callback json
			// Mlog::addone ( __METHOD__ . __LINE__ . "response for $actionname with callback--->", $callback . "(" . $json . ")" );
			echo $callback . "(" . $json . ")";
		} else {
			// callback is empty
			Mlog::addone ( __METHOD__ . __LINE__ . '::output:', $output );
			Mlog::addone ( __CLASS__ . __METHOD__, __LINE__ );
			
			if (isset ( $output ) && isset ( $_SESSION ['x_memreas_chameleon'] )) {
				if ($this->isJson ( $output )) {
					$message_data = json_decode ( $output, true );
					
					Mlog::addone ( $cm . __LINE__ . '', '...' );
					$entry = count ( $_SESSION ['x_memreas_chameleon'] ) - 1;
					Mlog::addone ( $cm . __LINE__ . '', '...' );
					$message_data ['x_memreas_chameleon'] = $_SESSION ['x_memreas_chameleon'] [$entry];
					Mlog::addone ( $cm . __LINE__ . '', '...' );
					Mlog::addone ( $cm . __LINE__ . 'set x_memreas_chameleon in $message_data --->', $message_data );
					$output = json_encode ( $message_data );
					Mlog::addone ( $cm . __LINE__ . '', '...' );
				} else {
					$data = simplexml_load_string ( trim ( $output ) );
					if (empty ( $data->x_memreas_chameleon )) {
						// $data->x_memreas_chameleon = $_SESSION ['x_memreas_chameleon'];
						$entry = count ( $_SESSION ['x_memreas_chameleon'] ) - 1;
						$data->addChild ( 'x_memreas_chameleon', $_SESSION ['x_memreas_chameleon'] [$entry] );
						Mlog::addone ( $cm . __LINE__ . 'set x_memreas_chameleon in $data --->', $data );
						$output = $data->asXML ();
						// error_log ( '$xml-->' . $xml );
					}
					Mlog::addone ( $cm . __LINE__ . 'set x_memreas_chameleon in $ouput --->', $output );
				}
			}
			// Mlog::addone ( __METHOD__ . __LINE__ . "response for $actionname without callback--->", $output );
			echo $output;
		}
		
		/**
		 * close session for ajax
		 */
		session_write_close ();
		
		/**
		 * Cache Warming section...
		 */
		if (MemreasConstants::REDIS_SERVER_USE) {
			// error_log ( "Inside Redis warmer @..." . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL
			// );
			
			// Return the status code here so this process continues and the user receives
			// response
			try {
				// http_response_code ( 200 );
				// header ( 'Connection: close' );
				// header ( 'Content-Length: ' . ob_get_length () );
				ob_end_flush (); // Strange behaviour, will not work
				flush (); // Unless both are called !
			} catch ( Exception $e ) {
				// Do nothing...
			}
			
			if (! $this->redis->hasSet ( '@person' )) {
				// Now continue processing and warm the cache for @person
				// $registration = new Registration ( $message_data, $memreas_tables,
				$this->redis->warmPersonSet ();
			}
			if ((! $this->redis->hasSet ( '!memreas' )) && (isset ( $_SESSION ['user_id'] ))) {
				// Now continue processing and warm the cache for !memreas
				// $registration = new Registration ( $message_data, $memreas_tables,
				$this->redis->warmMemreasSet ( $_SESSION ['user_id'] );
			}
			if ((! $this->redis->hasSet ( '#hashtag' )) && (isset ( $_SESSION ['user_id'] ))) {
				// warm the cache for #hashtag
				$user_id = $_SESSION ['user_id'];
				$this->redis->warmHashTagSet ( $_SESSION ['user_id'] );
			}
		}
		// Need to exit here to avoid ZF2 framework view.
		exit ();
	}
	// end indexcontroller...
	public function requiresSecureAction($actionname) {
		/*
		 * Check action to see if session is needed...
		 */
		$public = array (
				'login',
				'registration',
				'forgotpassword',
				'checkusername',
				// 'chkuname',
				'changepassword',
				'verifyemailaddress',
				'ws_tester',
				'stripe_ws_tester',
				'clearlog',
				'showlog',
				'stripe_activeCredit',
				'dcmareportviolation',
				/* For stripe
				'getplans',
				'getplansstatic',
				'getorderhistory',
				'getorder',
				'getaccountdetail',
				'refund',
				'listpayees',
				'makepayout',
				'getdiskusage',*/
				
                                        
		);
		if (in_array ( $actionname, $public )) {
			Mlog::addone ( 'Inside else public action in_array actionname ->', $actionname );
			return false;
		}
		return true;
	}
	public function fetchSession($actionname, $requiresExistingSession, $data) {
		/*
		 * Setup Redis and the session save handle
		 */
		$sid_success = 0;
		try {
			/*
			 * Check sid against logged in sid
			 */
			if ($requiresExistingSession) {
				//
				// Check if array if so then convert to object
				//
				if (is_array ( $data )) {
					$data = ( object ) $data;
				}
				//
				// Check data to attributes...
				//
				if (! empty ( $data->sid )) {
					/*
					 * SetId for the mobile devices session and start...
					 */
					$this->sessHandler->startSessionWithSID ( $data->sid );
					if (session_id () == $data->sid) {
						$sid_success = 1;
					}
					// Mlog::addone ( __METHOD__ . __LINE__ . "from startSessionWithSID", $data->sid );
				} else if (! empty ( $data->uid ) || ! empty ( $data->username )) {
					/*
					 * SetId for the web browser session and start... (TESTING...)
					 */
					$this->sessHandler->startSessionWithUID ( $data );
					// Mlog::addone ( __METHOD__ . __LINE__ . "from startSessionWithUID", $actionname );
					return $actionname;
				} else if (! empty ( $data->memreascookie )) {
					
					/*
					 * SetId for the web browser session and start...
					 */
					// Mlog::addone ( __CLASS__ . __METHOD__ . '::$this->sessHandler->startSessionWithMemreasCookie --->actionname----> ', $actionname );
					Mlog::addone ( __CLASS__ . __METHOD__ . '::$this->sessHandler->startSessionWithMemreasCookie --->(string) $data->memreascookie----> ', ( string ) $data->memreascookie );
					Mlog::addone ( __CLASS__ . __METHOD__ . '::$this->sessHandler->startSessionWithMemreasCookie --->(string) $data->x_memreas_chameleon ----->', ( string ) $data->x_memreas_chameleon );
					$result = $this->sessHandler->startSessionWithMemreasCookie ( ( string ) $data->memreascookie, ( string ) $data->x_memreas_chameleon, $actionname );
					if ($_SESSION ['memreascookie'] == $data->memreascookie) {
						$sid_success = 1;
					}
					// Mlog::addone ( __METHOD__ . __LINE__ . "from startSessionWithMemreasCookie", $actionname );
				}
				
				if (! $sid_success) {
					error_log ( 'SID IS NOT SET !!!!!' . PHP_EOL );
					session_destroy ();
					return 'notlogin';
				}
			} // end if ($requiresExistingSession)
			
			/**
			 * Fetch user ip
			 */
			Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::$data---->',$data);
			if (empty ( $data->clientIPAddress )) {
				Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::$data->clientIPAddress---->','is empty...');
				
				$currentIPAddress = $this->fetchUserIPAddress ();
				Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::$currentIPAddress', $currentIPAddress);
				if (! empty ( $_SESSION ['ipAddress'] ) && ($currentIPAddress != $_SESSION ['ipAddress'])) {
					Mlog::addone ( '$_SESSION [ipAddress]', $_SESSION ['ipAddress'] );
					Mlog::addone ( '$currentIPAddress', $currentIPAddress );
					Mlog::addone ( __CLASS__ . __METHOD__, "ERROR::User IP Address has changed - logging user out!" );
					Mlog::addone ( '_SESSION vars after sid_success', $_SESSION );
					return 'notlogin';
				}
				Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::','..');
				$_SESSION ['user'] ['HTTP_USER_AGENT'] = "";
				Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::','..');
				if (! empty ( $_SERVER ['HTTP_USER_AGENT'] )) {
				Mlog::addone ( __CLASS__.__METHOD__.__LINE__.'::','..');
					$_SESSION ['user'] ['HTTP_USER_AGENT'] = $_SERVER ['HTTP_USER_AGENT'];
				}
			}
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
		Mlog::addone ( '$_SERVER [REMOTE_ADDR]', $_SERVER ['REMOTE_ADDR'] );
		Mlog::addone ( '$_SERVER [HTTP_X_FORWARDED_FOR]', $_SERVER ['HTTP_X_FORWARDED_FOR'] );
		if (! empty ( $_SERVER ['HTTP_X_FORWARDED_FOR'] )) {
			$ipAddress = $_SERVER ['HTTP_X_FORWARDED_FOR'];
		} else {
			$ipAddress = $_SERVER ['REMOTE_ADDR'];
		}
		// error_log ( 'ip is ' . $ipAddress );
		
		return $ipAddress;
	}
}
// end class IndexController

