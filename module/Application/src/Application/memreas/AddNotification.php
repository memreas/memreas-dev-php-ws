<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use Application\Entity\Notification;
use Application\memreas\Email;



class AddNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification_id;
	protected $aws_manager;
 


	public $sendShortCode=false;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->aws_manager = new AWSManagerSender($service_locator);
		$this->viewRender = $service_locator->get('ViewRenderer');     

		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		
		$user_id = (trim ( $data->addNotification->user_id ));
		
		$meta = $data->addNotification->meta;
		$event_id = $data->addNotification->event_id;
		$event_name = $data->addNotification->event_name;
		
		$status = empty ( $data->addNotification->status ) ? 0 : $data->addNotification->status;
		$notification_type = $data->addNotification->notification_type;
		$links = $data->addNotification->links;
		$network_name = $data->addNotification->network_name;
		$time = time ();
		// save notification in table
		$this->notification_id = $notification_id = MUUID::fetchUUID ();
		$tblNotification = new \Application\Entity\Notification ();
		$tblNotification->notification_id = $notification_id;
		$tblNotification->user_id = $user_id;
		$tblNotification->notification_type = $notification_type;
		$tblNotification->meta = $meta;
		$tblNotification->links = $links;

		
		$tblNotification->notification_method = Notification::EMAIL ;
		 
		if($network_name == 'email'){
			//do nothing
		}else if( $tblNotification->notification_type == Notification::ADD_FRIEND_TO_EVENT 
			&& $network_name !== 'memreas'  
			 ){
			$uuid = explode('-', $this->notification_id);
			$short_code =  $this->getSortCode($uuid [0]);
        	$tblNotification->short_code = $this->getSortCode($uuid [0]);
        	$tblNotification->notification_method = Notification::NONMEMREAS;
        	$tblNotification->meta .= ' code '.$tblNotification->short_code;
        	Email::$item['short_code'] = $short_code;
        	$this->composeNonMemarsMail($data);
		}else if($network_name == 'memreas'){
			$tblNotification->notification_method = Notification::MEMREAS;
 		}  
		
		$tblNotification->create_time = $time;
		$tblNotification->update_time = $time;
 		$this->dbAdapter->persist ( $tblNotification );
		
		try {
			$this->dbAdapter->flush ();
			$status = "success";
			$message = "";
		} catch ( \Exception $exc ) {
			$message = "";
			$status = "fail";
		}
		
		if (empty ( $frmweb )) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<addnotification>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>" . $message . "</message>";
			$xml_output .= "<notification_id>$notification_id</notification_id>";
			$xml_output .= "</addnotification>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}

function getSortCode($str)
{
	$raw = '';
        for ($i=0; $i < strlen($str); $i+=2)
        {
            $raw .= chr(hexdec(substr($str, $i, 2)));
        }
        return rtrim(base64_encode($raw),'=');
	
}
	 

}

?>
