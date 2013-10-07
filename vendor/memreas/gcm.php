<?php
namespace memreas;
use Zend\Session\Container;

use Application\Model\MemreasConstants;

class gcm {

	protected $service_locator;
	protected $dbAdapter;
    protected $messages;
    protected $device_token;
    public function __construct($service_locator) {
	   //$this->dbAdapter = $service_locator->get(MEMREASDB);
	   $this->service_locator = $service_locator;
	   //$this->dbAdapter = $this->service_locator->get('doctrine.entitymanager.orm_default');
//       $config = $this->service_locator->get('Config');

	}
public function addDevice($device_token) {
    $this->device_token[] =$device_token;
 
   }
	public function sendpush($message='')
	{// Message to be sent
         if(count($this->device_token)==0)return;
 
        $url = 'https://android.googleapis.com/gcm/send';

$fields = array(
                'registration_ids'  => $this->device_token,
                'data'              => array( "price" => $message ),
                );

$headers = array( 
                    'Authorization: key=AIzaSyC-NTSCQBJuBAuvwjlDH5SRm2IaixuW5gM',
                    'Content-Type: application/json'
                );

// Open connection
$ch = curl_init();

// Set the url, number of POST vars, POST data
curl_setopt( $ch, CURLOPT_URL, $url );

curl_setopt($ch, CURLOPT_POST, true );
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode( $fields ));

// Execute post
$result = curl_exec($ch);

// Close connection
curl_close($ch);

return $result;

	}

}
?>
