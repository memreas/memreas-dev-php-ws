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
         * 5-OCT-2014 - Change to reference constants file...
         */

        //Facebook's Credentials
        $fb_appId = MemreasConstants::FB_APPID;
        $fb_appSecret = MemreasConstants::FB_SECRET;;

        //Twitter's Credentials
        /*
         * TODO: Why is this different than constants file?
         */
        //const TW_CONSUMER_KEY ='9jwg1vX4MgH7rfBzxqkcjI90f';
        //const TW_CONSUMER_SECRET = 'bDqOeHkJ7OIQ4QPNnT1PA9oz55gf51YW0REBo12aazGA0CBrbY';
        //$tw_appid = 'XjWz7d8AIh0hq6mDqjR7mA';
        //$tw_appSecret = 'wlF52rzjDCtrFNzZ8lJRgMU9Fd4aOagkqLclomXOYg';
        $tw_appid = MemreasConstants::TW_CONSUMER_KEY;
        $tw_appSecret = MemreasConstants::TW_CONSUMER_SECRET;;
        

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
