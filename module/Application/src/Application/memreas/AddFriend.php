<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;

class AddFriend {

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
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
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

		$event_id='';
		$time = time();
		if (!isset($user_id) || empty($user_id)) {
		    $message .= 'user id is empty';
		    $status = 'Failure';
		}else if (!isset($event_name) || empty($event_name)) {
		    $message .= 'event name is empty';
		    $status = 'Failure';
		} else {
             $uuid = MUUID::fetchUUID();
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
                
  
				//TODO send Notification
		        $event_id = $uuid;
		        $message .= 'Event successfully added';
		        $status = 'Success';
		  
		        $data = array(
		        			'addNotification' => 
		        				array (
		                                'user_id' => $user_id,
		                                'meta' => "New Event: $event_name",
		                                'notification_type' => \Application\Entity\Notification::ADD_EVENT,
		                                'links' => json_encode(
		                                		array(
		                                    		'event_id' => $event_id,
		                                    		'from_id' => $user_id,
												)
		        				),
						));
				$this->AddNotification->exec($data);
				$this->notification->add($user_id);
				if (!empty($data['addNotification']['meta'])) {
                    $this->notification->setMessage($data['addNotification']['meta']);
                    $this->notification->type = \Application\Entity\Notification::ADD_EVENT;
                    $this->notification->event_id = $event_id;
                    $this->notification->send();
                }

		}

		if(empty($status)) {
		        $message.=mysql_error();
		        $status = 'Failure';
		} 
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
