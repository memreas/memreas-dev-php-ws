<?php
/*
* @Get event people
* @params: event id
* @return: event people list
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\Entity\Event;

class GetEventDetails {
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
        $event_id = trim ( $data->geteventdetails->event_id );

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select('e')
            ->from('Application\Entity\Event', 'e')
            ->where('e.event_id = ?1')
            ->setParameter(1, $event_id);
        $event_detail = $qb->getQuery()->getResult();
        if (empty($event_detail)){
            $status = 'Failure';
            $message = 'No event found for this id';
        }
        else{
            $event_detail = $event_detail[0];
            $status = 'Success';
            $output .= '<event>';
                $output .= '<event_id>' . $event_detail->event_id . '</event_id>';
                $output .= '<name>' . $event_detail->name . '</name>';
                $output .= '<location>' . $event_detail->location . '</location>';
                $output .= '<date>' . $event_detail->date . '</date>';
                $output .= '<friends_can_post>' . (($event_detail->friends_can_post) ? 1 : 0) . '</friends_can_post>';
                $output .= '<friends_can_share>' . (($event_detail->friends_can_share) ? 1 : 0) . '</friends_can_share>';
                $output .= '<public>' . (($event_detail->public) ? 1 : 0) . '</public>';
                $output .= '<viewable_from>' . (($event_detail->viewable_from) ? date('m/d/Y', $event_detail->viewable_from) : '') . '</viewable_from>';
                $output .= '<viewable_to>' . (($event_detail->viewable_to) ? date('m/d/Y', $event_detail->viewable_to) : '') . '</viewable_to>';
                $output .= '<self_destruct>' . (($event_detail->self_destruct) ? date('m/d/Y', $event_detail->self_destruct) : '') . '</self_destruct>';
            $output .= '</event>';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<geteventdetailsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</geteventdetailsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}
?>
