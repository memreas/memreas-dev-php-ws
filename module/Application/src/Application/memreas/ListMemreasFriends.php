<?php
/*
* Get List of memreas friends
* @params: null
* @Return
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Friend;
use Application\Entity\UserFriend;

class ListMemreasFriends {
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $url_signer;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
        $this->url_signer = new MemreasSignedURL ();
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
        $user_id = trim ( $data->listmemreasfriends->user_id );

        if(empty($user_id)){
            $status = "Failure";
            $message = "No data available to this user";

        } else {

        

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select ( 'f' );
        $qb->from ( 'Application\Entity\Friend', 'f' );
        $qb->where ( "LOWER(f.network)='memreas'" );

        $qb->join('Application\Entity\UserFriend', 'uf', 'WITH', 'uf.friend_id = f.friend_id')
                ->andwhere("uf.user_approve = '1'")
                ->andwhere("uf.user_id = :userid")
                ->setParameter ( 'userid', $user_id );


               // error_log("dql ---> ".$qb->getQuery()->getSql().PHP_EOL);     

        $result = $qb->getQuery ()->getResult ();
        if (empty($result)) {
            $status = "Failure";
            $message = "No data available to this user";
        } else {
            $status = 'Success';
            $output .= '<friends>';
            foreach ($result as $friend){
                $output .= '<friend>';
                $output .= '<friend_id>' . $friend->friend_id . '</friend_id>';
                $output .= '<friend_name>' . $friend->social_username . '</friend_name>';
                $output .= '<photo>' . $this->url_signer->fetchSignedURL ($friend->url_image) . '</photo>';
                $output .= '</friend>';
            }
            $output .= '</friends>';
        }
    }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listmemreasfriendsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</listmemreasfriendsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
