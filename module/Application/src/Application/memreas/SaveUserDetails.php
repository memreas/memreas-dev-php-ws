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
use Application\Entity\User;

class SaveUserDetails {
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
        $user_id = trim ( $data->saveuserdetails->user_id );
        $email = trim ( $data->saveuserdetails->email );
        $password = trim ($data->saveuserdetails->password);

        //check if exist user's email
        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select('u')
            ->from('Application\Entity\User', 'u')
            ->where("u.email_address = '{$email}' AND u.user_id <> '{$user_id}'");
        $user_info = $qb->getQuery ()->getResult ();

        if (empty($user_info)){
            $query = "UPDATE Application\Entity\User u SET u.email_address = '{$email}'";
            if (!empty($password))
                $query .= ", u.password = '" . md5($password) . "'";
            $query .= " WHERE u.user_id = '{$user_id}'";
            $qb = $this->dbAdapter->createQuery($query);
            $result = $qb->getResult();
            if ($result){
                $status = 'Success';
                $message = 'User details updated';
            }
            else{
                $status = 'Failure';
                $message = 'Update user details failed';
            }
        }
        else{
            $status = 'Failure';
            $message = 'This email has been owned on another user';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<saveuserdetailsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</saveuserdetailsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
