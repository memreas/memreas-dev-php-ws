<?php
namespace memreas;
use Zend\Session\Container;

use Application\Model\MemreasConstants;

class apns {
	protected $service_locator;
	protected $dbAdapter;
       protected $device_token=array();

	public function __construct() {
	   //$this->dbAdapter = $service_locator->get(MEMREASDB);
	   //$this->service_locator = $service_locator;
	   //$this->dbAdapter = $this->service_locator->get('doctrine.entitymanager.orm_default');

	}
    public function addDevice($device_token) {
    $this->device_token[] =$device_token;
 
   }
	public static function sendpush($message='', $type='', $id='')
	{// Message to be sent
            $payload = '{
					"aps" : 
						
						{
						  "alert" : "Test",
						  "badge" : "1",
						  "sound" : "default"
						} 
				}';
	
	$ctx = stream_context_create();
	stream_context_set_option($ctx, 'ssl', 'local_cert', 'ck.pem');
	stream_context_set_option($ctx, 'ssl', 'passphrase', 'nopass');
	//$fp = stream_socket_client('ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT, $ctx);
	
	$fp = stream_socket_client('ssl://gateway.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);

 
	if(!$fp){
		print "Failed to connect $err";
		return;
	} else {
		print "Notifications sent!";
	}
	
	// Pass device key	
	foreach($this->device_token as $Token){
		$msg = chr(0) . pack("n",32) . pack('H*', str_replace(' ', '', $Token)) . pack		("n",strlen($payload)) . $payload;
		fwrite($fp, $msg);
	}
	fclose($fp);


	}

}
?>
