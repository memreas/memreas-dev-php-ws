<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\UUID;
use Application\memreas\gcm;

class AddFriendtoevent {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $notification;
    protected $AddNotification;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        if (!$this->AddNotification) {
            $this->AddNotification = new AddNotification($message_data, $memreas_tables, $service_locator);
        }
        if (!$this->notification) {
            $this->notification = new Notification($service_locator);
        }
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {


        $data = simplexml_load_string($_POST['xml']);


        $friend_array = $data->addfriendtoevent->friends->friend;
        $user_id = (trim($data->addfriendtoevent->user_id));
        $event_id = (trim($data->addfriendtoevent->event_id));
        $group_array = $data->addfriendtoevent->groups;
        $status = "";
        $message = "";
        $message1 = "";
        $time = time();
        $error = 0;

        //add group to event_group
        foreach ($group_array as $key => $value) {
            $group_id = $value->group->group_id;
            $check = "SELECT e  FROM  Application\Entity\EventGroup e  where e.event_id='" . $event_id . "' and e.group_id='" . $group_id . "'";

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
                $message1 = 'event group Successfully added ';
                $status = 'Success';
            }
        }
        
        //get user name
        
        //add friends to event loop
        foreach ($friend_array as $key => $value) {
            $network_name = addslashes(trim($value->network_name));
            $friend_name = addslashes(trim($value->friend_name));
            $profile_pic_url = stripslashes(trim($value->profile_pic_url));
            $friend_query = "select f.friend_id ,f.network
                    from Application\Entity\Friend f
                    where f.network='$network_name' and f.social_username='$friend_name'";
            $statement = $this->dbAdapter->createQuery($friend_query);
            $result_friend = $statement->getOneOrNullResult();
            // add to friend
            if ($result_friend) {
               
                $friend_id = $result_friend['friend_id'];
                $network_name = $result_friend['network'];
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

            }
            // add to user_friend
            if (isset($friend_id) && !empty($friend_id)) {
                $check_user_frind = "SELECT u  FROM  Application\Entity\UserFriend u where u.user_id='$user_id' and u.friend_id='$friend_id'";
                
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


               
                }
            }
            //adding friend to event
            if (!empty($event_id) && $error == 0) {
                $check_event_frind = "SELECT e FROM Application\Entity\EventFriend e  where e.event_id='$event_id' and e.friend_id='$friend_id'";
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
                        $message.='';
                        $status = 'failure';
                    }
                }

                //save nofication intable
                $ndata['addNotification']['meta'] = $friend_name . ' want to add you to event';
                if($network_name == 'memreas'){
                    $ndata = array('addNotification' => array(
                        'user_id' => $user_id,
                        'meta' => $friend_name . 'want to add you to event',
                        'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT,
                        'links' => json_encode(array(
                                    'event_id' => $event_id,
                                    'from_id' => $user_id,
                                )),
                            )
                        );
                    $this->AddNotification->exec($ndata);
                    //send push message add user id
                    $this->notification->add($user_id);
                    
                }
                else{
                    $this->notification->addFriend($friend_id);
                }
                
                
                
            }
        }
        
        //set nofication data and call send method
        
            $this->notification->setMessage($ndata['addNotification']['meta']);
            $this->notification->type=\Application\Entity\Notification::ADD_FRIEND_TO_EVENT;
            $this->notification->event_id= $event_id;
                
            $this->notification->send();
        

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
