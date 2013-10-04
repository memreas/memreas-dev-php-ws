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
use memreas\Login;
use memreas\Registration;
use memreas\AddComment;
use memreas\AddMediaEvent;
use memreas\ChkUname;
use memreas\LikeMedia;
use memreas\MediaInappropriate;
use memreas\CountListallmedia;
use memreas\ListGroup;
use memreas\DeletePhoto;
use memreas\ListPhotos;
use memreas\ForgotPassword;
use memreas\Download;
use memreas\ViewAllfriends;
use memreas\CreateGroup;
use memreas\ListAllmedia;
use memreas\CountViewevent;
use memreas\EditEvent;
use memreas\AddEvent;
use memreas\ViewEvents;
use memreas\AWSManager;
use memreas\AddFriendtoevent;
use memreas\ViewMediadetails;
use memreas\snsProcessMediaPublish;
use memreas\Memreastvm;
use memreas\UploadMedia;
use memreas\UploadAdvertisement;
use memreas\AddNotification;

use memreas\Memreas;
use memreas\MemreasTables;

class IndexController extends AbstractActionController {

    //protected $url = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/webservices/index.php";
    //protected $url = "http://192.168.1.9/eventapp_zend2.1/webservices/index_json.php";
    protected $url = "http://test";
    protected $user_id;
    protected $storage;
    protected $authservice;
    protected $userTable;
    protected $eventTable;
    protected $mediaTable;
    protected $eventmediaTable;
    protected $friendmediaTable;

    public function xml2array($xmlstring) {
        $xml = simplexml_load_string($xmlstring);
        $json = json_encode($xml);
        $arr = json_decode($json, TRUE);

        return $arr;
    }

