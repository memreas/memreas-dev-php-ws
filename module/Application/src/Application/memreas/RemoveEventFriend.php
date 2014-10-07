<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

use Application\Entity\EventFriend;

class RemoveEventFriend {
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
        // $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }
    public function exec() {
        $data = simplexml_load_string ( $_POST ['xml'] );
        $friend_ids = $data->removeeventfriend->friend_ids->friend_id;
        $event_id = $data->removeeventfriend->event_id;
        if (!empty($friend_ids)){
            $friendList = array();
            foreach ($friend_ids as $friend_id)
                $friendList[] = "'" . $friend_id . "'";

            $friendList = implode(', ', $friendList);

            $query_event = "DELETE FROM Application\Entity\EventFriend ef WHERE ef.friend_id IN ({$friendList}) AND ef.event_id = '{$event_id}'";
            $event_statement = $this->dbAdapter->createQuery ( $query_event );
            $event_result = $event_statement->getResult ();
        }

        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<removeeventfriendresponse>";
        $xml_output .= "<status>Success</status>";
        $xml_output .= "<message>Event friend removed</message>";
        $xml_output .= "</removeeventfriendresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}
?>
