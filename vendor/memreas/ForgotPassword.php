<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ForgotPassword {

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
       
function is_valid_email($email) {
    $result = TRUE;
    if (!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email)) {
        $result = FALSE;
    }
    return $result;
}

//require 'config.php';
$data = simplexml_load_string($_POST['xml']);
$email = trim($data->forgotpassword->email);
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$xml_output .= "<forgotpasswordresponse>";
if (isset($email) && !empty($email)) {
    $checkvalidemail = is_valid_email($email);
    if ($checkvalidemail == TRUE) {
        $query = "SELECT * FROM user where email_address='" . $email . "' and role = 2 and disable_account = 0";
       // $result = mysql_query($query);
        $statement = $this->dbAdapter->createStatement($query);
            $result = $statement->execute();
            //$row = $result->current();

        if ($result->count() > 0) {
            $data = mysql_fetch_array($result);
            $username = $email;
            $to = $email;
            $pass = mt_rand(10000, 999999);
            $password = md5($pass);
            $updatequr = "UPDATE user set password ='" . $password . "' where user_id='" . $data['user_id']."'";
          //  $resofupd = mysql_query($updatequr);
            $statement1 = $this->dbAdapter->createStatement($updatequr);
            $resofupd = $statement1->execute();
           // $row = $result->current();

            if ($resofupd) {
                $subject = "Welcome to Event App";
                $message = "<p>Hello " . $data['username'] . ",</p>";
                $message .= "<p>Welcome to Event App</p>";
                $message .= "<p>Your username is: " . $data['username'] . "</p>";
                $message .= "<p>Your Password is: " . $pass . "</p>";
                $message .= "<p>Thanks and Regards,</p>";
                $message .= "<p><b>Event App Team</b></p>";
                $headers = "MIME-Version: 1.0" . "\r\n";
                $headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
                $headers .= 'From: <admin@eventapp.com>' . "\r\n";
                $s = mail($to, $subject, $message, $headers);
                if ($s) {

                    $xml_output .= "<status>success</status>";
                    $xml_output .= "<message>Your password is send to your email address successfully.</message>";
                } else {
                    $xml_output .= "<status>failure</status>";
                    $xml_output .= "<message>Error occur in sending email. Please try again.</message>";
                }
            } else {
                $xml_output .= "<status>failure</status>";
                $xml_output .= "<message>Error occur in password updation. Please try again.</message>";
            }
        } else {
            $xml_output .= "<status>failure</status>";
            $xml_output .="<message>Incorrect email address or your account not active.</message>";
        }
    } else {
        $xml_output .= "<status>failure</status>";
        $xml_output .="<message>Please enter valid email address.</message>";
    }
} else {
    $xml_output .= "<status>failure</status>";
    $xml_output .="<message>Please check that email address is given.</message>";
}
$xml_output .="</forgotpasswordresponse>";
$xml_output .="</xml>";
echo $xml_output;

    }

}

?>
