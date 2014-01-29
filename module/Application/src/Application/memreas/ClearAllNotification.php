<?php
namespace Application\memreas;
use Zend\Session\Container;

use Application\Model\MemreasConstants;
use \Exception;


class ClearAllNotification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $notification;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
        if(!$this->notification){
        $this->notification = new Notification($service_locator);
        }
    }

    public function exec($frmweb='') {
                    
        if(empty($frmweb)){
            $data = simplexml_load_string($_POST['xml']);
        } else{
            
            $data =json_decode(json_encode($frmweb));
        }
        $message='';$time = time();

   
             $user_id = (trim($data->clearallnotification->user_id));
             //save notification in table
       
        if (empty($user_id)) {
             $status = "failure";
             $message ="Notification not found";
        } else{   
           
                    $qb = $this->dbAdapter->createQueryBuilder();
                    $q = $qb->update('\Application\Entity\Notification', 'n')
                            ->set('n.is_read', '1')
                            ->where('n.user_id = ?1 AND n.status = 0')
                           ->setParameter(1, $user_id)
                           ->getQuery();
                    $p = $q->execute();
                    
                    
                    
                    $status = "Sucess";
             $message ="All Notification cleared";
                           
           }
            
       

        
        
                    
         if(empty($frmweb)){
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output.= "<clearallnotification>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>" . $message."</message>";
        $xml_output.= "</clearallnotification>";
        $xml_output.= "</xml>";
        echo $xml_output;
        }
        
    }

}

?>
