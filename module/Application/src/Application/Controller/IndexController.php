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
use Zend\Session\Container;
use Application\Model;
use Application\Form;
use Zend\Mail\Message;
use Zend\Mail\Transport\Sendmail as SendmailTransport;
use Guzzle\Http\Client;
use Application\Model\MemreasConstants;
use Application\memreas\Login;
use Application\memreas\Registration;
use Application\memreas\AddComment;
use Application\memreas\AddMediaEvent;
use Application\memreas\ChkUname;
use Application\memreas\LikeMedia;
use Application\memreas\MediaInappropriate;
use Application\memreas\MemreasCache;
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
use Application\memreas\AWSMemreasCache;
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



class IndexController extends AbstractActionController {

	// protected $url = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index.php";
	// protected $url = "http://192.168.1.9/eventapp_zend2.1/webservices/index_json.php";
	protected $url = "http://test";
	protected $user_id;
	protected $storage;
	protected $authservice;
	protected $userTable;
	protected $eventTable;
	protected $mediaTable;
	protected $eventmediaTable;
	protected $friendmediaTable;
	protected $elasticache;
	protected $aws;
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
				// 'cache_me' => true,
				'xml' => $xml
		) );
		$response = $request->send ();
		return $data = $response->getBody ( true );
	}
	public function indexAction() {
		error_log ( "Inside indexAction" . PHP_EOL );
		// $path = $this->security("application/index/ws_tester.phtml");

		$path = "application/index/ws_tester.phtml";
		$output = '';

		$callback = isset ( $_REQUEST ['callback'] ) ? $_REQUEST ['callback'] : '';

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

		if (isset ( $actionname ) && ! empty ( $actionname )) {
			// Fetch the elasticache handle
			error_log ( "Need to create MemreasCache..." . PHP_EOL );
			// $this->aws = new AWSManagerSender($this->service_locator);
             $this->elasticache = new AWSMemreasCache();
             $update_elasticache_flag = false;
             $cache_me   = false;
             $invalidate = false;
             $invalidate_me = false;

			// Debugging
			// $this->elasticache->set('hello', 'world', 600);
			// End Debugging

			// Capture the echo from the includes in case we need to convert back to json
			ob_start ();
			$memreas_tables = new MemreasTables ( $this->getServiceLocator () );
			if ($actionname == "login") {
				$login = new Login ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $login->exec ();
			} else if ($actionname == "registration") {
				$registration = new Registration ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $registration->exec ();


                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim($data->registration->username );
                //first letter of name
                $uid = $uid[0];
                $actionname= '@';
                if ($registration->status = 'Success') {
                    $this->elasticache->setCache("@person",$registration->userIndex);
                }


			} else if ($actionname == "addcomments") {
				$addcomment = new AddComment ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addcomment->exec ();
			} else if ($actionname == "checkusername" || $actionname == "chkuname") {
				$chkuname = new ChkUname ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $chkuname->exec ();
			} else if ($actionname == "addmediaevent") {
				$addmediaevent = new AddMediaEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addmediaevent->exec ();
                /*
                 * TODO: Since we just added media we need to invalidate the cache for list all media
                 */
                $data = simplexml_load_string ( $_POST ['xml'] );
                if (isset ( $data->addmediaevent->user_id ) ) {
                    $invalidate_action = "listallmedia";
                    $uid = $data->addmediaevent->user_id;
                    $invalidate_me = true;
                    $this->elasticache->invalidateCache("viewevents_".$uid);

                }
			} else if ($actionname == "likemedia") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ($data->likemedia->user_id);
                $result = $this->elasticache->getCache($actionname,$uid);
                if(!$result){
				$likemedia = new LikeMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $likemedia->exec ();
                    $cache_me = true;


                }




			} else if ($actionname == "mediainappropriate") {
				$mediainappropriate = new MediaInappropriate ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $mediainappropriate->exec ();
                $data   = simplexml_load_string ($_POST ['xml']);
                $uid    = trim($data->mediainappropriate->user_id);
                if (!empty($uid)) {
                    $invalidate_action = "listallmedia";
                    $invalidate_me = true;
                }
			} else if ($actionname == "countlistallmedia") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->countlistallmedia->user_id );
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$countlistallmedia = new CountListallmedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $countlistallmedia->exec ();
                    $cache_me = true;
                }

			} else if ($actionname == "listgroup") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->listgroup->user_id );
                $result = $this->elasticache->getCache($actionname,$uid);

                if(!$result){
				$listgroup = new ListGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
                    $result    = $listgroup->exec ();
                    $cache_me  = true;
                }

			} else if ($actionname == "deletephoto") {
				$deletephoto = new DeletePhoto ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $deletephoto->exec ();

                $uid =$result->user_id;
                if (!empty($uid)) {
                    $invalidate_action = "listallmedia";
                    $invalidate_me = true;

                }


			} else if ($actionname == "listphotos") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->listphotos->userid );
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$listphotos = new ListPhotos ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $listphotos->exec ();
                    $cache_me = true;
                }





			} else if ($actionname == "forgotpassword") {
				$forgotpassword = new ForgotPassword ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $forgotpassword->exec ();
			} else if ($actionname == "download") {
				$download = new Download ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $download->exec ();
			} else if ($actionname == "viewallfriends") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim($data->viewallfriends->user_id);
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result)
                {
				$viewallfriends = new ViewAllfriends ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $viewallfriends->exec ();
                    $cache_me = true;
                }

			} else if ($actionname == "creategroup") {
				$creategroup = new CreateGroup ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $creategroup->exec ();

                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ($data->creategroup->user_id);

                if (!empty($uid)) {
                    $invalidate_action = "listgroup";
                    $invalidate_me = true;
                }


			} else if ($actionname == "listallmedia") {
                /*
                 * TODO: Check cache first if not there then fetch and cache...
                */
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = $data->listallmedia->user_id;
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$listallmedia = new ListAllmedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $listallmedia->exec ();
                    $cache_me = true;
                }
			} else if ($actionname == "countviewevent") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->countviewevent->user_id );
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$countviewevent = new CountViewevent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $countviewevent->exec ();
                    $cache_me = true;
                }



			} else if ($actionname == "editevent") {
				$editevent = new EditEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $editevent->exec ();
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim( $data->editevent->event_id);
                if (!empty($uid)) {
                    $invalidate_action = "viewevents";
                    $data->addmediaevent->user_id;
                    $invalidate_me = true;

                }


			} else if ($actionname == "addevent") {
				$addevent = new AddEvent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addevent->exec ();

                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim($data->addevent->user_id);
                if (!empty($uid)) {
                    $invalidate_action = "viewevents";

                    $invalidate_me = true;

                }

			} else if ($actionname == "viewevents") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->viewevent->user_id );
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$viewevents = new ViewEvents ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $viewevents->exec ();
                    $cache_me = true;
                }

			} else if ($actionname == "addfriendtoevent") {
				$addfriendtoevent = new AddFriendtoevent ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addfriendtoevent->exec ();
                $uid =trim($data->addfriendtoevent->user_id);
                $data = simplexml_load_string ( $_POST ['xml'] );
                if (!empty($uid)) {
                    $invalidate_action = "viewevents";
                    $invalidate_me = true;

                }

			} else if ($actionname == "viewmediadetails") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $mid = trim ( $data->viewmediadetails->media_id );

                $result = $this->elasticache->getCache($actionname,$mid);

                if (!$result) {
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
				$uploadmedia = new UploadMedia ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $uploadmedia->exec ();
			} else if ($actionname == "uploadadvertisement") {
				$uploadadvertisement = new UploadAdvertisement ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $uploadadvertisement->exec ();
			} else if ($actionname == "addNotification") {
				$addNotification = new AddNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $addNotification->exec ();
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim( $data->addNotification->user_id );
                if (!empty($uid)) {
                    $invalidate_action = "listnotification";

                    $invalidate_me = true;

                }





			} else if ($actionname == "changepassword") {
				$changepassword = new ChangePassword ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $changepassword->exec ();
			} else if ($actionname == "listnotification") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ( $data->listnotification->user_id );
                $result = $this->elasticache->getCache($actionname,$uid);

                if (!$result) {
				$listnotification = new ListNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
                    $result = $listnotification->exec();
                    $cache_me = true;
                }

			} else if ($actionname == "updatenotification") {
				$updatenotification = new UpdateNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $updatenotification->exec ();
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = $updatenotification->user_id;
                if (!empty($uid)) {
                    $invalidate_action = "listnotification";
                    $invalidate_me = true;
                }


			} else if ($actionname == "findtag") {
                $data = simplexml_load_string ( $_POST ['xml'] );
                $tag = (trim ( $data->findtag->tag ));
                $a = $tag[0];
                $search = substr($tag, 1) ;

                switch($a){
                    case '@':

                        $mc = $this->elasticache->getCache('@person');
                        if(!$mc){
                            $registration = new registration( $message_data, $memreas_tables, $this->getServiceLocator () );
                            $registration->createUserCache();
                            $mc = $registration->userIndex;
                            $this->elasticache->setCache("@person",$mc );
                        }
                        $search_result = array();
                        foreach ($mc as &$pr){
                            if(strpos($pr['username'],$search)!==false){
                                $pr['username'] = '@'.$pr['username'];
                                $search_result[] = $pr;
                                
                            }
                        }
                        
                        $result['count']= count($search_result);
                        $result['search']= $search_result;

                        //$result =  preg_grep("/$search/", $mc);
                        //echo '<pre>';print_r($result);
                        
                        echo json_encode($result);

                    break;
                    case '!':
                        $mc = $this->elasticache->getCache('@event');
                        if(!$mc){
                            $AddEvent = new AddEvent( $message_data, $memreas_tables, $this->getServiceLocator () );
                            $AddEvent->createEventCache();
                            $mc = $AddEvent->eventIndex;
                            $this->elasticache->setCache("@event",$mc );
                        }
                        $result =  preg_grep("/$search/", $mc);
                        echo json_encode(array_values($result));
                    break;
                    default:
				$findtag = new FindTag ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $findtag->exec ();
                        $result =  preg_grep("/$search/", $mc);
                        echo json_encode(array_values($result));
                    break;
                }

            }
            else if ($actionname == "signedurl") {
				$signedurl = new MemreasSignedURL ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $signedurl->exec ();
            }
            else if ($actionname == "showlog") {
				echo '<pre>' . file_get_contents ( getcwd () . '/php_errors.log' );
				exit ();
            }
            else if ($actionname == "doquery") {
				$em = $this->getServiceLocator ()->get ( 'doctrine.entitymanager.orm_default' );
				$x = $em->createQuery ( $_GET ['sql'] )->getResult ();
				echo '<pre>';
				print_r ( $x );
				exit ();
			} else if ($actionname == "logout") {
				$logout = new LogOut ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
			} else if ($actionname == "clearallnotification") {
				$logout = new ClearAllNotification ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
                $data = simplexml_load_string ( $_POST ['xml'] );
                $uid = trim ($data->clearallnotification->user_id);
                if (!empty($uid)) {
                    $invalidate_action = "listnotification";
                    $invalidate_me = true;

                }



			} else if ($actionname == "getsession") {
				$logout = new GetSession ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
			} else if ($actionname == "registerdevice") {
				$logout = new RegisterDevice ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $logout->exec ();
			} else if ($actionname == "listcomments") {
				$listcomments = new ListComments ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $listcomments->exec ();
			} else if ($actionname == "verifyemail") {
				$aws_manager = new AWSManagerSender ( $this->service_locator );
				$client = $aws_manager->ses ();
				$client->verifyEmailAddress ( array (
						'EmailAddress' => $_GET ['email']
				) );
				echo 'Please Cheack email validate you email to recive emails';
			} else if ($actionname == "geteventlocation") {
				$GetEventLocation = new GetEventLocation ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetEventLocation->exec ();
			}else if ($actionname == "geteventcount") {
				$GetEventLocation = new GetEventCount ( $message_data, $memreas_tables, $this->getServiceLocator () );
				$result = $GetEventLocation->exec ();
			}else if ($actionname == "getuserdetails"){
                $GetUserDetails = new GetUserDetails($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $GetUserDetails->exec();
            }
            else if ($actionname == "saveuserdetails"){
                $SaveUserDetails = new SaveUserDetails($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $SaveUserDetails->exec();
            }else if ($actionname == "getusergroups"){
                $GetUserGroups = new GetUserGroups($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $GetUserGroups->exec();
            }else if ($actionname == "getgroupfriends"){
                $GetGroupFriends = new GetGroupFriends($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $GetGroupFriends->exec();
            }else if ($actionname == "addfriendtogroup"){
                $AddFriendToGroup = new AddFriendToGroup($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $AddFriendToGroup->exec();
            }else if ($actionname == "removefriendgroup"){
                $RemoveFriendGroup = new RemoveFriendGroup($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $RemoveFriendGroup->exec();
            }else if ($actionname == "geteventpeople"){
                $GetEventPeople = new GetEventPeople($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $GetEventPeople->exec();
            }else if ($actionname == "addexistmediatoevent"){
                $AddExistMediaToEvent = new AddExistMediaToEvent($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $AddExistMediaToEvent->exec();
            }else if ($actionname == "getmedialike"){
                $GetMediaLike = new GetMediaLike($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $GetMediaLike->exec();
            }else if ($actionname == "checkexistmedia"){
                $CheckExistMedia = new CheckExistMedia($message_data, $memreas_tables, $this->getServiceLocator ());
                $result = $CheckExistMedia->exec();
            }
			$output = ob_get_clean ();
             /*
             * TODO - Cache here
             */
            if ($cache_me && MemreasConstants::ELASTICACHE_SERVER_USE) {

                $this->elasticache->setCache($actionname . '_' . $uid, $output);
            }

            /*
             * TODO - Invalidate cache here
             */
            if ($invalidate_me && MemreasConstants::ELASTICACHE_SERVER_USE) {
                $this->elasticache->invalidateCache($invalidate_action.'_'.$uid);
            }


		}
		// memreas related calls...
		// $memreas = new Memreas();
		// $memreas_tables = new MemreasTables($this->getServiceLocator());
		// $result = $memreas->login($message_data, $memreas_tables, $this->getServiceLocator());
		if (! empty ( $callback )) {
			$message_data ['data'] = $output;
			error_log ( "Final XML ----> " . $message_data ['data'] );

			$json_arr = array (
					"data" => $message_data ['data']
			);
			$json = json_encode ( $json_arr );

			error_log ( "Callback ---> " . $callback . "(" . $json . ")" );
			header ( "Content-type: plain/text" );
			// callback json
			echo $callback . "(" . $json . ")";
			// Need to exit here to avoid ZF2 framework view.

			exit ();
		}
		error_log ( "path ----> " . PHP_EOL );
		if (isset ( $_GET ['view'] ) && empty ( $actionname )) {
			$view = new ViewModel ();
			$view->setTemplate ( $path ); // path to phtml file under view folder
			return $view;
		} else {
			// xml output
			echo $output;
			exit ();
		}
	}
	public function galleryAction() {
		$path = $this->security ( "application/index/gallery.phtml" );

		$action = 'listallmedia';
		$session = new Container ( 'user' );
		$xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet ( 'user_id' ) . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
		$result = $this->fetchXML ( $action, $xml );

		$view = new ViewModel ( array (
				'xml' => $result
		) );
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
		// return new ViewModel();
	}
	public function eventAction() {
		$path = $this->security ( "application/index/event.phtml" );

		$action = 'listallmedia';
		$session = new Container ( 'user' );
		$xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet ( 'user_id' ) . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
		$result = $this->fetchXML ( $action, $xml );

		$view = new ViewModel ( array (
				'xml' => $result
		) );
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
		// return new ViewModel();
	}
	public function shareAction() {
		$path = $this->security ( "application/index/share.phtml" );
		$view = new ViewModel ();
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
	}
	public function queueAction() {
		$path = $this->security ( "application/index/queue.phtml" );
		$view = new ViewModel ();
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
	}
	public function eventGalleryAction() {
		$path = $this->security ( "application/index/event-gallery.phtml" );
		$view = new ViewModel ();
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
	}
	public function memreasMeFriendsAction() {
		$path = $this->security ( "application/index/memreas-me-friends.phtml" );
		$view = new ViewModel ();
		$view->setTemplate ( $path ); // path to phtml file under view folder
		return $view;
	}
	public function loginAction() {
		error_log ( "INSIDE LOGIN ACTION" );
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
	public function security($path) {
		// if already login do nothing
		$session = new Container ( "user" );
		if (! $session->offsetExists ( 'user_id' )) {
			error_log ( "Not there so logout" );
			$this->logoutAction ();
			return "application/index/index.phtml";
		}
		return $path;
		// return $this->redirect()->toRoute('index', array('action' => 'login'));
	}
}

// end class IndexController
