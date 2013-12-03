<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\Facebook\Facebook;
use Application\TwitterOAuth\TwitterOAuth;
use Application\memreas\UUID;
use \Exception;

class Notification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $userIds;
    protected $firends;
    protected $message;
    protected $gcm;
    protected $apns;
    protected $type;
    protected $event_id;
    protected $media_id;
    protected $fb;
    protected $twitter;

    public function __construct($service_locator) {
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        if (!$this->gcm) {
            $this->gcm = new gcm($service_locator);
        }
        if (!$this->apns) {
            $this->apns = new apns($service_locator);
        }

        if (!$this->fb) {
            $config = array();
            $config['appId'] = '152686394870393';
            $config['secret'] = '62215615af53550af20c1d56736c189e';
            $this->fbhref = 'http://apps.facebook.com/kptestfb';
            $this->fb = new Facebook($config);
            $this->fb->setAccessToken($config['appId'] . '|' . $config['secret']);
        }

        if (!$this->twitter) {

            $config = array();
            $config['consumer_key'] = '1bqpAfSWfZFuEeY3rbsKrw';
            $config['consumer_secret'] = 'wM0gGBCzZKl5dLRB8TQydRDfTD5ocf2hGRKSQwag';
            $config['oauth_token'] = '74094832-mnJlYPt02qpy1jhEYAYPMKAzrLF2jTeMiJue65Zn7';
            $config['oauth_token_secret'] = 'zdIrpUzuIs7llt5KLlx1TU1vWUrq28TkSNFUsschaaE4X';
            $config['output_format'] = 'object';
            $this->twitter = new TwitterOAuth($config);
        }
    }

    public function add($userid) {

        $this->userIds[] = $userid;
    }

    public function addFriend($friendid) {

        $this->firends[] = $friendid;
    }

    public function send() {
        try {
            //mobile notification. 
        if (count($this->userIds) > 0) {
            $get_user_device = "SELECT d  FROM  Application\Entity\Device d where d.user_id in('" . join('\' , \'', $this->userIds) . "')";
            $statement = $this->dbAdapter->createQuery($get_user_device);
            $users = $statement->getArrayResult();

            if (count($users) > 0) {


                foreach ($users as $user) {
                    error_log('user-id- '.$user['user_id'].'  devicetype-'.$user['device_type'] . PHP_EOL);
                    if ($user['device_type'] == \Application\Entity\Device::ANROID) {
                        $this->gcm->addDevice($user['device_token']);
                    } else if ($user['device_type'] == \Application\Entity\Device::APPLE) {
                        $this->apns->addDevice($user['device_token']);
                    }
                }

                if ($this->gcm->getDeviceCount() > 0) {
                    
                    $x = $this->gcm->sendpush($this->message, $this->type, $this->event_id, $this->media_id);
                    error_log('SENDING-ANROID'.   print_r($x, true) . PHP_EOL);
                }
                if ($this->apns->getDeviceCount() > 0) {
                    
                    $x = $this->apns->sendpush($this->message, $this->type, $this->event_id, $this->media_id);
                    error_log('SENDING-Apple'.   print_r($x, true) . PHP_EOL);
                }
            }
            //memaras user fb twitter
            $this->webNotification();
        }
        //non memras users fb twitter
        $this->webNotification();
        } catch (\Exception $exc) {
        }

        
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

    public function webNotification() {
        if (count($this->firends) > 0) {
            //web notification 
            $get_user = "SELECT f  FROM  Application\Entity\Friend f where f.friend_id in('" . join('\' , \'', $this->firends) . "')";
            $statement = $this->dbAdapter->createQuery($get_user);
            $users = $statement->getArrayResult();

            if (count($users) > 0) {
                $fbparams = array(
                    'href' => $this->fbhref,
                    'template' => $this->message,
                );
                $twparams['text'] = $this->message;
                foreach ($users as $user) {
                    switch (strtolower($user['network'])) {
                        case 'facebook':
                            error_log('SENDING-FB');
                            $this->fb->api('/' . $user['social_username'] . '/notifications/', 'post', $fbparams);
                            break;
                        case 'twitter':
                            error_log('SENDING-tWIIT');
                            $twparams['screen_name'] = $user['social_username'];
                            $this->twitter->post('direct_messages/new', $twparams);

                            break;
                        default:
                            break;
                    }
                }
            }
        }
    }

}

?>
