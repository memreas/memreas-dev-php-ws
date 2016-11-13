<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;

class AddEvent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
	protected $AddTag;
	protected $notification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
		/*
		 * if(!$this->AddTag){
		 * $this->AddTag = new AddTag($service_locator);
		 * }
		 */
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		try {
			
			Mlog::addone( $cm . __LINE__, "AddEvent::input::" . $_POST ['xml'] . PHP_EOL );
			$data = simplexml_load_string ( $_POST ['xml'] );
			$message = ' ';
			$user_id = addslashes ( trim ( $data->addevent->user_id ) );
			$event_name = addslashes ( trim ( $data->addevent->event_name ) );
			$event_location = addslashes ( trim ( $data->addevent->event_location ) );
			
			$event_date = trim ( $data->addevent->event_date );
			$event_from = $duration_from =  trim ( $data->addevent->event_from );
			$event_to = $duration_to =  trim ( $data->addevent->event_to );
			
			$event_date_timestamp = time ();
			$is_friend_can_share = trim ( $data->addevent->is_friend_can_add_friend );
			$is_friend_can_post_media = trim ( $data->addevent->is_friend_can_post_media );
			$event_self_destruct = trim ( $data->addevent->event_self_destruct );
			$is_public = trim ( $data->addevent->is_public );
			$price = trim ( $data->addevent->price );
			$event_id = '';
			$time = time ();
			if (! isset ( $user_id ) || empty ( $user_id )) {
				$message .= 'user id is empty';
				$status = 'Failure';
			} else if (! isset ( $event_name ) || empty ( $event_name )) {
				$message .= 'event name is empty';
				$status = 'Failure';
			} else {
				$uuid = MUUID::fetchUUID ();
				$tblEvent = new \Application\Entity\Event ();
				
				$metadata = array ();
				$metadata ['price'] = $price;
				$metadata ['duration_from'] = $duration_from;
				$metadata ['duration_to'] = $duration_to;
				
				$tblEvent->name = $event_name;
				$tblEvent->location = $event_location;
				$tblEvent->user_id = $user_id;
				$tblEvent->type = 'audio';
				$tblEvent->event_id = $uuid;
				$tblEvent->date = $event_date;
				$tblEvent->friends_can_post = $is_friend_can_post_media;
				$tblEvent->friends_can_share = $is_friend_can_share;
				$tblEvent->public = $is_public;
				$tblEvent->viewable_from = $event_from;
				$tblEvent->viewable_to = $event_to;
				$tblEvent->self_destruct = $event_self_destruct;
				$tblEvent->create_time = $event_date_timestamp;
				$tblEvent->update_time = $event_date_timestamp;
				$tblEvent->metadata = json_encode ( $metadata );
				$this->dbAdapter->persist ( $tblEvent );
				$this->dbAdapter->flush ();
				
				$event_id = $uuid;
				$message .= $event_name . ' successfully added';
				$status = 'Success';
				// TODO send Notification
				/*
				 * $data = array('addNotification' => array(
				 * 'user_id' => $user_id,
				 * 'meta' => "New Event: $event_name",
				 * 'notification_type' => \Application\Entity\Notification::ADD_EVENT,
				 * 'links' => json_encode(array(
				 * 'event_id' => $event_id,
				 * 'from_id' => $user_id,
				 *
				 * )),
				 * )
				 *
				 *
				 * );
				 * $this->AddNotification->exec($data);
				 * $this->notification->add($user_id);
				 * if (!empty($data['addNotification']['meta'])) {
				 *
				 * $this->notification->setMessage($data['addNotification']['meta']);
				 * $this->notification->type = \Application\Entity\Notification::ADD_EVENT;
				 * $this->notification->event_id = $event_id;
				 * $this->notification->send();
				 * }
				 */
				/*
				 * foreach ($media_array as $key => $value)
				 * {
				 *
				 * $comment = trim($value->media_comments);
				 * $media_name = trim($value->media_name);
				 * $media_url = trim($value->media_url);
				 * $media_audio_file = trim($value->media_audio_file);
				 * //--------------upload media file---------------
				 *
				 *
				 * $query_media = "insert into media(user_id,create_date,update_date)
				 * values('$user_id','$event_date','$event_date')";
				 * $result_media = mysql_query($query_media);
				 * if (!$result_media) {
				 * $status = 'failure';
				 * $message.=mysql_error();
				 * }
				 * $media_id = mysql_insert_id();
				 *
				 * $query_event_media = "insert into event_media values('$media_id','$event_id')";
				 * $result_event_media = mysql_query($query_event_media);
				 * if (!$result_event_media) {
				 * $message.=mysql_error();
				 * $status = 'failure';
				 * }
				 *
				 * $query_comment = "insert into comment(media_id, user_id, text, event_id, create_time,update_time)
				 * values('$media_id','$user_id','$comment','$event_id','$event_date','$event_date')";
				 * $result_comment = mysql_query($query_comment);
				 * if (!$result_comment) {
				 * $message.=mysql_error();
				 * $status = 'failure';
				 * }
				 * }
				 *
				 */
				/*
				 * foreach ($friend_array as $key => $value) {
				 * $network_name = trim($value->network_name);
				 * $friend_name = trim($value->friend_name);
				 * $profile_pic_url = trim($value->profile_pic_url);
				 * $friend_query = "select friend_id from friend where network='$network_name' and
				 * social_username='$friend_name' and
				 * url_image='$profile_pic_url'";
				 * $result_friend = mysql_query($friend_query);
				 * if ($row = mysql_fetch_assoc($result_friend)) {
				 * $friend_id = $row['friend_id'];
				 * }else{
				 *
				 * $insert_q="INSERT INTO friend(
				 * `network` ,
				 * `social_username` ,
				 * `url_image` ,
				 * `create_date` ,
				 * `update_date`
				 * )
				 * VALUES (
				 * '$network_name', '$friend_name', '$profile_pic_url', '$time', '$time')";
				 * $result_friend_insert= mysql_query($insert_q);
				 * $friend_id= mysql_insert_id();
				 * if (!$result_event_media) {
				 * $message.=mysql_error();
				 * $status = 'failure';
				 * }
				 *
				 * }
				 * $query_event_friend = "insert into event_friend values('$event_id','$friend_id')";
				 * $result_event_friend= mysql_query($query_event_friend);
				 * if(!$result_event_friend)
				 * {
				 * $message.=mysql_error();
				 * $status = 'failure';
				 * }
				 * $message .= 'Event successfully added';
				 * $status = 'success';
				 *
				 * }
				 *
				 */
			}
			
			if (empty ( $status )) {
				$message .= mysql_error ();
				$status = 'Failure';
			}
			ob_clean ();
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<addeventresponse>";
			$xml_output .= "<status>" . $status . "</status>";
			$xml_output .= "<message>" . $message . "</message>";
			$xml_output .= "<event_id>" . $event_id . "</event_id>";
			$xml_output .= "</addeventresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
			error_log ( "AddEventoutput::" . $xml_output . PHP_EOL );
		} catch ( Exception $e ) {
			error_log ( 'Caught exception: ' . $e->getMessage () . PHP_EOL );
		}
	}
}

?>
