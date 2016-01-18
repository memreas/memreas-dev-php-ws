<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */

namespace Application\memreas\StripeWS;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use GuzzleHttp\Client;

class GetOrder {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        // $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
    }

    /*
     *
     */

    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty($frmweb)) {
            $data = simplexml_load_string($_POST ['xml']);
        } else {

            $data = json_decode(json_encode($frmweb));
        }
        $transaction_id = trim($data->getorder->transaction_id);

        $guzzle = new Client ();

        $response = $guzzle->post(MemreasConstants::MEMREAS_PAY_URL,  [
                                                'form_params' => [
                                                    'action' => 'getorder',
                                                    'transaction_id' => $transaction_id
                                                ]
                                                    ]
        );

        $data = json_decode($response->getBody(), true);
        $status = $data ['status'];

        if ($status == 'Success') {
            $status = 'Success';
            $order = $data ['order'];
            if (!empty($order)) {
                $output .= '<order>';
                $output .= '<transaction_id>' . $order ['transaction_id'] . '</transaction_id>';
                $output .= '<transaction_type>' . $order ['transaction_type'] . '</transaction_type>';
                $output .= '<amount>' . $order ['amount'] . '</amount>';
                $output .= '<transaction_receive>' . $order ['transaction_sent'] . '</transaction_receive>';
                $output .= '<transaction_request>' . $order ['transaction_request'] . '</transaction_request>';
                $output .= '<transaction_response>' . $order ['transaction_response'] . '</transaction_response>';
                $output .= '<user_detail>' . json_encode($data ['user']) . '</user_detail>';
                $output .= '</order>';
            } else {
                $status = 'Failure';
                $message = $data ['message'];
            }
        } else {
            $status = 'Failure';
            $message = $data ['message'];
        }

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<getorderresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message))
            $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</getorderresponse>";
        $xml_output .= "</xml>";
        echo trim($xml_output);
        die();
    }

}

?>
