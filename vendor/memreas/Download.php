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
        $this->dbAdapter = $service_locator->get('memreasdevdb');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
        
$data = simplexml_load_string($_POST['xml']);
$message="";
$status ="";
$media_id = trim($data->download->media_id);
$user_id = trim($data->download->user_id);
$device_id = trim($data->download->device_id);
$event_media = "select * from event_media where media_id='$media_id'";
//$result_event_media = mysql_query($event_media);
$statement = $this->dbAdapter->createStatement($event_media);
            $result_event_media = $statement->execute();
            $row = $result_event_media->current();

if (!$result_event_media) {
    $status = "Failure";
    $message .= mysql_error();
} else {
    $row = $result_event_media->next();
    $q_event = "select user_id from event where event_id='".$row['event_id']."'";
   // $result_event = mysql_query($q_event);
    $statement1 = $this->dbAdapter->createStatement($q_event);
            $result_event = $statement1->execute();
            $row = $result_event->current();

    if (!$result_event) {
        $status = "Failure";
        $message .= mysql_error();
    } else {
        $row1=$result_event->next();
        $q_user_frind = "select * from user_friend where user_id='" . $row1['user_id'] . "' and friend_id='" . $user_id."'";
        //$result_user_friend = mysql_query($q_user_frind);
        $statement2 = $this->dbAdapter->createStatement($q_user_frind);
            $result_user_friend = $statement2->execute();
            $row = $result_user_friend->current();

        if (!$result_user_friend) {
            $status = "Failure";
            $message .= mysql_error();
        } else {
            
            if (0 < $result_user_friend->count()) {
                $q_friend__media = "select * from friend_media where friend_id='" . $user_id . "' and media_id='" . $media_id."'";
                //$result_friend_media = mysql_query($q_friend__media);
                
                $statement3 = $this->dbAdapter->createStatement($q_friend__media);
            $result_friend_media = $statement3->execute();
            $row = $result_friend_media->current();

                if (!$result_friend_media) {
                    $status = "Failure";
                    $message .= mysql_error();
                } else if ($result_friend_media->count() <= 0) {
                    $q_friend__media_insert = "insert into friend_media values('$user_id','$media_id')";
                   // $result_f_m = mysql_query($q_friend__media_insert);
                    
                $statement4 = $this->dbAdapter->createStatement($q_friend__media_insert);
            $result_f_m = $statement4->execute();
            $row = $result_f_m->current();

                    if (!$result_f_m) {
                        $status = "Failure";
                        $message = mysql_error();
                    } else {
                        $status = "Success";
                        $message.="Media Friend Successfully Updated and ";
                    }
                }
            }
        }
    }
}

//--------------------------------------------------------

$q = "SELECT * FROM `media` where media_id='$media_id'";
//$r = mysql_query($q) or die(mysql_error());
$statement4 = $this->dbAdapter->createStatement($q);
            $r = $statement4->execute();
            $row = $r->current();

if ($row = $r->next()) {
    $count = 0;
    $flag = 0;
    if (!empty($row['metadata'])) {
        $json_array = json_decode($row['metadata'], true);
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
            $update = "UPDATE `media` SET `metadata` ='$json_str' WHERE media_id ='$media_id'";
            //$result = mysql_query($update)
            $statement6 = $this->dbAdapter->createStatement($update);
            $result = $statement6->execute();
            $row = $result->current();

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
