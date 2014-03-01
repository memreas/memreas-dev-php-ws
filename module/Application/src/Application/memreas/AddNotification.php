<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use Application\Entity\Notification;

class AddNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification_id;
	public $sendShortCode=false;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
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
		if( $tblNotification->notification_type == Notification::ADD_FRIEND_TO_EVENT 
			&& $network_name !== 'memreas'  
			 ){
			$uuid = explode('-', $this->notification_id);
        	$short_code_10 = base_convert($uuid [0], 16, 10);
        	$tblNotification->short_code = $this->getSortCode($short_code_10);
        	$tblNotification->notification_method = Notification::NONMEMERAS;
        	$tblNotification->meta .= ' code '.$tblNotification->short_code;
		}else if($network_name == 'memreas'){
			$tblNotification->notification_method = Notification::MEMERAS ;
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

function getSortCode($id,$chars='0abcdfghjkmnpqrstvwxyz123456789ABCDFGHJKLMNPQRSTVWXYZ(!@${}^&*-)')
{
	$code ='';
	$length = strlen($chars);
    while ($id > $length - 1) {
    	// determine the value of the next higher character
        // in the short code should be and prepend
        $l = $id % $length;
        $code = $chars[$l] .$code;
        // reset $id to remaining value to be converted
        $id = intval($id / $length);
    }

    // remaining value of $id is less than the length of
    // self::$chars
    $code = $chars[$id] . $code;
	return   $code;
}


}

?>
