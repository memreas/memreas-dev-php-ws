<?php
namespace Application\memreas;
 

use Zend\Session\Container;

use Application\Model\MemreasConstants;
use Application\memreas\MUUID;

class RegisterDevice {

	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;

	public function __construct($message_data, $memreas_tables, $service_locator) {
	   $this->message_data = $message_data;
	   $this->memreas_tables = $memreas_tables;
	   $this->service_locator = $service_locator;
	   $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');

	}


	 
	public function exec() {
        error_log('registerdevice');
		$data = simplexml_load_string($_POST['xml']);
		$username = trim($data->registerdevice->username);
        $devicetoken = trim($data->registerdevice->devicetoken);
        $devicetype = trim($data->registerdevice->devicetype);
        
		header("Content-type: text/xml");
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<registerdeviceresponse>";
        $time = time();
		if (!empty($username)&& !empty($devicetoken) && !empty($devicetype)) {
            $qb = $this->dbAdapter->createQueryBuilder();
                            $qb->select('u.user_id');
                            $qb->from('Application\Entity\User', 'u');
                            $qb->join('Application\Entity\Device', 'd','WITH', 'u.user_id = d.user_id');
                            $qb->where('d.device_type = ?1 AND u.username = ?2');
                            $qb->setParameter(1, $devicetype);
                            $qb->setParameter(2, $username);
                            $result =$qb->getQuery()->getOneOrNullResult();
               
                if($result){
 
                    $qb = $this->dbAdapter->createQueryBuilder();
                    $q = $qb->update('\Application\Entity\Device', 'd')
                            ->set('d.device_token', $qb->expr()->literal($devicetoken))
                            ->set('d.update_time', $qb->expr()->literal($time))
                            ->where('d.user_id = ?1 AND d.device_type = ?2')
                            ->setParameter(1, $result['user_id'])
                            ->setParameter(2, $devicetype)
                            ->getQuery();
                    $p = $q->execute();
                     
                }else{
                    $sql = "SELECT u FROM Application\Entity\User u  where  u.username = '$username'";
                    $statement = $this->dbAdapter->createQuery($sql);
                    $result1 = $statement->getOneOrNullResult();
                    
                    if($result1){
                        
                        $tblDevice = new \Application\Entity\Device();
                        $device_id = MUUID::fetchUUID();
                        $tblDevice->device_id = $device_id;
                        $tblDevice->device_token = $devicetoken;
                        $tblDevice->user_id = $result->user_id;
                        $tblDevice->device_type = $devicetype;
                        $tblDevice->create_time = $time;
                        $tblDevice->update_time = $time;
                        $this->dbAdapter->persist($tblDevice);
                        $this->dbAdapter->flush();
                    }
                    
                }

			$status= 'success';
            $message = "Device Token Saved";
        }else{
            $status= 'failure';
            $message = "";
        }
        $xml_output .= "<status>$status</status>";
        $xml_output .= "<message>$message</message>";
		$xml_output .= "</registerdeviceresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
error_log ("getsession ---> xml_output ----> " . $xml_output . PHP_EOL);
	}
    
      
	
}
?>
