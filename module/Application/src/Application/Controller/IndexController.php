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
use Zend\Db\TableGateway\TableGateway;
use Application\Model\DbTableGatewayOptions;
use Application\memreas\GetDiskUsage;
use Application\memreas\StripeWS\ListPayees;


//Stripe Web Services
use Application\memreas\StripeWS\GetPlans;
use Application\memreas\StripeWS\GetPlansStatic;
use Application\memreas\StripeWS\GetOrderHistory;
use Application\memreas\StripeWS\GetOrder;
use Application\memreas\StripeWS\GetAccountDetail;
use Application\memreas\StripeWS\Refund;
use Application\memreas\StripeWS\MakePayout;

class IndexController extends AbstractActionController {
	
	protected $xml_in;
    protected $url = "http://ws/";
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
				'cache_me' => true,
				'xml' => $xml 
		) );
		$response = $request->send ();
		return $data = $response->getBody ( true );
	}
	public function indexAction() {
//error_log ( "Inside indexAction---> " . date ( 'Y-m-d H:i:s' ) . PHP_EOL );
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
            $actionname = isset($_REQUEST ['action']) ? $_REQUEST ['action'] : '';
            $message_data ['xml'] = '';
        }
                    
        
        /*
         * TODO: SID working within security as of 4-OCT-2014
         */
       $actionname = $this->security($actionname);
                    
