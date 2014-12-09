<?php
/*
* Get user's group friend
* @params: group_id Provide user id to get back detail
* @params: group_network select group network: facebook/twitter/memreas
* @Return Friend list
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\FriendGroup;
use Application\Entity\Friend;

class  GetGroupFriends{
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
        $group_id = trim ( $data->getgroupfriends->group_id );
        $network = trim ($data->getgroupfriends->network);

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select ( 'f' );
        $qb->from ( 'Application\Entity\FriendGroup', 'fg' );
        $qb->join ('Application\Entity\Friend', 'f', 'WITH', 'fg.friend_id = f.friend_id');

        //Add ignore group id if group id is empty
        if (!empty($group_id)){
            $qb->where ( "fg.group_id=?1 AND f.network = '{$network}'" );
            $qb->setParameter(1, $group_id);
        }
        else $qb->where ( "f.network = '{$network}'" );

        $result_groups = $qb->getQuery ()->getResult();
        if (empty($result_groups)) {
            $status = "Failure";
            if (!empty($group_id))
                $message = "You have no friend with this group network.";
            else $message = "You have no friend with this network.";
        } else {
            $status = 'Success';
            $output .= '<friends>';
            foreach ($result_groups as $friend){
                $output .= '<friend>';
                $output .= '<friend_id>' . $friend->friend_id . '</friend_id>';
                $output .= '<friend_name>' . $friend->social_username . '</friend_name>';
                $output .= '<friend_network>' . $friend->network . '</friend_network>';
                if ($friend->network != 'memreas')
                    $output .= '<friend_photo>' . $friend->url_image . '</friend_photo>';
                else $output .= '<friend_photo>' . $this->url_signer->signArrayOfUrls($friend->url_image) . '</friend_photo>';
                $output .= '</friend>';
            }
            $output .= '</friends>';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<getgroupfriendsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</getgroupfriendsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
