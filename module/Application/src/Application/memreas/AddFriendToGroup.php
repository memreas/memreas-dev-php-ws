<?php
/*
* Add friend to user group
* @params:
* @Return
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Friend;
use Application\Entity\Group;
use Application\Entity\FriendGroup;

class AddFriendToGroup{
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
    }


    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty ( $frmweb )) {
            $data = simplexml_load_string ( $_POST ['xml'] );
        } else {
            $data = json_decode ( json_encode ( $frmweb ) );
        }

        $group_id = $data->addfriendtogroup->group_id;
        $network = $data->addfriendtogroup->network;
        $friends = $data->addfriendtogroup->friends;

        //Check if group exist or not
        $group_query = $this->dbAdapter->createQueryBuilder();
        $group_query->select('g')
                    ->from('Application\Entity\Group', 'g')
                    ->where('g.group_id = ?1')
                    ->setParameter(1, $group_id);
        $result = $group_query->getQuery()->getResult();
        if (empty($result)){
            $status = 'Failure';
            $message = 'This group does not exist';
        }
        else{
            foreach ($friends->friend as $friend){
                $friend_name = $friend->friend_name;
                $friend_photo = $friend->profile_pic_url;

                //Check if friend has existed or not
                $query = $this->dbAdapter->createQueryBuilder();
                $query->select('f.friend_id')
                        ->from('Application\Entity\Friend', 'f')
                        ->where('f.social_username = ?1')
                        ->setParameter(1, $friend_name);
                $result = $query->getQuery()->getResult();
                if (empty($result)){

                    $friend_id = MUUID::fetchUUID();
                    $current_time = time();

                    //Create friend db if not exist
                    $friendDbInstance = new \Application\Entity\Friend();
                    $friendDbInstance->friend_id = $friend_id;
                    $friendDbInstance->network = $network;
                    $friendDbInstance->social_username = $friend_name;
                    $friendDbInstance->url_image = $friend_photo;
                    $friendDbInstance->create_date = $current_time;
                    $friendDbInstance->update_date = $current_time;
                    $this->dbAdapter->persist ($friendDbInstance);
                    $this->dbAdapter->flush ();
                }
                else $friend_id = $result[0]['friend_id'];

                //Check if friend has already added to group before yet
                $query_group = $this->dbAdapter->createQueryBuilder();
                $query_group->select('fg')
                        ->from('Application\Entity\FriendGroup', 'fg')
                        ->where("fg.group_id = '{$group_id}' AND fg.friend_id = '{$friend_id}'");
                $result = $query_group->getQuery()->getResult();
                if (empty($result)){
                    $friendGroupDbInstance = new \Application\Entity\FriendGroup();
                    $friendGroupDbInstance->group_id = $group_id;
                    $friendGroupDbInstance->friend_id = $friend_id;
                    $this->dbAdapter->persist($friendGroupDbInstance);
                    $this->dbAdapter->flush();
                }
            }
            $status = 'Success';
            $message = 'Friends added to the group';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<addfriendtogroupresponse>";
        $xml_output .= "<status>{$status}</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= "</addfriendtogroupresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
