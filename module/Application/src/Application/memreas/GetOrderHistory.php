<?php
    namespace Application\memreas;

    use Zend\Session\Container;
    use Application\Model\MemreasConstants;
    use Application\memreas\AWSManagerSender;
    use Guzzle\Http\Client;

    class GetOrderHistory {
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
            // $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
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
            $user_name = trim ( $data->getorderhistory->user_name );
            $guzzle = new Client();

            $request = $guzzle->post(
                'http://memreasdev-pay.elasticbeanstalk.com',
                null,
                array(
                    'action' => 'list',
                    'user_name' => $user_name
                )
            );
            $response = $request->send();print_r($response);exit;
            $data = json_decode($response->getBody(true), true);
            $status = $data['status'];
print_r($user_name);print_r($data);exit;
            if ($status == 'Success'){
                $plans = $data['plans'];
                if (!empty($plans)){
                    foreach ($plans as $plan){
                        $output .= '<plan_id>' . $plan['plan']['id'] . '</plan_id>';
                        $output .= '<plan_name>' . $plan['plan']['name'] . '</plan_name>';
                        $output .= '<plan_amount>' . ($plan['plan']['amount'] / 100) . '</plan_amount>';
                        $output .= '<plan_currency>' . $plan['plan']['currency'] . '</plan_currency>';
                    }
                }else{
                    $status = 'Failure';
                    $message = 'This user has no any actived plan';
                }
            }
            else $message = $data['message'];

            header ( "Content-type: text/xml" );
            $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
            $xml_output .= "<xml>";
            $xml_output .= "<getorderhistoryresponse>";
            $xml_output .= "<status>" . $status . "</status>";
            if (isset($message)) $xml_output .= "<message>{$message}</message>";
            $xml_output .= $output;
            $xml_output .= "</getorderhistoryresponse>";
            $xml_output .= "</xml>";
            echo $xml_output;
        }
    }

?>
