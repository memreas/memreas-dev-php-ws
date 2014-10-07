<?php
    /*
    * Check if event exist or not
    * @params: user_id, media_id
    * @Return Falure if media existed and Success if has no
    * @Tran Tuan
    */
    namespace Application\memreas;

    use Zend\Session\Container;
    use Application\Model\MemreasConstants;
    use Application\memreas\AWSManagerSender;
    use Application\Entity\Event;

    class CheckEvent{
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

        /*
         *
         */
        public function exec($frmweb = false, $output = '') {
            $error_flag = 0;
            $message = '';
            if (empty ( $frmweb )) {
                $data = simplexml_load_string ( $_POST ['xml'] );
            } else {
                $data = json_decode ( json_encode ( $frmweb ) );
            }
            $event_id = $data->checkevent->event_id;

            $query = $this->dbAdapter->createQueryBuilder();
            $query->select('e')
                ->from('Application\Entity\Event', 'e')
                ->where('e.event_id = ?1')
                ->setParameter(1, $event_id);
            $result = $query->getQuery()->getResult();
            if (!empty($result)){
                $status = 'success';
                $output .= '<event_id>' . $result[0]->event_id . '</event_id>';
                $output .= '<event_name>' . $result[0]->name . '</event_name>';
            }
            else {
                $status = 'Failure';
                $message = 'Event does not exist';
            }
            error_log("Inside CheckExistEvent.exec() - status ---> $status".PHP_EOL);

            if ($frmweb) {
                return $output;
            }
            header ( "Content-type: text/xml" );
            $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
            $xml_output .= "<xml>";
            $xml_output .= "<checkeventresponse>";
            $xml_output .= "<status>" . $status . "</status>";
            if (isset($message)) $xml_output .= "<message>{$message}</message>";
            $xml_output .= $output;
            $xml_output .= "</checkeventresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

?>
