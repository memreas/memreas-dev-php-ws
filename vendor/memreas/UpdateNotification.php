<?php
namespace memreas;
 

use Zend\Session\Container;

use Application\Model\MemreasConstants;
use memreas\UUID;
use \Exception;


class UpdateNotification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec($frmweb='') {

        if(empty($frmweb)){
            $data = simplexml_load_string($_POST['xml']);
        } else{
            
            $data =json_decode(json_encode($frmweb));
        }
        
        $user_id = (trim($data->updatenotification->user_id));
        $notification_id = (trim($data->updatenotification->notification_id));
        
        $status = trim($data->updatenotification->status);

        $time = time();
       
        //save notification in table
        $tblNotification = $this->dbAdapter->find("\Application\Entity\Notification", $notification_id);
        
        if (!$tblNotification) {
             $status = "failur";
             $message ="Notification not found";
        } else{
            
             $tblNotification->status = $status;
             $tblNotification->update_time = $time;
        
             $this->dbAdapter->flush();
             $status = "Sucess";
             $message ="Notification Updated";
             $user_id = $tblNotification->user_id;
             $get_user_device = "SELECT d  FROM  Application\Entity\Device d where d.user_id='$user_id'";
             $statement = $this->dbAdapter->createQuery($get_user_device);
             $r = $statement->getOneOrNullResult();
             if($r){
                 
                 switch($tblNotification->notification_type){
                     
                     case 1:
                         $message = "Friend ";          
                         break;
                     case 2:
                         $message = "Add Friend to Event";             
                         break;
                     case 3:
                         $message = "Add Media ";
                         break;
                     case 4:
                         $message = "Add Comment";
                         break;
                 }
                 
                 
                 
                 
                 
                 
             gcm::sendpush($message, $r->device_token);
             }
                    
            
        }

       
         
        
        
        
        
        
        
        
        
        
        
        
        
        
        
        
       
         
       
            
        
 
        
        if(empty($frmweb)){
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output.= "<addnotification>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>" . $message . "</message>";
        $xml_output.= "<notification_id>$notification_id</notification_id>";
        $xml_output.= "</addnotification>";
        $xml_output.= "</xml>";
        echo $xml_output;
        }
        
    }

}

?>
