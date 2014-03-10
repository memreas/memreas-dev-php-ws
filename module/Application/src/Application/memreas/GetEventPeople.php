<?php
/*
* @Get event people
* @params: event id
* @return: event people list
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Entity\EventFriend;
use Application\Entity\Friend;

class GetEventPeople {
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
    }
    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty ( $frmweb )) {
            $data = simplexml_load_string ( $_POST ['xml'] );
        } else {
            $data = json_decode ( json_encode ( $frmweb ) );
        }
        $event_id = trim ( $data->geteventpeople->event_id );

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select('f')
            ->from('Application\Entity\EventFriend', 'ef')
            ->join('Application\Entity\Friend', 'f', 'WITH', 'ef.friend_id = f.friend_id')
            ->where('ef.event_id = ?1')
            ->setParameter(1, $event_id);
        $event_people = $qb->getQuery()->getResult();
        if (empty($event_people)){
            $status = 'Failure';
            $message = 'No people with this event';
        }
        else{
            $status = 'Success';
            $output .= '<friends>';
            foreach ($event_people as $people){
                $output .= '<friend>';
                    $output .= '<friend_id>' . $people->friend_id . '</friend_id>';
                    $output .= '<friend_name>' . $people->social_username . '</friend_name>';
                    $output .= '<photo>' . $people->url_image . '</photo>';
                $output .= '</friend>';
            }
            $output .= '</friends>';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<geteventpeopleresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        $xml_output .= "<event_id>{$event_id}</event_id>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</geteventpeopleresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}
?>
