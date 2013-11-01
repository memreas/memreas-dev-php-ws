<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\UUID;

class AddFriend {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $AddNotification;
    protected $AddTag;
    protected $notification;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
//        if(!$this->AddNotification){
//            $this->AddNotification = new AddNotification($message_data, $memreas_tables, $service_locator);
//        }
        /*if(!$this->AddTag){
                        $this->AddTag = new AddTag($service_locator);
        }*/
//        if (!$this->notification) {
//            $this->notification = new Notification($service_locator);
//        }
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
       
        $data = simplexml_load_string($_POST['xml']);
		$message = ' ';
		$user_id =addslashes(trim($data->addevent->user_id));
		$event_name = addslashes(trim($data->addevent->event_name));
		$event_location = addslashes(trim($data->addevent->event_location));

		$event_date = addslashes(trim($data->addevent->event_date));
		$event_from = strtotime(trim($data->addevent->event_from));
		$event_to = strtotime(trim($data->addevent->event_to));



$event_date_timestamp= time();
$is_friend_can_share = trim($data->addevent->is_friend_can_add_friend);
$is_friend_can_post_media = trim($data->addevent->is_friend_can_post_media);
$event_self_destruct = strtotime(trim($data->addevent->event_self_destruct));
$is_public = trim($data->addevent->is_public);
//$media_array = $data->addevent->medias->media;
//$friend_array =$data->addevent->friends->friend;
//    print_r($friend_array);
//print_r($media_array);
$event_id='';
$time = time();
if (!isset($user_id) || empty($user_id)) {
    $message .= 'user id is empty';
    $status = 'Failure';
}else if (!isset($event_name) || empty($event_name)) {
    $message .= 'event name is empty';
    $status = 'Failure';
} 
//else if (!isset($event_date) || empty($event_date)) {
//    $message .= 'event date is empty';
//    $status = 'Failure';
//} 
//else if (!isset($event_location) || empty($event_location)) {
//    $message .= 'event location is empty';
//    $status = 'Failure';
//} else if (!isset($event_from) || empty($event_from)) {
//    $message .= 'event from is empty';
//    $status = 'Failure';
//} else if (!isset($event_to) || empty($event_to)) {
//    $message .= 'event to date is empty';
//    $status = 'Failure';
//} else if (!isset($is_friend_can_share) || empty($is_friend_can_share)) {
//    $message .= 'frients can share field is empty';
//    $status = 'Failure';
//} else if (!isset($is_friend_can_post_media) || empty($is_friend_can_post_media)) {
//    $message .= 'friend can post field is empty';
//    $status = 'Failure';
//} else if (!isset($event_self_destruct) || empty($event_self_destruct)) {
//    $event_self_destruct='';
//}
else {
   // $uuid=  getUUID();
             $uuid = UUID::getUUID($this->dbAdapter);
             			$tblEvent= new \Application\Entity\Event();

             $tblEvent->name=$event_name;
                $tblEvent->location=$event_location;
                $tblEvent->user_id=$user_id;
                $tblEvent->type='audio';
                $tblEvent->event_id=$uuid;
                $tblEvent->date=$event_date;
                $tblEvent->friends_can_post=$is_friend_can_post_media;
                $tblEvent->friends_can_share=$is_friend_can_share;
                $tblEvent->public=$is_public;
                $tblEvent->viewable_from=$event_from;
                $tblEvent->viewable_to=$event_to;
                $tblEvent->self_destruct=$event_self_destruct;
                $tblEvent->create_time=$event_date_timestamp;
                $tblEvent->update_time=$event_date_timestamp;
                $this->dbAdapter->persist($tblEvent);
                $this->dbAdapter->flush();
                
                
                
                
              //add tag
              /*  $tagData = array('addtag' => array(
                    'meta' => json_encode(array('event_id' => $uuid)),
                    'tag_type' => \Application\Entity\Tag::EVENT,
                    'tag' => \Application\Entity\Tag::EVENT.$event_name,
                ),);
                $this->AddTag->exec($tagData);
               * *
               */
                

   /* $query = "insert into Application\Entity\Event e (event_id,user_id,
                                                name,
                                                location,
                                                date,
                                                friends_can_post,
                                                friends_can_share,
                                                public,
                                                viewable_from,
                                                viewable_to,
                                                self_destruct,
                                                create_time,update_time)
                                                    values('$uuid','$user_id',
                                                '$event_name',
                                                '$event_location',
                                                '$event_date',
                                                '$is_friend_can_post_media',
                                                '$is_friend_can_share',
                                                '$is_public',
                                                '$event_from',
                                                '$event_to',
                                                '$event_self_destruct',
                                                '$event_date_timestamp','$event_date_timestamp')";*/
    //$result = mysql_query($query);
    // $statement = $this->dbAdapter->createStatement($query);
     //      $result = $statement->execute();
            //$row = $result->current();
   // $statement = $this->dbAdapter->createQuery($query);
  //$result = $statement->getResult();
  
  
   //TODO send Notification
        $event_id = $uuid;
        $message .= 'Event successfully added';
        $status = 'Success';
  
        $data = array('addNotification' => array(
                                'user_id' => $user_id,
                                'meta' => "New Event: $event_name",
                                'notification_type' => \Application\Entity\Notification::ADD_EVENT,
                                'links' => json_encode(array(
                                    'event_id' => $event_id,
                                    'from_id' => $user_id,
                                    
                                )),
                                )
                        
                        
                    );
                     $this->AddNotification->exec($data);
                      $this->notification->add($user_id);
                     if (!empty($data['addNotification']['meta'])) {

                    $this->notification->setMessage($data['addNotification']['meta']);
                    $this->notification->type = \Application\Entity\Notification::ADD_EVENT;
                    $this->notification->event_id = $event_id;
                    $this->notification->send();
                }
    /*
      foreach ($media_array as $key => $value)
      {

      $comment = trim($value->media_comments);
      $media_name = trim($value->media_name);
      $media_url = trim($value->media_url);
      $media_audio_file = trim($value->media_audio_file);
      //--------------upload media file---------------


      $query_media = "insert into media(user_id,create_date,update_date)
      values('$user_id','$event_date','$event_date')";
      $result_media = mysql_query($query_media);
      if (!$result_media) {
      $status = 'failure';
      $message.=mysql_error();
      }
      $media_id = mysql_insert_id();

      $query_event_media = "insert into event_media values('$media_id','$event_id')";
      $result_event_media = mysql_query($query_event_media);
      if (!$result_event_media) {
      $message.=mysql_error();
      $status = 'failure';
      }

      $query_comment = "insert into comment(media_id, user_id, text, event_id, create_time,update_time)
      values('$media_id','$user_id','$comment','$event_id','$event_date','$event_date')";
      $result_comment = mysql_query($query_comment);
      if (!$result_comment) {
      $message.=mysql_error();
      $status = 'failure';
      }
      }
     * 
     */
    /*
      foreach ($friend_array as $key => $value) {
      $network_name = trim($value->network_name);
      $friend_name = trim($value->friend_name);
      $profile_pic_url = trim($value->profile_pic_url);
      $friend_query = "select friend_id from friend where network='$network_name' and
      social_username='$friend_name' and
      url_image='$profile_pic_url'";
      $result_friend = mysql_query($friend_query);
      if ($row = mysql_fetch_assoc($result_friend)) {
      $friend_id = $row['friend_id'];
      }else{

      $insert_q="INSERT INTO friend(
      `network` ,
      `social_username` ,
      `url_image` ,
      `create_date` ,
      `update_date`
      )
      VALUES (
      '$network_name', '$friend_name', '$profile_pic_url', '$time', '$time')";
      $result_friend_insert=  mysql_query($insert_q);
      $friend_id=  mysql_insert_id();
      if (!$result_event_media) {
      $message.=mysql_error();
      $status = 'failure';
      }

      }
      $query_event_friend = "insert into event_friend values('$event_id','$friend_id')";
      $result_event_friend=  mysql_query($query_event_friend);
      if(!$result_event_friend)
      {
      $message.=mysql_error();
      $status = 'failure';
      }
      $message .= 'Event successfully added';
      $status = 'success';

      }
     * 
     */
}

 if(empty($status)) {
        $message.=mysql_error();
        $status = 'Failure';
    } 
/*
  if(strcasecmp('success', $status)==0){
  $flag=uploadMedia();
  if(!$flag)
  {
  $status="failure";
  $message="event successfully added but error in upload media file";
  }
  }
 * 
 */
ob_clean();
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml>";
$xml_output.= "<addeventresponse>";
$xml_output.= "<status>".$status."</status>";
$xml_output.= "<message>".$message."</message>";
$xml_output.= "<event_id>".$event_id."</event_id>";
$xml_output.= "</addeventresponse>";
$xml_output.= "</xml>";
echo $xml_output;

    }

}

?>
