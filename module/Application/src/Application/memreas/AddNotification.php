<?php
namespace Application\memreas;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\UUID;

class AddNotification {

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
        
        $user_id = (trim($data->addNotification->user_id));
         
        $meta = $data->addNotification->meta;
        
        
        $status = empty($data->addNotification->status)?0:$data->addNotification->status;
        $notification_type = $data->addNotification->notification_type;
        $links= $data->addNotification->links;
        $time = time();
         //save notification in table
        $notification_id = UUID::getUUID($this->dbAdapter);
        $tblNotification = new \Application\Entity\Notification();
        $tblNotification->notification_id = $notification_id;
        $tblNotification->user_id = $user_id;
        $tblNotification->notification_type = $notification_type;
        $tblNotification->meta = $meta;
        $tblNotification->links = $links;

        $tblNotification->create_time = $time;
        $tblNotification->update_time = $time;
        $this->dbAdapter->persist($tblNotification);
          
        
        try {
            $this->dbAdapter->flush();
             $status = "success";
             $message ="";
        } catch (\Exception $exc) {
            $message ="";
            $status = "fail";
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
