<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

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
        $this->dbAdapter = $service_locator->get('memreasdevdb');
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
    $group_id=  getUUID();
    $query_group = "INSERT INTO `group` (group_id,`user_id` ,
                                        `group_name` ,
                                        `create_date` ,
                                        `update_date`
                                        ) VALUES ('$group_id','$user_id','$group_name','$time','$time')";
    //$result_group = mysql_query($query_group);
     $statement = $this->dbAdapter->createStatement($query);
            $result_group = $statement->execute();
            $row = $result_group->current();

    if (!$result_group) {
        $status = 'Failure';
        $message = mysql_error();
    } else {

        foreach ($friends as $key => $friend) {
            $friend_name = trim($friend->friend_name);
            $network_name = trim($friend->network_name);
            $url = trim($friend->profile_pic_url);
            //----------------------------check is friend in friends table
            $query_frind = "SELECT friend_id FROM friend where social_username='$friend_name' and network='$network_name'";
            //$result_friend = mysql_query($query_frind);
             $statement1 = $this->dbAdapter->createStatement($query_frind);
            $result_friend = $statement1->execute();
            $row = $result_friend->current();

            if (!$result_friend) {
                $status = 'Failure';
                $message = mysql_error();
            } else {
                if ($result_friend->count() == 0) {
                    $friend_id = getUUID();
                    $insert_friend = "insert into friend(friend_id,`network`, `social_username`, `url_image`, `create_date`, `update_date`)
                                values('$friend_id','$network_name','$friend_name','$url','$time','$time')";
                   // $result_friends = mysql_query($insert_friend) or die(mysql_error());
                     $statement2 = $this->dbAdapter->createStatement($insert_friend);
            $result_friend = $statement2->execute();
            $row = $result_friend->current();

                    
                } else if ($row = mysql_fetch_assoc($result_friend)) {
                    $friend_id = $row['friend_id'];
                }
                $q1 = "select * from user_friend where user_id='$user_id' and friend_id='$friend_id'";
                //$r1 = mysql_query($q1) or die(mysql_error());
                 $statement3 = $this->dbAdapter->createStatement($q1);
            $r1 = $statement3->execute();
            $row = $r1->current();

                if ($r1->count() <= 0) {
                    $inset_user_friend = " INSERT INTO `user_friend` (`user_id` ,`friend_id`)VALUES ('$user_id','$friend_id')";
                  //  $result_user_friend = mysql_query($inset_user_friend) or die(mysql_error());
                     $statement3 = $this->dbAdapter->createStatement($inset_user_friend);
            $result_user_friend = $statement3->execute();
            $row = $result_user_friend->current();

                }
                $query_friend_group = "insert into friend_group values('$group_id','$friend_id')";
                $result_f_g = mysql_query($query_friend_group);
                 $statement4 = $this->dbAdapter->createStatement($query_friend_group);
            $result_f_g = $statement4->execute();
            $row = $result_f_g->current();

                if (!$result_f_g) {
                    $status = 'Failure';
                    $message = mysql_error();
                } else {
                    $status = 'Success';
                    $message = 'New Group created';
                }
            }
        }
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
