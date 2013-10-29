<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;

//use Application\facebook\Facebook;
//use Application\twitteroauth\TwitterOAuth;

use Application\memreas\UUID;

class Notification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $userIds;
    protected $message;
    protected $gcm;
    protected $apns;
    protected $type;
    protected $event_id;
    protected $media_id;
    protected $fb;
    protected $twitter;



    public function __construct($service_locator) {
        error_log("Inside__construct...");
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        if (!$this->gcm) {
            $this->gcm = new gcm($service_locator);
        }
         if (!$this->apns) {
            $this->apns = new apns($service_locator);
        }
        /*
        if (!$this->fb) {
            $config = array();
            $config['appId'] = '152686394870393';
            $config['secret'] = '62215615af53550af20c1d56736c189e';
            $this->fb =  new Facebook($config);
            $this->fb->setAccessToken($config['appId'].'|'.$config['secret']);
            $user = '100001297014569';
$message = 'kamlesh noti message';
$href = 'http://apps.facebook.com/kptestfb
';

$params = array(
        'href' => $href,
        'template' => $message,
    );

  //$facebook->api('/' . $user . '/notifications/', 'post', $params);
  

        }
            
        if(!$this->twitter){
            
            $config = array();
            $config['CONSUMER_KEY'] = '1bqpAfSWfZFuEeY3rbsKrw';
            $config['CONSUMER_SECRET'] = 'wM0gGBCzZKl5dLRB8TQydRDfTD5ocf2hGRKSQwag';
            $config['OAUTH_CALLBACK'] = 'http://localhost.in/test/twitteroauth';
            $config['oauth_token'] = '74094832-mnJlYPt02qpy1jhEYAYPMKAzrLF2jTeMiJue65Zn7';
             $config['oauth_token_secret'] = 'zdIrpUzuIs7llt5KLlx1TU1vWUrq28TkSNFUsschaaE4X';

         
            $this->twitter =  new TwitterOAuth($config['CONSUMER_KEY'],  $config['CONSUMER_SECRET'], $config['oauth_token'], $config['oauth_token_secret']);
echo '<pre>';print_r($this->twitter );
$options = array("screen_name" => "kamleshpawar", "text" => "Hey that's my message");
print_r($this->twitter->post('direct_messages/new', $options));
            exit;
        }

*/


 
    }
            
  
    public function add($userid) {

        $this->userIds[] = $userid;
    }

    public function send() {
            
        $get_user_device = "SELECT d  FROM  Application\Entity\Device d where d.user_id in('" . join('\' , \'', $this->userIds) . "')";
        $statement = $this->dbAdapter->createQuery($get_user_device);

        $users = $statement->getArrayResult();

        if (count($users) > 0) {


            foreach ($users as $user) {
                if ($user['device_type'] == \Application\Entity\Device::ANROID) {
                    $this->gcm->addDevice($user['device_token']);
                } else if ($user['device_type'] == \Application\Entity\Device::APPLE) {
                    $this->apns->addDevice($user['device_token']);
                }
            }
            
            if($this->gcm->getDeviceCount()> 0){
                print_r($this->gcm->sendpush($this->message,$this->type,$this->event_id,$this->media_id));
            }
             if($this->apns->getDeviceCount()> 0){
                $this->apns->sendpush($this->message,$this->type,$this->event_id,$this->media_id);
             }
        }
        
        //notification face book
        
    }

    public function setUpdateMessage($notification_type, $data = '') {
        switch ($notification_type) {
            case 1:
                $this->message = "Friend Request";
                break;
            case 2:
                $this->message = "Add Friend to Event";
                break;
            case 3:
                $this->message = "Add Media ";
                break;
            case 4:
                $this->message = "Add Comment";
                break;
            case 5:
                $this->message = "Update on Event";
                break;
            
        }

         //echo '<pre>';print_r($notification_type);exit;
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
