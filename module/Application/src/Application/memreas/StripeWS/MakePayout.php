<?php
    namespace Application\memreas\StripeWS;

    use Zend\Session\Container;
    use Application\Model\MemreasConstants;
    use Application\memreas\AWSManagerSender;
    use Guzzle\Http\Client;

    class MakePayout {
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
        }

        public function exec($frmweb = false, $output = '') {
            $message = '';
            if (empty ( $frmweb )) {
                $data = simplexml_load_string ( $_POST ['xml'] );
            } else {

                $data = json_decode ( json_encode ( $frmweb ) );
            }
            $account_id = trim ( $data->makepayout->account_id );
            $amount = trim ( $data->makepayout->amount );
            $description = trim ( $data->makepayout->description );

            $guzzle = new Client();

            $request = $guzzle->post(
                MemreasConstants::MEMREAS_PAY_URL,
                null,
                array(
                    'action' => 'makepayout',
                    'amount' => $amount,
                    'description' => $description
                )
            );

            $response = $request->send();
            $data = json_decode($response->getBody(true), true);

            $status = $data['status'];
            $message = $data['message'];

            header ( "Content-type: text/xml" );
            $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
            $xml_output .= "<xml>";
            $xml_output .= "<makepayoutresponse>";
            $xml_output .= "<status>" . $status . "</status>";
            if (isset($message)) $xml_output .= "<message>{$message}</message>";
            $xml_output .= $output;
            $xml_output .= "</makepayoutresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

?>