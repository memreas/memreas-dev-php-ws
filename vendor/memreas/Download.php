<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class Download {

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
        $message = "";
        $status = "";
        $media_id = trim($data->download->media_id);
        $user_id = trim($data->download->user_id);
        $device_id = trim($data->download->device_id);
        $event_media = "select e from Application\Entity\EventMedia e  where e.media_id='$media_id'";
//$result_event_media = mysql_query($event_media);
//$statement = $this->dbAdapter->createStatement($event_media);
        //          $result_event_media = $statement->execute();
        //        $row = $result_event_media->current();
        $statement = $this->dbAdapter->createQuery($event_media);
        $result_event_media = $statement->getResult();

        if (count($result_event_media) == 0) {
            $status = "Failure";
            $message .= mysql_error();
        } else {
            $row = array_pop($result_event_media);
            $q_event = "select e.user_id from Application\Entity\Event e  where e.event_id='" . $row->event_id . "'";
            // $result_event = mysql_query($q_event);
            // $statement1 = $this->dbAdapter->createStatement($q_event);
            //       $result_event = $statement1->execute();
            //       $row = $result_event->current();
            $statement = $this->dbAdapter->createQuery($q_event);
            $result_event = $statement->getResult();


            if (count($result_event) == 0) {
                $status = "Failure";
                $message .= mysql_error();
            } else {
                $row1 = array_pop($result_event);
                $q_user_frind = "select f from Application\Entity\UserFriend  f where f.user_id='" . $row1['user_id'] . "' and f.friend_id='" . $user_id . "'";
                //$result_user_friend = mysql_query($q_user_frind);
                // $statement2 = $this->dbAdapter->createStatement($q_user_frind);
                //  $result_user_friend = $statement2->execute();
                //  $row = $result_user_friend->current();
                $statement = $this->dbAdapter->createQuery($q_user_frind);
                $result_user_friend = $statement->getResult();


                if (count($result_user_friend) == 0) {
                    $status = "Failure";
                    $message .= mysql_error();
                } else {

                    if (0 < count($result_user_friend)) {
                        $q_friend__media = "select m from Application\Entity\FriendMedia  m where m.friend_id='" . $user_id . "' and media_id='" . $media_id . "'";
                        //$result_friend_media = mysql_query($q_friend__media);
                        //       $statement3 = $this->dbAdapter->createStatement($q_friend__media);
                        //     $result_friend_media = $statement3->execute();
                        //    $row = $result_friend_media->current();
                        $statement = $this->dbAdapter->createQuery($q_friend__media);
                        $result_friend_media = $statement->getResult();


                        if (count($result_friend_media) == 0) {
                            $status = "Failure";
                            $message .= mysql_error();
                        } else if ($result_friend_media <= 0) {

                            $tblFriendMedia = new \Application\Entity\FriendMedia();
                            $tblFriendMedia->user_id = $user_id;
                            $tblFriendMedia->media_id = $media_id;
                            $this->dbAdapter->persist($tblFriendMedia);
                            $this->dbAdapter->flush();




                            //$q_friend__media_insert = "insert into Application\Entity\FriendMedia  values('$user_id','$media_id')";
                            // $result_f_m = mysql_query($q_friend__media_insert);
                            //  $statement4 = $this->dbAdapter->createStatement($q_friend__media_insert);
                            // $result_f_m = $statement4->execute();
                            //  $row = $result_f_m->current();
                            //        $statement = $this->dbAdapter->createQuery($q_friend__media_insert);
                            //      $result_f_m = $statement->getResult();



                            $status = "Success";
                            $message.="Media Friend Successfully Updated and ";
                        }
                    }
                }
            }
        }

//--------------------------------------------------------

        $q = "SELECT m FROM Application\Entity\Media m  where  m.media_id='$media_id'";
//$r = mysql_query($q) or die(mysql_error());
//$statement4 = $this->dbAdapter->createStatement($q);
        //          $r = $statement4->execute();
        //        $row = $r->current();
        $statement = $this->dbAdapter->createQuery($q);
        $r = $statement->getResult();

        

        if (isset($r[0])) {
            $count = 0;
            $flag = 0;
            if (strlen($r[0]->metadata)> 10) {
                $json_array = json_decode($r[0]->metadata, true);
                $device = $json_array['local_filenames']['device'];
                foreach ($device as $key => $value) {
                    $count++;
                    if (strcasecmp($value, $user_id . '_' . $device_id) == 0)
                        $flag = 1;
                }
                if ($flag == 0) {
                    $count++;
                    $new_key = 'unique_device_identifier' . $count;
                    $json_array['local_filenames']['device'][$new_key] = $user_id . '_' . $device_id;
                    $json_str = json_encode($json_array);
                    $update = "UPDATE Application\Entity\Media m  SET  m.metadata ='$json_str' WHERE m.media_id ='$media_id'";
                    //$result = mysql_query($update)
                    //  $statement6 = $this->dbAdapter->createStatement($update);
                    //     $result = $statement6->execute();
                    //    $row = $result->current();
                    $statement = $this->dbAdapter->createQuery($update);
                    $result = $statement->getResult();


                    if ($result) {
                        $status = "Success";
                        $message .= "Successfully updated";
                    } else {
                        $status = "Failure";
                        $message .= mysql_error();
                    }
                } else {
                    $status = "Failure";
                    $message .= "This media is already set downloaded";
                }
            } else {
                $status = "Failure";
                $message .= "Metada date is empty";
            }
        }

        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";

        $xml_output.= "<downloadresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>$message</message>";
        $xml_output.= "</downloadresponse>";
        $xml_output.= "</xml>";
        echo $xml_output;
    }

}

?>
