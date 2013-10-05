<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;
use memreas\gcm;


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
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        if(!$this->AddNotification){
            $this->AddNotification = new AddNotification($message_data, $memreas_tables, $service_locator);
        }
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
        $message1 = "";
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
        //add group to event_group
        foreach ($group_array as $key => $value) {
//    echo $count++;
            //event check group already exist
//    echo "$key => ";
//    echo "hi";
//    print_r($value->group);
//    exit;
            $group_id = $value->group->group_id;
            $check = "SELECT e  FROM  Application\Entity\EventGroup e  where e.event_id='" . $event_id . "' and e.group_id='" . $group_id . "'";
//    exit;
            // $result_check = mysql_query($check) or die(mysql_error());
            //$statement = $this->dbAdapter->createStatement($check);
            //	$result_check = $statement->execute();
            $statement = $this->dbAdapter->createQuery($check);
            $result_check = $statement->getResult();




            if ($result_check->count() > 0) {
                $message1 = 'event group already exist.';
                $status = 'Failure';
            } else {//insert
                $tblEventGroup = new \Application\Entity\EventGroup();
                $tblEventGroup->group_id = $group_id;
                $tblEventGroup->event_id = $event_id;

                $this->dbAdapter->persist($tblEventGroup);
                $this->dbAdapter->flush();


                // $q = "INSERT INTO Application\Entity\EventGroup (event_id ,group_id) VALUES ('" . $event_id . "', '" . $group_id . "')";
                // $rs = mysql_query($q) or die(mysql_error());
                // $statement = $this->dbAdapter->createStatement($q);
                //	$rs= $statement->execute();
                // $statement = $this->dbAdapter->createQuery($q);
                // $rs = $statement->getResult();

                if (!$rs) {
                    $message1 = mysql_error();
                    $status = 'Failure';
                    $error = 1;
                } else {
                    $message1 = 'event group Successfully added ';
                    $status = 'Success';
                }
            }
        }//echo $message;
        //add friends to event loop
        foreach ($friend_array as $key => $value) {
            $network_name = addslashes(trim($value->network_name));
            $friend_name = addslashes(trim($value->friend_name));
            $profile_pic_url = stripslashes(trim($value->profile_pic_url));
            $friend_query = "select f.friend_id 
                    from Application\Entity\Friend f
                    where f.network='$network_name' and f.social_username='$friend_name'";
            //$result_friend = mysql_query($friend_query) or die(mysql_error());
            //  $statement = $this->dbAdapter->createStatement($friend_query);
            //		$result_friend = $statement->execute();
            $statement = $this->dbAdapter->createQuery($friend_query);
            $result_friend = $statement->getResult();
             // add to friend
            if ($row = $result_friend->current()) {
                $friend_id = $row['friend_id'];
            } else {
                ////
                $friend_id = UUID::getUUID($this->dbAdapter);

                $tblFriend = new \Application\Entity\Friend();
                $tblFriend->friend_id = $friend_id;
                $tblFriend->network = $network_name;
                $tblFriend->social_username = $friend_name;
                $tblFriend->url_image = $profile_pic_url;
                $tblFriend->create_date = $time;
                $tblFriend->update_date = $time;



                try {
                    $this->dbAdapter->persist($tblFriend);
                    $this->dbAdapter->flush();
                } catch (\Exception $exc) {
                    $status = 'failure';
                    $error = 1;
                }


                /* $insert_q = "INSERT INTO Application\Entity\Friend(friend_id,`network` ,
                  `social_username` ,
                  `url_image` ,
                  `create_date` ,
                  `update_date`
                  )VALUES ('$friend_id',
                  '$network_name',
                  '$friend_name',
                  '$profile_pic_url',
                  '$time',
                  '$time')"; */
                //$result_friend_insert = mysql_query($insert_q);
                //  $statement = $this->dbAdapter->createStatement($insert_q);
                //		$result_friend_insert = $statement->execute();
                //  $statement = $this->dbAdapter->createQuery($insert_q);
                //$result_friend_insert = $statement->getResult();


                if (!$result_friend_insert) {
                    $message.=mysql_error();
                    $status = 'failure';
                    $error = 1;
                }
            }
            // add to user_friend
            if (isset($friend_id) && !empty($friend_id)) {
                $check_user_frind = "SELECT u  FROM  Application\Entity\UserFriend u where u.user_id='$user_id' and u.friend_id='$friend_id'";
                // $r = mysql_query($check_user_frind) or die(mysql_error());
                //    $statement = $this->dbAdapter->createStatement($check_user_frind);
                //			$r = $statement->execute();
                
                $statement = $this->dbAdapter->createQuery($check_user_frind);
                $r = $statement->getResult();

                if (count($r) > 0) {
                    $status = "success";
                    $message .= $friend_name . " is already in your friend list. ";
                } else {

                    $tblUserFriend = new \Application\Entity\UserFriend();
                    $tblUserFriend->friend_id = $friend_id;
                    $tblUserFriend->user_id = $user_id;


                    try {
                        $this->dbAdapter->persist($tblUserFriend);
                        $this->dbAdapter->flush();

                        $message .= 'Event Friend Successfully added';
                        $status = 'Success';
                    } catch (\Exception $exc) {
                        $status = 'Failure';
                        $error = 1;
                    }


                    /* $user_friend_insert = "INSERT INTO Application\Entity\UserFriend (
                      `user_id` ,
                      `friend_id`
                      )
                      VALUES (
                      '$user_id', '$friend_id'
                      )"; */
                    //  $result_friend_insert1 = mysql_query($user_friend_insert);
                    //  $statement = $this->dbAdapter->createStatement($user_friend_insert);
                    //	$result_friend_insert1 = $statement->execute();
                    // $statement = $this->dbAdapter->createQuery($user_friend_insert);
                    //  $result_friend_insert1 = $statement->getResult();
//                    if (!$result_friend_insert1) {
//                        $message.=mysql_error();
//                        $status = 'Failure';
//                        $error = 1;
//                    } else {
//                        $message = 'Friend Successfully added. ';
//                        $status = 'Success';
//                    }
                }
            }
            //adding friend to event
            if (!empty($event_id) && $error == 0) {//echo "hi";
                $check_event_frind = "SELECT e FROM Application\Entity\EventFriend e  where e.event_id='$event_id' and e.friend_id='$friend_id'";
                //$r = mysql_query($check_event_frind);
                //  $statement = $this->dbAdapter->createStatement($check_event_frind);
                //	$r = $statement->execute();

                $statement = $this->dbAdapter->createQuery($check_event_frind);
                $r = $statement->getResult();

                if (count($r) > 0) {
                    $status = "Success";
                    $message .= "$friend_name is already in your Event Friend list.";
                } else {
                    //insert EventFriend
                    $tblEventFriend = new \Application\Entity\EventFriend();
                    $tblEventFriend->friend_id = $friend_id;
                    $tblEventFriend->event_id = $event_id;

                    try {
                        $this->dbAdapter->persist($tblEventFriend);
                        $this->dbAdapter->flush();
                        $message .= 'Event Friend Successfully added';
                        $status = 'Success';
                    } catch (\Exception $exc) {
                        $message.=mysql_error();
                        $status = 'failure';
                        echo $exc->getTraceAsString();
                    }



                    //$query_event_friend = "insert into Application\Entity\EventFriend values('$event_id','$friend_id')";
                    // $result_event_friend = mysql_query($query_event_friend);
                    //$statement = $this->dbAdapter->createStatement($query_event_friend);
                    //	$result_event = $statement->execute();
                    //  $statement = $this->dbAdapter->createQuery($query_event_friend);
                    // $r = $statement->getResult();
//                    if (!$result_event_friend) {
//                        
//                    } else {
//                        
//                    }
                }
                
                //save nofication intable
                
                    $data = array('addNotification' => array(
                                'user_id' => $user_id,
                                'event_id' => $event_id,
                                'table_name' => 'event_friend',
                                'id' => $event_id,
                                'meta' => $friend_name .'want to add you',
                                )
                        
                        
                    );
                    
                    $this->AddNotification->exec($data);
                               
                    //send push message
                    if(trim($value->network_name == 'memreas')){
                        $get_user_device = "SELECT d  FROM  Application\Entity\Device u where u.user_id='$user_id'";
                        $statement = $this->dbAdapter->createQuery($get_user_device);
                        $r = $statement->getOneOrNullResult();
                        gcm::sendpush($data['mrta'], $r->device_token);

                    }
                	 
            }
           
        }
        //add friends to event loop end
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output.= "<addfriendtoeventresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>" . $message . " And " . $message1 . "</message>";
        $xml_output.= "<event_id>$event_id</event_id>";
        $xml_output.= "</addfriendtoeventresponse>";
        $xml_output.= "</xml>";
        echo $xml_output;
    }

}

?>
