<?php

namespace Application\memreas;

use Application\memreas\UUID;

class AddComment {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $notification;
    protected $AddNotification;
    protected $addTag;
    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
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
        if (!$this->addTag) {
           // $this->addTag = new AddTag($service_locator);
        }
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
            $tblComment = new \Application\Entity\Comment();
            
            if (!$audio_media_id) {
                $tblComment->comment_id = $uuid;
                $tblComment->media_id = $media_id;
                $tblComment->user_id = $user_id;
                $tblComment->type = 'text';
                $tblComment->event_id = $event_id;
                $tblComment->text = $comment;
                $tblComment->create_time = $time;
                $tblComment->update_time = $time;
                $this->dbAdapter->persist($tblComment);
                $this->dbAdapter->flush();



                /* $query_comment = "insert into Application\Entity\Comment (comment_id,media_id,user_id,type,text, event_id,create_time,update_time)
                  values('$uuid','$media_id','$user_id','text','$comment','$event_id','$time','$time')"; */

                $status = 'sucess';
            } else {
                $tblComment->comment_id = $uuid;
                $tblComment->media_id = $media_id;
                $tblComment->user_id = $user_id;
                $tblComment->type = 'audio';
                $tblComment->event_id = $event_id;
                $tblComment->text = $comment;
                $tblComment->audio_id = $audio_media_id;
                $tblComment->create_time = $time;
                $tblComment->update_time = $time;
                $this->dbAdapter->persist($tblComment);
                $this->dbAdapter->flush();
                /*  $query_comment = "insert into Application\Entity\Comment(comment_id,media_id,user_id,type,text, event_id,audio_id,create_time,update_time)
                  values('$uuid','$media_id','$user_id','audio','$comment','$event_id','$audio_media_id','$time','$time')";
                 * 
                 */

                $status = 'sucess';
            }
            // $result_comment = mysql_query($query_comment) or die(mysql_error());
            //echo $query_comment;
            //$statement = $this->dbAdapter->createStatement($query_comment);
            // $result = $statement->execute();

            /* $statement = $this->dbAdapter->createQuery($query_comment);
              $result = $statement->getResult(); */
            //print_r($result);
            $status = 'sucess';
            $message = "Comment successfuly added";


            if (empty($status)) {
                $status = 'failure';
            } else {
               
                //add tag
               /* $metaTag = array(
                    'event_ids' => $event_id,
                    'media_ids' => $media_id,
                    'user_ids'  => $user_id,
                    'comment_ids' => $uuid,
                );
                
                
                $events = ParseString::getEventname($comment)  ;  
                echo '<pre>';print_r($events);exit;
                $usernames = ParseString::getUserName($comment)  ;                    
                $keywords = ParseString::getKeyword($comment)  ;                    

                $tagData = array('addtag' => array(
                    'meta' => json_encode($metaTag),
                    'tag_type' => \Application\Entity\Tag::EVENT,
                    'tag' => \Application\Entity\Tag::EVENT.$event_name,
                ),);
                $this->AddTag->exec($tagData);
                
                */
                
                //TODO send notification owner of the event and all who commented.
                $query = "SELECT c.user_id FROM  Application\Entity\Comment as c  where c.event_id = '$event_id'";
                $statement = $this->dbAdapter->createQuery($query);
                $comment_u = $statement->getResult();
                foreach ($comment_u as $comment_row) {
                    $data = array('addNotification' => array(
                            'user_id' => $comment_row['user_id'],
                            'meta' => 'User has comment on ',
                            'notification_type' => \Application\Entity\Notification::ADD_COMMENT,
                            'links' => json_encode(array(
                                'event_id' => $event_id,
                                'from_id' => $comment_row['user_id'],
                            )),
                        )
                    );

                    $this->AddNotification->exec($data);
                    $this->notification->add($comment_row['user_id']);
                }

                if (!empty($data['addNotification']['meta'])) {

                    $this->notification->setMessage($data['addNotification']['meta']);
                    $this->notification->type = \Application\Entity\Notification::ADD_COMMENT;
                    $this->notification->event_id = $event_id;
                    $this->notification->media_id = $media_id;

                    $this->notification->send();
                }
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
