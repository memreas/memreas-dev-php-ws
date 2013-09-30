<?php
namespace memreas;
use Zend\Session\Container;

use Application\Model\MemreasConstants;

class gcm {

	protected $service_locator;
	protected $dbAdapter;

	public function __construct($service_locator) {
	   //$this->dbAdapter = $service_locator->get(MEMREASDB);
	   //$this->service_locator = $service_locator;
	   //$this->dbAdapter = $this->service_locator->get('doctrine.entitymanager.orm_default');

	}

	public static function sendpush($message,$user_register)
	{// Message to be sent
$message = $message;

// Set POST variables
$url = 'https://android.googleapis.com/gcm/send';

$fields = array(
                'registration_ids'  => array($user_register),
                'data'              => array( "price" => $message ),
                );
echo'<pre>'; print_r($fields);
$headers = array( 
                    'Authorization: key=AIzaSyC-NTSCQBJuBAuvwjlDH5SRm2IaixuW5gM',
                    'Content-Type: application/json'
                );

// Open connection
$ch = curl_init();

// Set the url, number of POST vars, POST data
curl_setopt( $ch, CURLOPT_URL, $url );

curl_setopt( $ch, CURLOPT_POST, true );
curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
curl_setopt($ch,
CURLOPT_SSL_VERIFYPEER, false);
curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $fields ) );

// Execute post
$result = curl_exec($ch);

// Close connection
curl_close($ch);

return $result;

	}

}
?>
