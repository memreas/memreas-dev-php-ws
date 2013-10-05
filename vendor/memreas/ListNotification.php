<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ListNotification {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $error_flag = 0;
        $message = '';
        $data = simplexml_load_string($_POST['xml']);
        $userid = trim($data->listnotification->user_id);
//$device_id=trim($data->listphotos->device_id);
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listnotificationresponse>";
        if (isset($userid) && !empty($userid)) {

            $query_user_media = "SELECT m FROM Application\Entity\Notification m where m.user_id ='$userid' ORDER BY m.create_time DESC";
            $statement = $this->dbAdapter->createQuery($query_user_media);
            $result = $statement->getArrayResult();

            if (count($result) > 0) {
                $count = 0;
                $xml_output .= "<status>success</status><notifications>";
                foreach ($result as $row) {
                                            $xml_output .= "<notification>";
                    $xml_output .= "<meta>{$row['meta']}</meta>";
                    $xml_output .= "<notification_type>{$row['notification_type']}</notification_type>";
                    $xml_output .= "</notification>";
                }


 
            }
            //-----------------for users event
            if (count($result) == 0) {
                $error_flag = 2;
                $message = "no record found";
            }
            if ($error_flag) {
                $xml_output .= "<status>failure</status>";

                $xml_output .="<message>$message</message>";
            }
        } else {
            $xml_output .= "<status>failure</status>";

            $xml_output .="<message>User id is not given.</message>";
        }

        $xml_output .="</notifications></listnotificationresponse>";
        $xml_output .="</xml>";
        echo $xml_output;
    }

}

?>
