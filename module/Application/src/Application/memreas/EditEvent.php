<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class EditEvent {

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
$event_id = trim($data->editevent->event_id);
$event_name = trim($data->editevent->event_name);
$event_location = trim($data->editevent->event_location);

$event_date = trim($data->editevent->event_date);
$event_from =strtotime(trim($data->editevent->event_from));
$event_to = strtotime(trim($data->editevent->event_to));

$is_friend_can_share = trim($data->editevent->is_friend_can_add_friend);
$is_friend_can_post_media = trim($data->editevent->is_friend_can_post_media);
$event_self_destruct =strtotime(trim($data->editevent->event_self_destruct));

$media_array = $data->editevent->medias->media;
$friend_array = $data->editevent->friends->friend;

if (!isset($event_id) && !empty($event_id)) {
    $message = 'event id is empty';
    $status = 'Failure';
} else if (!isset($event_name) && !empty($event_name)) {
    $message = 'event name is empty';
    $status = 'Failure';
} else if (!isset($event_date) && !empty($event_date)) {
    $message = 'event date is empty';
    $status = 'Failure';
} else if (!isset($event_location) && !empty($event_location)) {
    $messages = 'event location is empty';
    $status = 'Failure';
} else if (!isset($event_from) && !empty($event_from)) {
    $message = 'event from is empty';
    $status = 'Failure';
} else if (!isset($event_to) && !empty($event_to)) {
    $message = 'event to date is empty';
    $status = 'Failure';
} else if (!isset($is_friend_can_share) && !empty($is_friend_can_share)) {
    $message = 'frients can share field is empty';
    $status = 'Failure';
} else if (!isset($is_friend_can_post_media) && !empty($is_friend_can_post_media)) {
    $message = 'friend can post field is empty';
    $status = 'Failure';
} else if (!isset($event_self_destruct) && !empty($event_self_destruct)) {
    $message = 'self distruct field is empty';
    $status = 'Failure';
} else {
    $query = "update Application\Entity\Event as e set                  e.name='$event_name',
                                                e.location='$event_location',
                                                e.date='$event_date',
                                                e.friends_can_post='$is_friend_can_post_media',
                                                e.friends_can_share='$is_friend_can_share',
                                                e.viewable_from='$event_from',
                                                e.viewable_to='$event_to',
                                                e.self_destruct='$event_self_destruct',
                                                e.create_time='$event_date',
                                                e.update_time='$event_date' where e.event_id='$event_id' ";
   // $result = mysql_query($query);
    // $statement = $this->dbAdapter->createStatement($query);
      //      $result = $statement->execute();
           // $row = $result->current
    $statement = $this->dbAdapter->createQuery($query);
  $result = $statement->getResult();
           

            

    if (!$result) {
        $message.='Record not updated';
        $status = 'Failure';
    } else {
        $message.='Event Successfully Updated';
        $status = 'Success';
    }
   // $event_id = mysql_insert_id($conn);
}



header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";

$xml_output.= "<editeventresponse>";
$xml_output.= "<status>$status</status>";
$xml_output.= "<message>$message</message>";
$xml_output.= "</editeventresponse>";
$xml_output.= "</xml>";
echo $xml_output;
        }

}

?>
