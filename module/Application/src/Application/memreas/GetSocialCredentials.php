<?php
/*
* Get user's details service
* @params: user_id Provide user id to get back detail
* @Return User information detail
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class GetSocialCredentials {
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

        /*
        * PRE-CONFIG HERE
        */
        //Facebook's Credentials
        $fb_appId = '462180953876554';
        $fb_appSecret = '23dcd2db19b17f449f39bfe9e93176e6';

        //Twitter's Credentials
        $tw_appid = 'XjWz7d8AIh0hq6mDqjR7mA';
        $tw_appSecret = 'wlF52rzjDCtrFNzZ8lJRgMU9Fd4aOagkqLclomXOYg';


        $network = $data->getsocialcredentials->network;
        switch ($network){
            case 'facebook':
                $output = '<facebook>';
                $output .= '<appid>' . $fb_appId . '</appid>';
                $output .= '<secret>' . $fb_appSecret . '</secret>';
                $output .= '</facebook>';
                break;
            case 'twitter':
                $output = '<twitter>';
                $output .= '<appid>' . $tw_appid . '</appid>';
                $output .= '<secret>' . $tw_appSecret . '</secret>';
                $output .= '</twitter>';
                break;

            default:
                $output = '<facebook>';
                $output .= '<appid>' . $fb_appId . '</appid>';
                $output .= '<secret>' . $fb_appSecret . '</secret>';
                $output .= '</facebook>';
                $output .= '<twitter>';
                $output .= '<appid>' . $tw_appid . '</appid>';
                $output .= '<secret>' . $tw_appSecret . '</secret>';
                $output .= '</twitter>';
                break;
        }

        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<getsocialcredentialsresponse>";
        $xml_output .= "<status>Success</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</getsocialcredentialsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
