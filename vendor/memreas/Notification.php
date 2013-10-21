<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\UUID;

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
    protected $id;
    protected $media_id;



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
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
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
                $this->gcm->sendpush($this->message,$this->type,$this->id,$this->media_id);
            }
             if($this->apns->getDeviceCount()> 0){
                $this->apns->sendpush($this->message,$this->type,$this->id,$this->media_id);
             }
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

    
}

?>
