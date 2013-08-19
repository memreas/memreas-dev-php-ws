<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ChkUname {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('memreasdevdb');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        $data = simplexml_load_string($_POST['xml']);
//print_r($data);

        $username = trim($data->checkusername->username);

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        if (isset($username) && !empty($username)) {
            $query = "SELECT * FROM user where username = '$username'";
            $statement = $this->dbAdapter->createStatement($query);
            $result = $statement->execute();
            $row = $result->current();

            if (!empty($row)) {
                $status = 'Success';
                $message = 'Username is taken';
                $isexist = 'Yes';
            } else {
                $status = 'Failure';
                $message = 'Username is not taken';
                $isexist = 'No';
            }
        } else {
            $status = 'Failure';
            $message = 'User name field is Empty';
            $isexist = 'No';
        }
        $xml_output.="<checkusernameresponse>";
        $xml_output.="<status>$status</status>";
        $xml_output.="<message>$message</message>";
        $xml_output.="<isexist>$isexist</isexist>";
        $xml_output.="</checkusernameresponse>";
        $xml_output.="</xml>";

        echo $xml_output;
    }

}

?>
