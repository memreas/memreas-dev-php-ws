<?php

namespace Application\memreas; 

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\UUID;

class ChangePassword {

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

        $data = simplexml_load_string($_POST['xml']);
        $token = trim($data->changepassword->token);
        $new = trim($data->changepassword->new);
        $retype = trim($data->changepassword->retype);

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<forgotpasswordresponse>";

        if (empty($token)) {
            $xml_output .= "<status>failure</status>";
            $xml_output .= "<message>Invalid Token. Please try again.</message>";
        } else if (empty($new) || empty($retype)) {

            $xml_output .= "<status>failure</status>";
            $xml_output .= "<message>New Password and Verify password did not match </message>";
        } else if ($new != $retype) {
            $xml_output .= "<status>failure</status>";
            $xml_output .= "<message>New Password and Verify password did not match </message>";
        } else {

            $query = "SELECT u FROM Application\Entity\User u where u.forgot_token='" . $token . "' and u.role = 2 and u.disable_account = 0";
            $statement = $this->dbAdapter->createQuery($query);
            $result = $statement->getOneOrNullResult();
            if (count($result) > 0) {
                    $pass =md5( $new);
              $updatequr = "UPDATE Application\Entity\User u  set u.forgot_token = '', u.password ='" . $pass . "' where u.user_id='" .$result->user_id . "'";
                $statement = $this->dbAdapter->createQuery($updatequr);
                $resofupd = $statement->getResult();

                /* if ($resofupd) {
                  $token =uniqid();
                  $subject = "Welcome to Event App";
                  $message = "<p>Hello " . $data->username . ",</p>";
                  $message .= "<p>Welcome to Event App</p>";
                  $message .= "<p>Your Password recovery  Token is: " .$token . "</p>";
                  //$message .= "<p>Your Password is: " . $pass . "</p>";
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
                 */
            } else {
                $xml_output .= "<status>failure</status>";
                $xml_output .="<message>Incorrect Activation code.</message>";
            }
        }



        $xml_output .="</forgotpasswordresponse>";
        $xml_output .="</xml>";
        echo $xml_output;
    }

}

?>
