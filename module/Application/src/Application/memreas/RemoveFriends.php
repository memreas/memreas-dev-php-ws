<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

use Application\Entity\EventFriend;

class RemoveFriends {
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log ( "Inside__construct..." );
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
        // $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }
    public function exec() {
        $data = simplexml_load_string ( $_POST ['xml'] );
        $friend_ids = $data->removefriends->friend_ids->friend_id;
        $user_id = $data->removefriends->user_id;
        if (!empty($friend_ids)){
            $friendList = array();
            foreach ($friend_ids as $friend_id)
                $friendList[] = "'" . $friend_id . "'";

            $friendList = implode(', ', $friendList);

            $query_friends = "DELETE FROM Application\Entity\UserFriend uf WHERE uf.friend_id IN ({$friendList}) AND uf.user_id = '{$user_id}'";
            $friend_statement = $this->dbAdapter->createQuery ( $query_friends );
            $friend_result = $friend_statement->getResult ();
        }

        if ($friend_result) { 
	        header ( "Content-type: text/xml" );
	        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
	        $xml_output .= "<xml>";
	        $xml_output .= "<removefriendsresponse>";
	        $xml_output .= "<status>Success</status>";
	        $xml_output .= "<message>friends removed</message>";
	        $xml_output .= "</removefriendsresponse>";
	        $xml_output .= "</xml>";
	        echo $xml_output;
        } else {
        	header ( "Content-type: text/xml" );
        	$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        	$xml_output .= "<xml>";
        	$xml_output .= "<removefriendsresponse>";
        	$xml_output .= "<status>Failure</status>";
        	$xml_output .= "<message>error occurred</message>";
        	$xml_output .= "</removefriendsresponse>";
        	$xml_output .= "</xml>";
        	echo $xml_output;
        }
    }
}
?>