    public function array2xml($array, $xml = false) {

        if ($xml === false) {
            $xml = new \SimpleXMLElement('<?xml version=\'1.0\' encoding=\'utf-8\'?><' . key($array) . '/>');
            $array = $array[key($array)];
        }
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                array2xml($value, $xml->addChild($key));
            } else {
                $xml->addChild($key, $value);
            }
        }
        return $xml->asXML();
    }

    public function fetchXML($action, $xml) {
        $guzzle = new Client();

        $request = $guzzle->post(
                $this->url, null, array(
            'action' => $action,
            //'cache_me' => true,
            'xml' => $xml
                )
        );
        $response = $request->send();
        return $data = $response->getBody(true);
    }

    public function indexAction() {

        //$path = $this->security("application/index/ws_tester.phtml");

        $path = "application/index/ws_tester.phtml";
        $output ='';
        
        $callback = isset($_REQUEST['callback'])?$_REQUEST['callback']:'';

        if (isset($_REQUEST['json'])) {
            //Fetch parms
            $json = $_REQUEST['json'];
            $jsonArr = json_decode($json, true);
            $actionname = $jsonArr['action'];
            $codebase = $jsonArr['codebase'];
            $type = $jsonArr['type'];
            $message_data = $jsonArr['json'];

            $_POST['xml'] = $message_data['xml'];
            
        } else{
            $actionname=  isset($_REQUEST['action'])?$_REQUEST['action']:''; 
            $codebase = "new_code";
            $message_data['xml']='';
            
        }

            if ($codebase == "new_code" && !empty($actionname)) {
error_log("IndexController ----> actionname ---> $actionname" . PHP_EOL);
                //Capture the echo from the includes in case we need to convert back to json
                ob_start();
                $memreas_tables = new MemreasTables($this->getServiceLocator());
                if ($actionname == "login") {
                    $login = new Login($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $login->exec();
                } else if ($actionname == "registration") {
                    $registration = new Registration($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $registration->exec();
                } else if ($actionname == "addcomment") {
                    $addcomment = new AddComment($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $addcomment->exec();
                } else if ($actionname == "chkuname") {
                    $chkuname = new ChkUname($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $chkuname->exec();
                } else if ($actionname == "addmediaevent") {
error_log("IndexController ----> about to addmediaevent" . PHP_EOL);
                    $addmediaevent = new AddMediaEvent($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $addmediaevent->exec();
                } else if ($actionname == "likemedia") {
                    $likemedia = new LikeMedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $likemedia->exec();
                } else if ($actionname == "mediainappropriate") {
                    $mediainappropriate = new MediaInappropriate($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $mediainappropriate->exec();
                } else if ($actionname == "countlistallmedia") {
                    $countlistallmedia = new CountListallmedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $countlistallmedia->exec();
                }else if ($actionname == "listgroup") {
                    $listgroup = new ListGroup($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listgroup->exec();
                }
                
                else if ($actionname == "deletephoto") {

                    $deletephoto = new DeletePhoto($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $deletephoto->exec();
                } else if ($actionname == "listphotos") {

                    $listphotos = new ListPhotos($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listphotos->exec();
                }
                else if ($actionname == "forgotpassword") {

                    $forgotpassword = new ForgotPassword($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $forgotpassword->exec();
                }
                else if ($actionname == "download") {

                    $download = new Download($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $download->exec();
                } else if ($actionname == "viewallfriends") {

                    $viewallfriends = new ViewAllfriends($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $viewallfriends->exec();
                } else if ($actionname == "creategroup") {

                    $creategroup = new CreateGroup($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $creategroup->exec();
                } 
                 else if ($actionname == "listallmedia") {

                    $listallmedia = new ListAllmedia($message_data, $memreas_tables, $this->getServiceLocator());
                    $result = $listallmedia->exec();
                }
                
                else if ($actionname == "countviewevent") {

                    $countviewevent = new CountViewevent($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $countviewevent->exec();
                }
                
                else if ($actionname == "editevent") {

                    $editevent = new EditEvent($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $editevent->exec();
                }
                
                  else if ($actionname == "addevent") {

                    $addevent = new AddEvent($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $addevent->exec();
                }

                   else if ($actionname == "viewevents") {

                    $viewevents = new ViewEvents($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $viewevents->exec();
                }
                
                 else if ($actionname == "addfriendtoevent") {
                    

                    $addfriendtoevent = new AddFriendtoevent($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $addfriendtoevent->exec();
                }   
                
                 else if ($actionname == "viewmediadetails") {
                    

                    $viewmediadetails = new ViewMediadetails($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $viewmediadetails->exec();
                }
                else if ($actionname == "snsProcessMediaPublish") {
                    

                    $snsProcessMediaPublish = new snsProcessMediaPublish($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $snsProcessMediaPublish->exec();
                }
                else if ($actionname == "memreas_tvm") {
                    

                    $memreastvm = new Memreastvm($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $memreastvm->exec();
                }
                else if ($actionname == "uploadmedia") {
                    

                    $uploadmedia = new UploadMedia($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $uploadmedia->exec();
                }
                 else if ($actionname == "uploadadvertisement") {
                    

                    $uploadadvertisement = new UploadAdvertisement($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $uploadadvertisement->exec();
                } else if ($actionname == "addNotification") {
                    

                    $addNotification = new AddNotification($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $addNotification->exec();
                }
                else if ($actionname == "changepassword") {
                    

                    $changepassword = new \memreas\ChangePassword($message_data, $memreas_tables, $this->getServiceLocator());
                        $result = $changepassword->exec();
                }

               $output = ob_get_clean();
            } else if ($codebase == "original_code") {
                //Guzzle existing webservice
                $this->url = MemreasConstants::ORIGINAL_URL;
                $output = $this->fetchXML($actionname, $_POST['xml']);
                error_log("result ---> " . $output . PHP_EOL);
            }

            //memreas related calls...
            //$memreas = new Memreas();
            //$memreas_tables = new MemreasTables($this->getServiceLocator());
            //$result = $memreas->login($message_data, $memreas_tables, $this->getServiceLocator());

           

          //
            if(!empty($callback)){
                $message_data['data'] = $output;
                error_log("Final XML ----> " . $message_data['data']);

                $json_arr = array("data" => $message_data['data']);
                $json = json_encode($json_arr);
               
                error_log("Callback ---> " . $callback . "(" . $json . ")");
                 header("Content-type: plain/text");
            //callback json
            echo $callback . "(" . $json . ")";
            //Need to exit here to avoid ZF2 framework view.

            exit;
            }
            
            
        
        if(isset($_GET['view'])&& empty($actionname)) {
            $view = new ViewModel();
            $view->setTemplate($path); // path to phtml file under view folder
            return $view;
        } else{
            //xml output
            echo $output;
            exit;
        }
    
    }
    public function galleryAction() {
        $path = $this->security("application/index/gallery.phtml");

        $action = 'listallmedia';
        $session = new Container('user');
        $xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet('user_id') . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
        $result = $this->fetchXML($action, $xml);

        $view = new ViewModel(array('xml' => $result));
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
        //return new ViewModel();
    }

    public function eventAction() {
        $path = $this->security("application/index/event.phtml");

        $action = 'listallmedia';
        $session = new Container('user');
        $xml = "<xml><listallmedia><event_id></event_id><user_id>" . $session->offsetGet('user_id') . "</user_id><device_id></device_id><limit>10</limit><page>1</page></listallmedia></xml>";
        $result = $this->fetchXML($action, $xml);

        $view = new ViewModel(array('xml' => $result));
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
        //return new ViewModel();
    }

    public function shareAction() {
        $path = $this->security("application/index/share.phtml");
        $view = new ViewModel();
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
    }

    public function queueAction() {
        $path = $this->security("application/index/queue.phtml");
        $view = new ViewModel();
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
    }

    public function eventGalleryAction() {
        $path = $this->security("application/index/event-gallery.phtml");
        $view = new ViewModel();
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
    }

    public function memreasMeFriendsAction() {
        $path = $this->security("application/index/memreas-me-friends.phtml");
        $view = new ViewModel();
        $view->setTemplate($path); // path to phtml file under view folder
        return $view;
    }

    public function loginAction() {
        error_log("INSIDE LOGIN ACTION");
        //Fetch the post data
        $request = $this->getRequest();
        $postData = $request->getPost()->toArray();
        $username = $postData ['username'];
        $password = $postData ['password'];

        //Original Web Service Call...
        //Setup the URL and action
        $action = 'login';
        $xml = "<xml><login><username>$username</username><password>$password</password></login></xml>";
        $redirect = 'gallery';

        //Guzzle the LoginWeb Service		
        $result = $this->fetchXML($action, $xml);
        $data = simplexml_load_string($result);

        //ZF2 Authenticate
        if ($data->loginresponse->status == 'success') {
            $this->setSession($username);
            //Redirect here
            return $this->redirect()->toRoute('index', array('action' => $redirect));
        } else {
            return $this->redirect()->toRoute('index', array('action' => "index"));
        }
    }

    public function logoutAction() {
        $this->getSessionStorage()->forgetMe();
        $this->getAuthService()->clearIdentity();
        $session = new Container('user');
        $session->getManager()->destroy();

        $view = new ViewModel();
        $view->setTemplate('application/index/index.phtml'); // path to phtml file under view folder
        return $view;
    }

    public function setSession($username) {
        //Fetch the user's data and store it in the session...
        $user = $this->getUserTable()->getUserByUsername($username);
        unset($user->password);
        unset($user->disable_account);
        unset($user->create_date);
        unset($user->update_time);
        $session = new Container('user');
        $session->offsetSet('user_id', $user->user_id);
        $session->offsetSet('username', $username);
        $session->offsetSet('user', json_encode($user));
    }

    public function registrationAction() {
        //Fetch the post data
        $postData = $this->getRequest()->getPost()->toArray();
        $email = $postData ['email'];
        $username = $postData ['username'];
        $password = $postData ['password'];

        //Setup the URL and action
        $action = 'registration';
        $xml = "<xml><registration><email>$email</email><username>$username</username><password>$password</password></registration></xml>";
        $redirect = 'event';

        //Guzzle the Registration Web Service		
        $result = $this->fetchXML($action, $xml);
        $data = simplexml_load_string($result);

        //ZF2 Authenticate
        if ($data->registrationresponse->status == 'success') {
            $this->setSession($username);

            //If there's a profile pic upload it...
            if (isset($_FILES['file'])) {
                $file = $_FILES['file'];
                $fileName = $file['name'];
                $filetype = $file['type'];
                $filetmp_name = $file['tmp_name'];
                $filesize = $file['size'];

//	    	 	echo "filename ----> $fileName<BR>";	 
//		     	echo "filetype ----> $filetype<BR>";	 
//     			echo "filetmp_name ----> $filetmp_name<BR>";	 
//    	 		echo "filesize ----> $filesize<BR>";	

                $url = MemreasConstants::ORIGINAL_URL;
                $guzzle = new Client();
                $session = new Container('user');
                $request = $guzzle->post($url)
                        ->addPostFields(
                                array(
                                    'user_id' => $session->offsetGet('user_id'),
                                    'filename' => $fileName,
                                    'event_id' => "",
                                    'device_id' => "",
                                    'is_profile_pic' => 1,
                                    'is_server_image' => 0,
                                )
                        )
                        ->addPostFiles(
                        array(
                            'f' => $filetmp_name,
                        )
                );
            }
            $response = $request->send();
            $data = $response->getBody(true);
            $xml = simplexml_load_string($result);

            //ZF2 Authenticate
            error_log("addmediaevent result -----> " . $data);
            if ($xml->addmediaeventresponse->status == 'success') {
                //Do nothing even if it fails...
            }

            //Redirect here
            return $this->redirect()->toRoute('index', array('action' => $redirect));
        } else {
            return $this->redirect()->toRoute('index', array('action' => "index"));
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
            $this->authservice = $this->getServiceLocator()
                    ->get('AuthService');
        }

        return $this->authservice;
    }

    public function getSessionStorage() {
        if (!$this->storage) {
            $this->storage = $this->getServiceLocator()
                    ->get('application\Model\MyAuthStorage');
        }

        return $this->storage;
    }

    public function security($path) {
        //if already login do nothing
        $session = new Container("user");
        if (!$session->offsetExists('user_id')) {
            error_log("Not there so logout");
            $this->logoutAction();
            return "application/index/index.phtml";
        }
        return $path;
        //return $this->redirect()->toRoute('index', array('action' => 'login'));
    }

}

// end class IndexController
