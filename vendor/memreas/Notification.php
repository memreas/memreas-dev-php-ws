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
    

    public function __construct($service_locator) {
        error_log("Inside__construct...");
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        if(!$this->gcm){
        $this->gcm = new gcm($service_locator);
        }
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }
    public function add($userid){
        
        $this->userIds[] = $userid;
    }

    public function send() {
        $get_user_device = "SELECT d  FROM  Application\Entity\Device d where d.user_id in('".join('\' , \'',$this->userIds)."')";
        $statement = $this->dbAdapter->createQuery($get_user_device);
                                
        $users = $statement->getArrayResult();
              if(count($users) >0 ){
                 

                foreach ($users as $user){

                    if($user['device_type'] == \Application\Entity\Device::ANROID){
                        
                        $this->gcm->addDevice($user['device_token']);
                    }else if($user['device_type'] == \Application\Entity\Device::APPLE){
                        
                    }
                    
                } 
                $this->gcm->sendpush($this->message);
                     
      
       
        
    }
    
     
}
public function setUpdateMessage($notification_type,$data=''){
                         switch($notification_type){          
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
                 }

       // echo '<pre>';print_r($notification_type);exit;

    }
    
    public function setMessage($message){
         $this->message = $message;   
        
    }

}

?>
