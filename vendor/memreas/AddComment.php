<?php

namespace memreas;

use memreas\UUID;

class AddComment {

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
        $event_id = trim($data->addcomment->event_id);
        $media_id = trim($data->addcomment->media_id);
        $comment = trim($data->addcomment->comments);
        $user_id = trim($data->addcomment->user_id);
        $audio_media_id = trim($data->addcomment->audio_media_id);
        $message = "";
        $time = time();
        if (!isset($event_id) && !empty($event_id)) {
            $message = 'event id is empty';
            $status = 'Failure';
        } else if (!isset($media_id) && !empty($media_id)) {
            $message = 'media_id is empty';
            $status = 'Failure';
        } else if (!isset($comment) && !empty($comment)) {
            $messages = 'comment is empty';
            $status = 'Failure';
        } else if (!isset($user_id) && !empty($user_id)) {
            $messages = 'user_id is empty';
            $status = 'Failure';
        } else {
            $uuid = UUID::getUUID($this->dbAdapter);
            if (!$audio_media_id) {
                $query_comment = "insert into comment(comment_id,media_id,user_id,type,text, event_id,create_time,update_time)
                    values('$uuid','$media_id','$user_id','text','$comment','$event_id','$time','$time')";
            } else {
                $query_comment = "insert into comment(comment_id,media_id,user_id,type,text, event_id,audio_id,create_time,update_time)
                    values('$uuid','$media_id','$user_id','audio','$comment','$event_id','$audio_media_id','$time','$time')";
            }
            // $result_comment = mysql_query($query_comment) or die(mysql_error());
            //echo $query_comment;
            $statement = $this->dbAdapter->createStatement($query_comment);
            $result = $statement->execute();
            //print_r($result);
            $status = 'sucess';
            $message = "Comment successfuly added";

            if (empty($result)) {
                $status = 'failure';
            }



            //echo '<pre>';print_r($result);exit;
            //	$result_comment = $result->current();
        }
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";

        $xml_output.= "<addcommentresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>$message</message>";

        $xml_output.= "</addcommentresponse>";
        $xml_output.= "</xml>";
        echo $xml_output;
    }

}

?>