//error_log("Inside indexAction---> actionname ---> $actionname ".date ( 'Y-m-d H:i:s' ). PHP_EOL);
//error_log("Inside indexAction---> _POST ['xml'] ---> ".print_r($_POST['xml'],true). PHP_EOL);

 
        if (isset($actionname) && !empty($actionname)) {
            $cache_me = false;
            $cache_id = null;
            $invalidate = false;
            $invalidate_me = false;

            // Capture the echo from the includes in case we need to convert back to json
            ob_start();
            
            if (isset($_POST ['xml']) && !empty($_POST ['xml'])) {
				error_log("Input data as xml ----> ".$_POST ['xml'].PHP_EOL);
            }
            
                      
            $memreas_tables = new MemreasTables($this->getServiceLocator());

            if($actionname == 'notlogin'){
                $result = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
                $result .= "<xml><error>Please Login </error></xml>";
                
                /*
                 * Cache approach - N/a 
                 */
        
            } else if ($actionname == "login") {
                $login = new Login($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $login->exec();

                /*
           
                 * Cache approach - cached in security 
                 */
                
            } else if ($actionname == "registration") {
error_log("f registration ... data ---> ".json_encode($message_data).PHP_EOL);
            	$registration = new Registration($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $registration->exec();

                $data = simplexml_load_string($_POST ['xml']);
                $uid = trim($data->registration->username);
                
                /*
                 * Cache approach - store in @person search index
                 */
                //first letter of name
                $uid = $uid[0];
                $actionname = '@';
                if ($registration->status == 'Success') {
                    $this->elasticache->setCache("@person", $registration->userIndex);
                }
            } else if ($actionname == "addcomments") {
                $addcomment = new AddComment($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $addcomment->exec();

                /*
                 * Cache approach - Write Operation - Invalidate listcomment here
                 */
                //$data = simplexml_load_string($_POST ['xml']);
                //if (isset($data->addcomment->event_id)) {
                //	//Invalidate existing cache
                //	$this->elasticache->invalidateCache("listcomments_" . $data->addcomment->event_id);
                //}
                
                
            } else if ($actionname == "verifyemailaddress") {
                $verifyemailaddress = new VerifyEmailAddress($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $verifyemailaddress->exec();
                if ($result) {
                	$redirect = MemreasConstants::WEB_URL . "index?email_verified=1";
                	$this->redirect()->toUrl($redirect);
                	return false;
                } else {
                	$redirect = MemreasConstants::WEB_URL . "index?email_verified=0";
                	$this->redirect()->toUrl($redirect);
                	return false;
                }
                /*
                 * Cache approach - N/a
                 */
            } else if ($actionname == "checkusername" || $actionname == "chkuname") {
                $chkuname = new ChkUname($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $chkuname->exec();
                

                /*
                 * Cache approach - read operation - pass for now
                */
            } else if ($actionname == "addmediaevent") {
                $addmediaevent = new AddMediaEvent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $addmediaevent->exec();

                /*
                 * Cache approach - Write Operation - Invalidate existing cache here
                 */
                $data = simplexml_load_string($_POST ['xml']);
                $this->elasticache->invalidateMedia($data->addmediaevent->user_id, $data->addmediaevent->event_id, $data->addmediaevent->media_id);
            } else if ($actionname == "likemedia") {
                $data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->likemedia->user_id);
                /*
                 * Cache approach - write operation - pass for now
                 */
                
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);
                if (!$result || empty($result)) {
                    $likemedia = new LikeMedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $likemedia->exec();
                    $cache_me = true;
                }
                
            } else if ($actionname == "mediainappropriate") {
                $mediainappropriate = new MediaInappropriate($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $mediainappropriate->exec();
                /*
                 * Cache approach - Write Operation - Invalidate existing cache here
                 */
                $this->elasticache->invalidateMedia($data->mediainappropriate->user_id, $data->mediainappropriate->event_id);
            } else if ($actionname == "countlistallmedia") {
                
                /*
                 * Cache approach - read operation - cache
                 */
                $data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->countlistallmedia->user_id);
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                    $countlistallmedia = new CountListallmedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $countlistallmedia->exec();
                    $cache_me = true;
                }
                
            } else if ($actionname == "listgroup") {
                /*
                 * Cache approach - read operation - cache
                 */
            	$data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->listgroup->user_id);
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result) {
                    $listgroup = new ListGroup($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listgroup->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "deletephoto") {
                $deletephoto = new DeletePhoto($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $deletephoto->exec();
                
                /*
                 * TODO: Cache approach - Write Operation - Invalidate existing cache here
                 *  -- need user_id or event_id --- this is based on media_id
                 */
                
                //$session = new Container("user");
                //$this->elasticache->invalidateCache("listallmedia_" . $session->user_id);
               	//$this->elasticache->invalidateCache("viewevents_" . $session->user_id);
            } else if ($actionname == "listphotos") {
                /*
                 * Cache approach - read operation - cache
                 */
            	$data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->listphotos->userid);
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result) {
                    $listphotos = new ListPhotos($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listphotos->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "forgotpassword") {
                $forgotpassword = new ForgotPassword($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $forgotpassword->exec();
                /*
                 * Cache approach - N/a
                 */
            } else if ($actionname == "download") {
                $download = new Download($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $download->exec();
                /*
                 * Cache approach - N/a
                 */
            } else if ($actionname == "viewallfriends") {
                /*
                 * Cache approach - read operation - cache
                 */
            	$data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->viewallfriends->user_id);
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                    $viewallfriends = new ViewAllfriends($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $viewallfriends->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "creategroup") {
                $creategroup = new CreateGroup($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $creategroup->exec();

                $data = simplexml_load_string($_POST ['xml']);
                $uid = trim($data->creategroup->user_id);

                /*
                 * Cache approach - write operation - invalidate listgroup
                 */
                $this->elasticache->invalidateGroups($uid);
            } else if ($actionname == "listallmedia") {
                /*
                 * Cache Approach: Check cache first if not there then fetch and cache...
                 *  if event_id then return that cache else user_id
                 */
            	$data = simplexml_load_string($_POST ['xml']);
                if (!empty($data->listallmedia->event_id)){
                	$cache_id =  $data->listallmedia->event_id;
                } else if (!empty($data->listallmedia->user_id)) {
                	$cache_id =  $data->listallmedia->user_id;
                }
                
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);
                if (!$result || empty($result)) {
error_log("Inside listallmedia - no result so pull from db...");                	
                    $listallmedia = new ListAllmedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listallmedia->exec();
                    $cache_me = true;
                } 
            } else if ($actionname == "countviewevent") {
            	/*
                 * Cache Approach: Check cache first if not there then fetch and cache...
                 */
                $data = simplexml_load_string($_POST ['xml']);
                if (!empty($data->countviewevent->is_public_event) && $data->countviewevent->is_public_event){
            		$cache_id =  "public";
            	} else {
            		$cache_id = trim ( $data->countviewevent->user_id );
            	}
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                    $countviewevent = new CountViewevent($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $countviewevent->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "editevent") {
                $editevent = new EditEvent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $editevent->exec();
                $data = simplexml_load_string($_POST ['xml']);
                $event_id = trim($data->editevent->event_id);

                /*
                 * Cache approach - write operation - invalidate events
                */
                $this->elasticache->invalidateEvents($event_id);
            } else if ($actionname == "addevent") {
                $addevent = new AddEvent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $addevent->exec();
                $data = simplexml_load_string($_POST ['xml']);
                
                /*
                 * Cache approach - write operation - hold for now
                 */
                $this->elasticache->invalidateEvents($data->addevent->user_id);
			} else if ($actionname == "viewevents") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	if (!empty($data->viewevent->is_public_event) && $data->viewevent->is_public_event) {
            		$cache_id =  "public";
            	} else if (!empty($data->viewevent->is_friend_event) && $data->viewevent->is_friend_event) {
            		$cache_id = "is_friend_event_".trim ($data->viewevent->user_id);
            	} else if (!empty($data->viewevent->is_my_event) && $data->viewevent->is_my_event) {
            		$cache_id = "is_my_event_" . trim ($data->viewevent->user_id);
            	}
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
                if (!$result || empty($result)) {
                    $viewevents = new ViewEvents($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $viewevents->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "addfriendtoevent") {
                $addfriendtoevent = new AddFriendtoevent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $addfriendtoevent->exec();
                $data = simplexml_load_string($_POST ['xml']);
                $uid = trim($data->addfriendtoevent->user_id);

                /*
                 * Cache approach - write operation - hold for now
                 */
                $this->elasticache->invalidateEvents($uid);
                $this->elasticache->invalidateGroups($uid);
            } else if ($actionname == "viewmediadetails") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
             	 *  if event_id then return then event_id_media_id else cache media_id
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	if (!empty($data->viewmediadetails->event_id) && !empty($data->viewmediadetails->media_id)){
            		$cache_id =  trim($data->viewmediadetails->event_id) ."_". trim($data->viewmediadetails->media_id);
            	} else if (!empty($data->viewmediadetails->media_id)) {
            		$cache_id =  trim($data->viewmediadetails->media_id);
            	}

            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                    $viewmediadetails = new ViewMediadetails($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $viewmediadetails->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "snsProcessMediaPublish") {
                $snsProcessMediaPublish = new snsProcessMediaPublish($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $snsProcessMediaPublish->exec();
            } else if ($actionname == "memreas_tvm") {
                $memreastvm = new Memreastvm($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $memreastvm->exec();
            } else if ($actionname == "uploadmedia") {
            	/*
            	 * TODO: See if this is used - if not remove 
            	 */
                $uploadmedia = new UploadMedia($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $uploadmedia->exec();
            } else if ($actionname == "uploadadvertisement") {
                $uploadadvertisement = new UploadAdvertisement($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $uploadadvertisement->exec();
            } else if ($actionname == "addNotification") {
                $addNotification = new AddNotification($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $addNotification->exec();
                $data = simplexml_load_string($_POST ['xml']);
                $uid = trim($data->addNotification->user_id);

                /*
                 * Cache approach - write operation - invalidate listnotification
                 */
                $this->elasticache->invalidateNotifications($uid);
            } else if ($actionname == "changepassword") {
                $changepassword = new ChangePassword($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $changepassword->exec();
            } else if ($actionname == "listnotification") {
                
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
                $cache_id = trim($data->listnotification->user_id);
                $result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                    $listnotification = new ListNotification($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listnotification->exec();
                    $cache_me = true;
                }
            } else if ($actionname == "updatenotification") {
                $updatenotification = new UpdateNotification($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $updatenotification->exec();
                $data = simplexml_load_string($_POST ['xml']);
                $uid = $updatenotification->user_id;

                /*
                 * Cache approach - write operation - invalidate listnotification
                 */
                $this->elasticache->invalidateNotifications($uid);
			} else if ($actionname == "findtag") {
            	
            	/*
            	 * TODO: How is caching working here?
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
                $tag = (trim($data->findtag->tag));
                $user_id = (trim($data->findtag->user_id));
                $user_id = empty($user_id)?0:$user_id;
                $a = $tag[0];
                $search = substr($tag, 1);

                $page = trim($data->findtag->page);
                if (empty($page)) {
                    $page = 1;
                }

                $limit = trim($data->findtag->limit);
                if (empty($limit)) {
                    $limit = 20;
                }

                $from = ($page - 1) * $limit;
                $rc = 0;

                $search_result =array();
                switch ($a) {
                    case '@':

                        $mc = $this->elasticache->getCache('@person');

                        if (!$mc || empty($mc)) {
                            $registration = new registration($message_data, $memreas_tables, $this->getServiceLocator());
                            $registration->createUserCache();
                            $mc = $registration->userIndex;
                            $this->elasticache->setCache("@person", $mc);
                        }

                        $user_ids = array();
                        foreach ($mc as $uk => $pr) {

                            if (stripos($pr['username'], $search) !== false) {
                                if($uk == $user_id) continue;
                                if ($rc >= $from && $rc < ($from + $limit)) {
                                    $pr['username'] = '@' . $pr['username'];
                                   
                                   
                                    $search_result[] = $pr;
                                    $user_ids[]= $uk;
                                }
                                $rc+=1;
                            }
                        }

                        //filter record
                        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
                        //$user_id = empty($_POST['user_id'])?0:$_POST['user_id'];
                        
                        $qb = $em->createQueryBuilder ();
                        $qb->select ( 'f.friend_id,uf.user_approve' );
                        $qb->from ( 'Application\Entity\Friend', 'f' );
                        $qb->where ( "f.network='memreas'" );

                        $qb->join('Application\Entity\UserFriend', 'uf', 'WITH', 'uf.friend_id = f.friend_id')
                               // ->andwhere("uf.user_approve != '1'")
                                ->andwhere("uf.user_id = '$user_id'")
                                ->andwhere('uf.friend_id IN (:f)')
                                ->setParameter('f', $user_ids );


                        $UserFriends = $qb->getQuery ()->getResult ();
                        $chkUserFriend = array();
                        foreach ($UserFriends as $ufRow) {
                            $chkUserFriend[$ufRow['friend_id']]=$ufRow['user_approve'];
                        }
                        foreach ($search_result as $k => &$srRow) {
                                if(isset($chkUserFriend[$user_ids[$k]])){
                        
                                   $srRow['friend_request_sent']=$chkUserFriend[$user_ids[$k]];
                                    continue;
                                    } 
                        
                                    
                         }
                        $result['totalPage'] =ceil($rc / $limit);
                        $result['count'] = $rc;
                        $result['search'] = $search_result;
                        //hide pagination


                        //echo '<pre>';print_r($result);

                        echo json_encode($result);$result='';

                        break;
                    case '!':
                        $mc = $this->elasticache->getCache('!event');
                        $eventRep = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default')
                                    ->getRepository('Application\Entity\Event');
                        if (!$mc || empty($mc)) {
                            
                            $mc = $eventRep->createEventCache();
                            $this->elasticache->setCache("!event", $mc);
                        }
                        $search_result = array();
                        $event_ids = array();
                         foreach ($mc as $er) {
                            if (stripos($er['name'], $search) === 0) {
                                if ($rc >= $from && $rc < ($from + $limit)) {
                                    $er['name'] = '!' . $er['name'];
                                    $er['created_on'] = Utility::formatDateDiff($er['create_time']);
                                    $event_creator = $eventRep->getUser($er['user_id'],'row');
                                    $er['event_creator_name'] ='@'. $event_creator['username'];
                                    $er['event_creator_pic'] =$event_creator['profile_photo'];

                                    $search_result[] = $er;
                                    $event_ids[]=$er['event_id'];
                                }
                               $rc+=1;
                            }
                        }
                         //filter record !event should show public events and events you've been invited to
                        $em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
                        //$user_id = empty($_POST['user_id'])?0:$_POST['user_id'];
                        $qb = $em->createQueryBuilder ();
                        $qb->select ( 'ef' );
                        $qb->from ( 'Application\Entity\EventFriend', 'ef' );
                        $qb->andWhere('ef.event_id IN (:e)')
                            ->andWhere('ef.friend_id =:f')
                          //  ->andWhere('ef.user_approve != 1')
                            ->setParameter('f', $user_id )
                            ->setParameter('e', $event_ids );
                                 

                        $EventFriends = $qb->getQuery ()->getArrayResult();
                      
                         $chkEventFriend = array();
                        foreach ($EventFriends as $efRow) {
                            $chkEventFriend[$efRow['event_id']]=$efRow['user_approve'];

                        }
                         foreach ($search_result as $k =>  &$srRow) {
                           if(isset($chkEventFriend[$event_ids[$k]])){
                            $srRow['event_request_sent'] =$chkEventFriend[$event_ids[$k]];

                           }
                        }

                        $comments = $eventRep->createDiscoverCache($tag);

                         
                        $result['count']  = $rc;
                        $result['search'] = $search_result;
                        $result['comments'] = empty($comments)?array():$comments;
                        $result['page']   = $page;
                        $result['totalPage'] = ceil($rc / $limit);


                        //$result =  preg_grep("/$search/", $mc);
                        //echo '<pre>';print_r($result);

                        echo json_encode($result);$result='';
                        break;
                    case '#':
                        $mc = $this->elasticache->getCache('#tag');
                        if (!$mc || empty($mc)) {
                            $eventRep = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default')
                                    ->getRepository('Application\Entity\Event');
                            $mc = $eventRep->createDiscoverCache($tag);

                            $this->elasticache->setCache("#tag", $mc);
                        }
                        $search_result = array();


                        foreach ($mc as $k => $er) {
                            if (stripos($er['name'], $search) !== false) {
                                if ($rc >= $from && $rc < ($from + $limit)) {
                                    $er['updated_on'] = Utility::formatDateDiff($er['update_time']);
                                    $search_result[$k] = $er;

                                }
                                 $rc+=1;
                            }
                        }

                        $result['count'] = $rc;
                        $result['search'] = $search_result;
                        $result['page']   = $page;
                        $result['totalPage'] = ceil($rc / $limit);


                        //$result =  preg_grep("/$search/", $mc);
                        //echo '<pre>';print_r($result);

                        echo json_encode($result);$result='';
                        break;
                    default:
                      //  $findtag = new FindTag($message_data, $memreas_tables, $this->getServiceLocator());
                      //  $result = $findtag->exec();
                      //  $result = preg_grep("/$search/", $mc);
                     $result['count'] = 0;
                        $result['search'] = array();

                        $result['totalPage'] = 0;

                        echo json_encode($result);$result='';
                        break;
                }
            } else if ($actionname == "findevent") {
            	/*
            	 * TODO: How is caching working here?
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
                $tag = (trim($data->findevent->tag));
                $search = substr($tag, 1);
                $eventRep = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default')
                        ->getRepository('Application\Entity\Event');
                $mc = $this->elasticache->getCache('!event');
                if (!$mc || empty($mc)) {
                    $mc = $eventRep->createEventCache();
                    $this->elasticache->setCache("!event", $mc);
                }

                $search_result = array();
                $page = trim($data->findevent->page);
                if (empty($page)) {
                    $page = 1;
                }

                $limit = trim($data->findevent->limit);
                if (empty($limit)) {
                    $limit = 20;
                }

                $from = ($page - 1) * $limit;
                $rc = 0;
                foreach ($mc as $eid => $er) {
                    if (stripos($er['name'], $search) === 0) {
                        if ($rc >= $from && $rc < ($from + $limit)) {
                            $er['name'] = '!' . $er['name'];
                            $er['comment_count'] = $eventRep->getCommentCount($eid);
                            $er['like_count'] = $eventRep->getLikeCount($eid);
                            $er['friends'] = $eventRep->getEventFriends($eid);
                            $search_result[] = $er;
                        }

                       $rc+=1;
                    }
                }
                $result['count'] = $rc;
                $result['page'] = $page;
                $result['totalPage'] = ceil($rc / $limit);
                $result['search'] = $search_result;
                //$result =  preg_grep("/$search/", $mc);
                //echo '<pre>';print_r($result);
                echo json_encode($result);$result='';
            } else if ($actionname == "getDiscover") {
            	/*
            	 * TODO: How is caching working here?
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
                $tag = (trim($data->getDiscover->tag));
                $search = $tag;
                $eventRep = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default')
                        ->getRepository('Application\Entity\Event');
                $mc = $this->elasticache->getCache('#tag');
                if (!$mc || empty($mc)) {
                    $mc = $eventRep->createDiscoverCache($tag);
                    $this->elasticache->setCache("#tag", $mc);
                }

                $search_result = array();
                $page = trim($data->getDiscover->page);
                if (empty($page)) {
                    $page = 1;
                }

                $limit = trim($data->getDiscover->limit);
                if (empty($limit)) {
                    $limit = 20;
                }

                $from = ($page - 1) * $limit;
                $rc = 0;
                foreach ($mc as $eid => $er) {

                    if (stripos($er['name'], $search) === 0) {

                        if ($rc >= $from && $rc < ($from + $limit)) {
                            $er['name'] = $er['name'];
                            //$er['comment_count'] = $eventRep->getLikeCount($eid);
                            //$er['like_count'] = $eventRep->getLikeCount($eid);
                            //$er['friends'] = $eventRep->getEventFriends($eid);
                            $search_result[] = $er;
                        }

                        $rc+=1;
                    }
                }
                $result['count'] = $rc;
                $result['page'] = $page;
                $result['totalPage'] = ceil($rc / $limit);
                $result['search'] = $search_result;
                //$result =  preg_grep("/$search/", $mc);
                //echo '<pre>';print_r($result);
                echo json_encode($result);
                $result='';
            } else if ($actionname == "signedurl") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	$signedurl = new MemreasSignedURL($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $signedurl->exec();
            } else if ($actionname == "showlog") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	echo '<pre>' . file_get_contents(getcwd() . '/php_errors.log');
                exit();
            } else if ($actionname == "clearlog") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	unlink(getcwd().'/php_errors.log');
            	error_log("Log has been cleared!");
            	echo '<pre>' . file_get_contents(getcwd() . '/php_errors.log');
                exit();
            } else if ($actionname == "doquery") {
            	/*
            	 * 5-OCT-2014 disabled - security flaw
             	 */
            	       
                //$em = $this->getServiceLocator()->get('doctrine.entitymanager.orm_default');
                //$x = $em->createQuery($_GET ['sql'])->getResult();
                //echo '<pre>';
                //print_r($x);
                exit();
            } else if ($actionname == "logout") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	$logout = new LogOut($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $logout->exec();
            } else if ($actionname == "clearallnotification") {
            	/*
            	 * TODO: Cache Approach: write operation do later
            	 */
            	$logout = new ClearAllNotification($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $logout->exec();
                $data = simplexml_load_string($_POST ['xml']);
                $uid = trim($data->clearallnotification->user_id);
                /*
                 * Cache approach - write operation - invalidate listnotification
                 */
                $this->elasticache->invalidateNotifications($uid);
            } else if ($actionname == "getsession") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim($data->listnotification->user_id);
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	
            	if (!$result || empty($result)) {
            		$getsession = new GetSession($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $getsession->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "registerdevice") {
                $register_device = new RegisterDevice($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $register_device->exec();
            } else if ($actionname == "listcomments") {
            	
                /*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
             	 *  if event_id then return then event_id_media_id else cache media_id
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	if (!empty($data->listcomments->event_id) && !empty($data->listcomments->media_id)){
            		$cache_id =  trim($data->listcomments->event_id) ."_". trim($data->listcomments->media_id);
            	} else if (!empty($data->listcomments->media_id)) {
            		$cache_id =  trim($data->listcomments->media_id);
            	}

            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                	$listcomments = new ListComments($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $listcomments->exec();
                	$cache_me = true;
                }
            } else if ($actionname == "verifyemail") {
                /*
            	 * Cache Approach: N/a
            	 */
            	$aws_manager = new AWSManagerSender($this->service_locator);
                $client = $aws_manager->ses();
                $client->verifyEmailAddress(array(
                    'EmailAddress' => $_GET ['email']
                ));
                echo 'Please Cheack email validate you email to receive emails';
            } else if ($actionname == "geteventlocation") {
            	
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->geteventlocation->event_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
            	if (!$result || empty($result)) {
                	$GetEventLocation = new GetEventLocation($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetEventLocation->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "geteventcount") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->geteventcount->event_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	
            	if (!$result || empty($result)) {
            		$GetEventLocation = new GetEventCount($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetEventLocation->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "getuserdetails") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->getuserdetails->user_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
            	if (!$result || empty($result)) {
                	$GetUserDetails = new GetUserDetails($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetUserDetails->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "saveuserdetails") {
            	/*
            	 * TODO: Invalidation needed
            	 */
                $SaveUserDetails = new SaveUserDetails($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $SaveUserDetails->exec();
                $data = simplexml_load_string($_POST ['xml']);
                
                /*
                 * Cache approach - write operation - invalidate listnotification
                 */
                $this->elasticache->invalidateUser($data->saveuserdetails->user_id);
            } else if ($actionname == "getusergroups") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->getusergroups->user_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	
            	if (!$result || empty($result)) {
                	$GetUserGroups = new GetUserGroups($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetUserGroups->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "getgroupfriends") {
                /*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
             	 *  if group_id then return then network_group_id else network
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$group_id = trim ( $data->getgroupfriends->group_id );
            	$network = trim ($data->getgroupfriends->network);
            	$cache_id =  $group_id;

            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);

                if (!$result || empty($result)) {
                	$GetGroupFriends = new GetGroupFriends($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetGroupFriends->exec();
                	$cache_me = true;
                }
            } else if ($actionname == "addfriendtogroup") {
            	/*
            	 * TODO: Invalidation needed
            	 */
                $AddFriendToGroup = new AddFriendToGroup($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $AddFriendToGroup->exec();
                $data = simplexml_load_string($_POST ['xml']);
                
                /*
                 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
                 */
                $session = new Container("user");
                $this->elasticache->invalidateGroups($session->offsetGet('user_id'));
                                
            } else if ($actionname == "removefriendgroup") {
            	$RemoveFriendGroup = new RemoveFriendGroup($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $RemoveFriendGroup->exec();
                
                /*
                 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
                 */
                $session = new Container("user");
                $this->elasticache->invalidateGroups($session->offsetGet('user_id'));
            } else if ($actionname == "geteventpeople") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->geteventpeople->event_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
            	if (!$result || empty($result)) {
            		$GetEventPeople = new GetEventPeople($message_data, $memreas_tables, $this->getServiceLocator());
            		$result = $GetEventPeople->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "addexistmediatoevent") {
                $AddExistMediaToEvent = new AddExistMediaToEvent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $AddExistMediaToEvent->exec();
                
                /*
                 * Cache approach - write operation - need to invalidate invalidateEvents 
                 */
                $session = new Container("user");
                $data = simplexml_load_string($_POST ['xml']);
                $event_id = $data->addexistmediatoevent->event_id;
                $this->elasticache->invalidateMedia($session->offsetGet('user_id'), $event_id);
                                
            } else if ($actionname == "getmedialike") {
                /*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->getmedialike->media_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
            	if (!$result || empty($result)) {
            		$GetMediaLike = new GetMediaLike($message_data, $memreas_tables, $this->getServiceLocator());
            		$result = $GetMediaLike->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "checkexistmedia") {
            	/*
            	 * TODO: Query inside needs caching...
            	 */
                $CheckExistMedia = new CheckExistMedia($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $CheckExistMedia->exec();
            } else if ($actionname == "listmemreasfriends") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->listmemreasfriends->user_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	
            	if (!$result || empty($result)) {
            		$ListMemreasFriends = new ListMemreasFriends($message_data, $memreas_tables, $this->getServiceLocator());
            		$result = $ListMemreasFriends->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "getsocialcredentials") {
            	/*
            	 * TODO: Cache Approach: Not necessary - no sql query
            	 */
                $GetSocialCredentials = new GetSocialCredentials($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetSocialCredentials->exec();
            } else if ($actionname == "updatemedia") {
            	/*
            	 * TODO: Invalidation needed.
            	 */
                $UpdateMedia = new UpdateMedia($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $UpdateMedia->exec();
                
                /*
                 * TODO: Cache approach - write operation - need to invalidate invalidateMedia
                */
                $session = new Container("user");
                $this->elasticache->invalidateMedia($session->offsetGet('user_id'));
                
            } else if ($actionname == "feedback") {
            	/*
            	 * Cache Approach - N/a
            	 */
            	$FeedBack = new FeedBack($this->getServiceLocator());
                $result = $FeedBack->exec();
            }else if ($actionname == "geteventdetails") {
            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->geteventdetails->event_id );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	 
            	if (!$result || empty($result)) {
            		$GetEventDetails = new GetEventDetails($message_data, $memreas_tables, $this->getServiceLocator());
            		$result = $GetEventDetails->exec();
            		$cache_me = true;
            	}
            }else if ($actionname == "removeeventmedia") {
                $RemoveEventMedia = new RemoveEventMedia($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $RemoveEventMedia->exec();

                /*
                 * Cache approach - write operation - invalidateMedia
                 */
                $session = new Container("user");
                $this->elasticache->invalidateMedia($session->offsetGet('user_id'));
                
            }else if ($actionname == "removeeventfriend") {
            	/*
            	 * TODO: Invalidation needed
            	 */
            	$RemoveEventFriend = new RemoveEventFriend($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $RemoveEventFriend->exec();
                $data = simplexml_load_string($_POST ['xml']);
                
                /*
                 * Cache approach - write operation - invalidateMedia
                 */
                $session = new Container("user");
                $this->elasticache->invalidateEventFriends($data->removeeventfriend->event_id, $session->offsetGet('user_id'));
                
            } else if ($actionname == "removefriends") {

            	$RemoveFriends = new RemoveFriends($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $RemoveFriends->exec();

                /*
                 * Cache approach - write operation - invalidateFriends
                 */
                $session = new Container("user");
                $this->elasticache->invalidateFriends($session->offsetGet('user_id'));
                
            } else if ($actionname == "getfriends") {

            	/*
            	 * Cache Approach: Check cache first if not there then fetch and cache...
            	 */
            	$data = simplexml_load_string($_POST ['xml']);
            	$cache_id = trim ( $data->getfriends->user_id  );
            	$result = $this->elasticache->getCache($actionname.'_'.$cache_id);
            	
            	if (!$result || empty($result)) {
            		$GetFriends = new GetFriends($message_data, $memreas_tables, $this->getServiceLocator());
                	$result = $GetFriends->exec();
            		$cache_me = true;
            	}
            } else if ($actionname == "getplans") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$GetPlans = new GetPlans($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetPlans->exec();
            } else if ($actionname == "getplansstatic") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$GetPlansStatic = new GetPlansStatic($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetPlansStatic->exec();
            }else if ($actionname == "getorderhistory") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$GetOrderHistory = new GetOrderHistory($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetOrderHistory->exec();
            }else if ($actionname == "getorder") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$GetOrder = new GetOrder($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetOrder->exec();
            }
            else if ($actionname == "removegroup") {
            	$RemoveGroup = new RemoveGroup($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $RemoveGroup->exec();
                
                /*
                 * TODO: Cache approach - write operation - need to invalidate listgroup but dont have user_id
                */
                $session = new Container("user");
                $this->elasticache->invalidateGroups($session->offsetGet('user_id'));
                
            }
            else if ($actionname == "checkevent") {
            	/*
            	 * TODO: Query inside needs to cached
            	 */
            	$CheckEvent = new CheckEvent($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $CheckEvent->exec();
            }else if ($actionname == "updatepassword") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	$UpdatePassword = new UpdatePassword($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $UpdatePassword->exec();
            }else if ($actionname == "getaccountdetail") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$GetAccountDetail = new GetAccountDetail($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $GetAccountDetail->exec();
            }else if ($actionname == "getdiskusage") {
            	/*
            	 * Cache Approach: N/a for now
            	 */
            	$getdiskusage = new GetDiskUsage($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $getdiskusage->exec();
            }else if ($actionname == "refund") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	$Refund = new Refund($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $Refund->exec();
            }else if ($actionname == "listpayees") {
            	/*
            	 * Cache Approach: N/a
            	 */
            	$ListPayees = new ListPayees($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $ListPayees->exec();
            }else if ($actionname == "makepayout") {
                $MakePayout = new MakePayout($message_data, $memreas_tables, $this->getServiceLocator());
                $result = $MakePayout->exec();
            }

            /*
             * Successfully retrieved from cache so echo
             */
            if ($cache_me == false && !empty($result)) {
            	echo $result;
            }
            $output = ob_get_clean();

            /*
             * TODO - Cache here due to ob_get_clean
             */
            if ($cache_me && MemreasConstants::ELASTICACHE_SERVER_USE) {
//error_log("Output data as json ----> ".json_encode($output).PHP_EOL);
error_log("about to cache ---> ".$actionname . '_' . $cache_id.PHP_EOL);
//error_log("setCache output ----> ".$output.PHP_EOL);
				$this->elasticache->setCache($actionname . '_' . $cache_id, $output);
            }

            /*
             * TODO - Invalidate cache in if statements (id is all that is needed)
             */
            
            //if ($invalidate_me && MemreasConstants::ELASTICACHE_SERVER_USE) {
			//	error_log("Invalidate Cache_id ----> ".$invalidate_action . '_' . $uid.PHP_EOL);
            //	$this->elasticache->invalidateCache($invalidate_action . '_' . $cache_id);
            //}
        }

        if (!empty($callback)) {
            $message_data ['data'] = $output;

            $json_arr = array(
                "data" => $message_data ['data']
            );
            $json = json_encode($json_arr);

            header("Content-type: plain/text");
            // callback json
            echo $callback . "(" . $json . ")";
            // Need to exit here to avoid ZF2 framework view.
            exit();
        }

        if (isset($_GET ['view']) && empty($actionname)) {
            $view = new ViewModel ();
            $view->setTemplate($path); // path to phtml file under view folder
            return $view;
        } else {
            // xml output
            echo $output;
//error_log("Output data as xml -----> ".$output.PHP_EOL);
//error_log("Exiting indexAction---> $actionname ".date ( 'Y-m-d H:i:s' ). PHP_EOL);
			exit();
        }
    }
    
    public function loginAction() {
        // Fetch the post data
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();
        $username = $postData ['username'];
        $password = $postData ['password'];

        // Original Web Service Call...
        // Setup the URL and action
        $action = 'login';
        $xml = "<xml><login><username>$username</username><password>$password</password></login></xml>";
        $redirect = 'gallery';

        // Guzzle the LoginWeb Service
        $result = $this->fetchXML($action, $xml);
        $data = simplexml_load_string($result);

        // ZF2 Authenticate
        if ($data->loginresponse->status == 'success') {
            $this->setSession($username);
            // Redirect here
            return $this->redirect()->toRoute('index', array(
                        'action' => $redirect
                    ));
        } else {
            return $this->redirect()->toRoute('index', array(
                        'action' => "index"
                    ));
        }
    }

    public function logoutAction() {
        $this->getSessionStorage()->forgetMe();
        $this->getAuthService()->clearIdentity();
        $session = new Container('user');
        $session->getManager()->destroy();

        $view = new ViewModel ();
        $view->setTemplate('application/index/index.phtml'); // path to phtml file under view folder
        return $view;
    }

    public function setSession($username) {
        // Fetch the user's data and store it in the session...
        $user = $this->getUserTable()->getUserByUsername($username);
        unset($user->password);
        unset($user->disable_account);
        unset($user->create_date);
        unset($user->update_time);
        $session = new Container('user');
        $session->offsetSet('user_id', $user->user_id);
        $session->offsetSet('username', $username);
        $session->offsetSet('sid', session_id());
        $session->offsetSet('user', json_encode($user));
    }

    public function registrationAction() {
        // Fetch the post data
        $postData = $this->getRequest()->getPost()->toArray();
        $email = $postData ['email'];
        $username = $postData ['username'];
        $password = $postData ['password'];

        // Setup the URL and action
        $action = 'registration';
        $xml = "<xml><registration><email>$email</email><username>$username</username><password>$password</password></registration></xml>";
        $redirect = 'event';

        // Guzzle the Registration Web Service
        $result = $this->fetchXML($action, $xml);
        $data = simplexml_load_string($result);

        // ZF2 Authenticate
        if ($data->registrationresponse->status == 'success') {
            $this->setSession($username);

            // If there's a profile pic upload it...
            if (isset($_FILES ['file'])) {
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
                $session = new Container('user');
                $request = $guzzle->post($url)->addPostFields(array(
                            'user_id' => $session->offsetGet('user_id'),
                            'filename' => $fileName,
                            'event_id' => "",
                            'device_id' => "",
                            'is_profile_pic' => 1,
                            'is_server_image' => 0
                        ))->addPostFiles(array(
                    'f' => $filetmp_name
                        ));
            }
            $response = $request->send();
            $data = $response->getBody(true);
            $xml = simplexml_load_string($result);

            // ZF2 Authenticate
            error_log("addmediaevent result -----> " . $data);
            if ($xml->addmediaeventresponse->status == 'success') {
                // Do nothing even if it fails...
            }

            // Redirect here
            return $this->redirect()->toRoute('index', array(
                        'action' => $redirect
                    ));
        } else {
            return $this->redirect()->toRoute('index', array(
                        'action' => "index"
                    ));
        }
    }

    public function getUserTable() {
        if (!$this->userTable) {
            $sm = $this->getServiceLocator();
            $this->userTable = $sm->get('Application\Model\UserTable');
        }
        return $this->userTable;
    }

    public function getAuthService() {
        if (!$this->authservice) {
            $this->authservice = $this->getServiceLocator()->get('AuthService');
        }

        return $this->authservice;
    }

    public function getSessionStorage() {
        if (!$this->storage) {
            $this->storage = $this->getServiceLocator()->get('application\Model\MyAuthStorage');
        }

        return $this->storage;
    }

    public function security($actionname) {

        /*
         * TODO: This function isn't working properly.  I added in session_id($sid) per docs 
         * but the session variables aren't retained  
         */
        $this->elasticache = new AWSMemreasCache();

//error_log('just set this->elasticache in security...');
                    
        $ipaddress = $this->getServiceLocator()->get ( 'Request' )->getServer ( 'REMOTE_ADDR' );
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else { 
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        }       
//error_log('ip is '.$ipaddress);
		/*
		 * TODO: Cache in 
		 */
        //if(MemreasConstants::ELASTICACHE_SERVER_USE){ 
        //  $saveHandler = new \Application\memreas\ElasticSessionHandler($this->elasticache);
        //}else{
            $gwOpts = new DbTableGatewayOptions ();
            $gwOpts->setDataColumn ( 'data' );
            $gwOpts->setIdColumn ( 'session_id' );
            $gwOpts->setLifetimeColumn ( 'lifetime' );
            $gwOpts->setModifiedColumn ( 'end_date' );
            $gwOpts->setNameColumn ( 'name' );
            $gwOpts->ipaddress = $ipaddress;
            $dbAdapter = $this->getServiceLocator()->get ( MemreasConstants::MEMREASDB );
            $saveHandler = new \Application\Model\DbTableGateway ( new TableGateway ( 'user_session', $dbAdapter ), $gwOpts );

        //}
        $sessionManager = new SessionManager();
        $sessionManager->setSaveHandler($saveHandler);
        Container::setDefaultManager ( $sessionManager );
		$sid='';
        
        if (!empty( $_REQUEST ['sid'] )) {
            $sid =  $_REQUEST ['sid'] ;
error_log("_REQUEST[sid]---> ".$sid.PHP_EOL);            
        } elseif (isset ( $_POST ['xml'] )) {
            $data = simplexml_load_string ( $_POST ['xml'] );
            $sid = trim ( $data->sid );
error_log("data[sid]---> ".$sid.PHP_EOL);            
        }
error_log('sid ->'.$sid);
        if (!empty ( $sid )) {
			$sessionManager->setId ( $sid );
error_log('set sid ->'.$sid);
        }
		$container = new Container ( 'user' );
    	$public= array(
            'login',
            'registration',
            'forgotpassword',
            'checkevent',
        	'checkusername',
        	'changepassword',
            'showlog',
            'clearlog',
            'feedback',
    		//verify email
    		'verifyemailaddress',
            //For stripe
            'getplans',
            'getplansstatic',
            'getorderhistory',
            'getorder',
            'getaccountdetail',
            'refund',
            'listpayees',
            'makepayout'
//            'doquery'
            ,'getdiskusage'
            );
//        $_SESSION ['user'] ['ip'] = $ipaddress;
        //$_SESSION ['user'] ['HTTP_USER_AGENT'] = $_SERVER['HTTP_USER_AGENT'];
         if(in_array($actionname, $public)|| empty($actionname)){
            return $actionname;
        } else {
	    	        $session = new Container("user");
error_log('ws-session-user_id ->'.$session->user_id);
	            if (!$session->offsetExists('user_id')) {
	                return 'notlogin';
	            }
//error_log("user session ---> ".json_encode($session).PHP_EOL);	    	        
	            return $actionname;       
// return $this->redirect()->toRoute('index', array('action' => 'login'));
        }

    }
}
// end class IndexController
