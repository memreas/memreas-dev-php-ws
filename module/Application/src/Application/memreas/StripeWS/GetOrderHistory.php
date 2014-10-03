<?php
    namespace Application\memreas\StripeWS;

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
            $user_id = trim ( $data->getorderhistory->user_id );
            $page = trim ( $data->getorderhistory->page );
            $limit = trim ( $data->getorderhistory->limit );

            $guzzle = new Client();

            $request_data = array(
                'user_id' => $user_id,
                'page' => $page,
                'limit' => $limit
            );

            $request = $guzzle->post(
                MemreasConstants::MEMREAS_PAY_URL,
                null,
                array(
                    'action' => 'getorderhistory',
                    'data' => json_encode($request_data)
                )
            );

            $response = $request->send();
            $data = json_decode($response->getBody(true), true);

            $status = $data['status'];

            if ($status == 'Success'){
                $status = 'Success';
                $orders = $data['orders'];
                if (!empty($orders)){
                    $output .= '<user_detail>' . json_encode($data['user']) . '</user_detail>';
                    $output .= '<orders>';
                    if ($user_id) $output .= '<user_id>' . $user_id . '</user_id>';
                    foreach ($orders as $transaction){
                        $output .= '<order>';
                            $output .= '<transaction_id>' . $transaction['transaction_id'] . '</transaction_id>';
                            $output .= '<transaction_type>' . $transaction['transaction_type'] . '</transaction_type>';
                            $output .= '<amount>' . $transaction['amount'] . '</amount>';
                            $output .= '<transaction_receive>' . $transaction['transaction_sent'] . '</transaction_receive>';
                            $output .= '<transaction_request>' . $transaction['transaction_request'] . '</transaction_request>';
                            $output .= '<transaction_response>' . $transaction['transaction_response'] . '</transaction_response>';
                        $output .= '</order>';
                    }
                    $output .= '</orders>';
                }
                else {
                    $status = 'Failure';
                    $message = 'No record found';
                }
            }
            else {
                $status = 'Failure';
                $message = $data['message'];
            }

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
