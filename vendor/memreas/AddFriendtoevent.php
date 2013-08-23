<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;
class AddFriendtoevent {

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

//$friend_array =addslashes(trim($data->addfriendtoevent->friends->friend));
$friend_array = $data->addfriendtoevent->friends->friend;
$user_id = (trim($data->addfriendtoevent->user_id));
$event_id = (trim($data->addfriendtoevent->event_id));
$group_array = $data->addfriendtoevent->groups;
$status = "";
$message = "";
$message1 ="";
$time = time();
$error = 0;
//echo "<pre>f=";
//print_r($group_array);exit;
//print_r($data);
//foreach ($friend_array as $key=>$value)
//{
//    echo $key.'=>';print_r($value);
//}exit;
//$count = 0;
foreach ($group_array as $key => $value) {
//    echo $count++;
    //event check group already exist
//    echo "$key => ";
//    echo "hi";
//    print_r($value->group);
//    exit;
    $group_id = $value->group->group_id;
    $check = "SELECT * FROM `event_group` where event_id='" . $event_id . "' and group_id='" . $group_id . "'";
//    exit;
   // $result_check = mysql_query($check) or die(mysql_error());
    $statement = $this->dbAdapter->createStatement($check);
				$result_check = $statement->execute();
    if ($result_check->count() > 0) {
        $message1 = 'event group already exist.';
        $status = 'Failure';
    } else {//insert
        $q = "INSERT INTO event_group (event_id ,group_id) VALUES ('" . $event_id . "', '" . $group_id . "')";
       // $rs = mysql_query($q) or die(mysql_error());
        $statement = $this->dbAdapter->createStatement($q);
				$rs= $statement->execute();
        if (!$rs) {
            $message1=mysql_error();
            $status = 'Failure';
            $error = 1;
        } else {
            $message1 = 'event group Successfully added ';
            $status = 'Success';
        }
    }
}//echo $message;
foreach ($friend_array as $key => $value) {
    $network_name = addslashes(trim($value->network_name));
    $friend_name = addslashes(trim($value->friend_name));
    $profile_pic_url = stripslashes(trim($value->profile_pic_url));
    $friend_query = "select friend_id 
                    from friend 
                    where network='$network_name' and social_username='$friend_name'";
    //$result_friend = mysql_query($friend_query) or die(mysql_error());
    $statement = $this->dbAdapter->createStatement($friend_query);
				$result_friend = $statement->execute();
    if ($row = $result_friend->current()) {
        $friend_id = $row['friend_id'];
    } else {
        ////
        $friend_id =UUID::getUUID($this->dbAdapter);
        $insert_q = "INSERT INTO friend(friend_id,`network` ,
                                    `social_username` ,
                                    `url_image` ,
                                    `create_date` ,
                                    `update_date`
                                    )VALUES ('$friend_id',
                            '$network_name',
                            '$friend_name',
                            '$profile_pic_url',
                            '$time',
                            '$time')";
        //$result_friend_insert = mysql_query($insert_q);
        $statement = $this->dbAdapter->createStatement($insert_q);
				$result_friend_insert = $statement->execute();

        if (!$result_friend_insert) {
            $message.=mysql_error();
            $status = 'failure';
            $error = 1;
        }
    }
    if (isset($friend_id) && !empty($friend_id)) {
        $check_user_frind = "SELECT * FROM `user_friend` where user_id='$user_id' and friend_id='$friend_id'";
       // $r = mysql_query($check_user_frind) or die(mysql_error());
        $statement = $this->dbAdapter->createStatement($check_user_frind);
				$r = $statement->execute();
        if ($r->count() > 0) {
            $status = "success";
            $message .= $friend_name." is already in your friend list. ";
        } else {
            $user_friend_insert = "INSERT INTO `user_friend` (
                                                    `user_id` ,
                                                    `friend_id`
                                                    )
                                                    VALUES (
                                                    '$user_id', '$friend_id'
                                                    )";
          //  $result_friend_insert1 = mysql_query($user_friend_insert);
            $statement = $this->dbAdapter->createStatement($user_friend_insert);
				$result_friend_insert1 = $statement->execute();
            if (!$result_friend_insert1) {
                $message.=mysql_error();
                $status = 'Failure';
                $error = 1;
            } else {
                $message = 'Friend Successfully added. ';
                $status = 'Success';
            }
        }
    }

    if (!empty($event_id) && $error == 0) {//echo "hi";
        $check_event_frind = "SELECT * FROM event_friend where event_id='$event_id' and friend_id='$friend_id'";
        //$r = mysql_query($check_event_frind);
        $statement = $this->dbAdapter->createStatement($check_event_frind);
				$r = $statement->execute();
        if ($r->count() > 0) {
            $status = "Success";
            $message .= "$friend_name is already in your Event Friend list.";
        } else {
            $query_event_friend = "insert into event_friend values('$event_id','$friend_id')";
           // $result_event_friend = mysql_query($query_event_friend);
            $statement = $this->dbAdapter->createStatement($query_event_friend);
				$result_event = $statement->execute();
            if (!$result_event_friend) {
                $message.=mysql_error();
                $status = 'failure';
            } else {
                $message .= 'Event Friend Successfully added';
                $status = 'Success';
            }
        }
    }
}

header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$xml_output.= "<addfriendtoeventresponse>";
$xml_output.= "<status>$status</status>";
$xml_output.= "<message>".$message." And ".$message1."</message>";
$xml_output.= "<event_id>$event_id</event_id>";
$xml_output.= "</addfriendtoeventresponse>";
$xml_output.= "</xml>";
echo $xml_output;
    }

}

?>
