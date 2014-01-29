<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;

class CreateGroup {

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
//echo "hi<pre>";
//print_r($data);
        $message = ' ';
        $status = '';
        $group_name = trim($data->creategroup->group_name);
        $user_id = trim($data->creategroup->user_id);
        $friends = $data->creategroup->friends->friend;
        $time = time();
        if (!isset($group_name) || empty($group_name)) {
            $status = 'Failure';
            $message = 'Group name is empty';
        } else if (!isset($user_id) || empty($user_id)) {
            $status = 'Failure';
            $message = 'User id  is empty';
        } else {
            $group_id = MUUID::fetchUUID();

            $tblGroup = new \Application\Entity\Group();
            $tblGroup->group_id = $group_id;
            $tblGroup->user_id = $user_id;
            $tblGroup->group_name = $group_name;
            $tblGroup->create_date = $time;
            $tblGroup->update_date = $time;


            $this->dbAdapter->persist($tblGroup);
            $this->dbAdapter->flush();
            $status = 'Success';
            $message = 'New Group created';

            /* $query_group = "INSERT INTO Application\Entity\Group (group_id,`user_id` ,
              `group_name` ,
              `create_date` ,
              `update_date`
              ) VALUES ('$group_id','$user_id','$group_name','$time','$time')";
             * 
             */
            //$result_group = mysql_query($query_group);
            // $statement = $this->dbAdapter->createStatement($query);
            //   $result_group = $statement->execute();
            //   $row = $result_group->current();
            // $statement = $this->dbAdapter->createQuery($query_group);
            //$result_group = $statement->getResult();


            foreach ($friends as $key => $friend) {
                $friend_name = trim($friend->friend_name);
                $network_name = trim($friend->network_name);
                $url = trim($friend->profile_pic_url);
                //----------------------------check is friend in friends table
                $query_frind = "SELECT f.friend_id FROM Application\Entity\Friend f  where f.social_username='$friend_name' and f.network='$network_name'";
                //$result_friend = mysql_query($query_frind);
                //  $statement1 = $this->dbAdapter->createStatement($query_frind);
                //  $result_friend = $statement1->execute();
                // $row = $result_friend->current();
                $statement = $this->dbAdapter->createQuery($query_frind);
                $result_friend = $statement->getResult();

                if (!$result_friend) {
                    $friend_id = MUUID::fetchUUID();
                    $tblFriend = new \Application\Entity\Friend();
                    $tblFriend->friend_id = $friend_id;
                    $tblFriend->network = $network_name;
                    $tblFriend->social_username = $friend_name;
                    $tblFriend->url_image = $url;
                    $tblFriend->create_date = $time;
                    $tblFriend->update_date = $time;
                    $this->dbAdapter->persist($tblFriend);
                    $this->dbAdapter->flush();
                    // $insert_friend = "insert into Application\Entity\Friend(friend_id,`network`, `social_username`, `url_image`, `create_date`, `update_date`)
                    //            values('$friend_id','$network_name','$friend_name','$url','$time','$time')";
                    // $result_friends = mysql_query($insert_friend) or die(mysql_error());
                    //  $statement2 = $this->dbAdapter->createStatement($insert_friend);
                    //   $result_friend = $statement2->execute();
                    // $row = $result_friend->current();
                    //    $statement = $this->dbAdapter->createQuery($insert_friend);
                    //$result_friend = $statement->getResult();
                } else {
                    $row = array_pop($result_friend);
                    $friend_id = $row['friend_id'];
                }
                $q1 = "select f from Application\Entity\UserFriend f where f.user_id='$user_id' and f.friend_id='$friend_id'";
                //$r1 = mysql_query($q1) or die(mysql_error());
                //  $statement3 = $this->dbAdapter->createStatement($q1);
                //$r1 = $statement3->execute();
                // $row = $r1->current();
                $statement = $this->dbAdapter->createQuery($q1);
                $UserFriend = $statement->getResult();


                if (!$UserFriend) {

                    $tblUserFriend = new \Application\Entity\UserFriend();
                    $tblUserFriend->friend_id = $friend_id;
                    $tblUserFriend->user_id = $user_id;
                    $this->dbAdapter->persist($tblUserFriend);
                    $this->dbAdapter->flush();
                    //   $inset_user_friend = " INSERT INTO Application\Entity\UserFriend (user_id` ,`friend_id`)VALUES ('$user_id','$friend_id')";
                    //  $result_user_friend = mysql_query($inset_user_friend) or die(mysql_error());
                    //     $statement3 = $this->dbAdapter->createStatement($inset_user_friend);
                    // $result_user_friend = $statement3->execute();
                    // $row = $result_user_friend->current();
                    //    $statement = $this->dbAdapter->createQuery($inset_user_friend);
                    //$result_user_friend = $statement->getResult();
                }

                $tblFriendGroup = new \Application\Entity\FriendGroup();
                $tblFriendGroup->friend_id = $friend_id;
                $tblFriendGroup->group_id = $group_id;
                $this->dbAdapter->persist($tblFriendGroup);
                $this->dbAdapter->flush();

                // $query_friend_group = "insert into Application\Entity\FriendGroup values('$group_id','$friend_id')";
                // $result_f_g = mysql_query($query_friend_group);
                //  $statement4 = $this->dbAdapter->createStatement($query_friend_group);
                //   $result_f_g = $statement4->execute();
                // $row = $result_f_g->current();
                //   $statement = $this->dbAdapter->createQuery($query_friend_group);
                //  $result_f_g = $statement->getResult();
            }
        }
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";

        $xml_output.= "<creategroupresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>$message</message>";

        $xml_output.= "</creategroupresponse>";
        $xml_output.= "</xml>";
        echo $xml_output;
    }

}

?>
