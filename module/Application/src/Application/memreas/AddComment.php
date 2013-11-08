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
        } else if (empty($media_id)) {
            $message = 'media_id is empty';
            $status = 'Failure';
        } else if (empty($comment)) {
            $messages = 'comment is empty';
            $status = 'Failure';
        } else if (empty($user_id)) {
            $messages = 'user_id is empty';
            $status = 'Failure'; 
        } else {
            $uuid = UUID::getUUID($this->dbAdapter);
            $tblComment = new \Application\Entity\Comment();
			 $userOBj = $this->dbAdapter->find('Application\Entity\User', $user_id);
             $eventOBj = $this->dbAdapter->find('Application\Entity\Event', $event_id);
     
            
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
               

                $status = 'sucess';
            }
           
            $status = 'sucess';
            $message = "Comment successfuly added";


            if ($status=='failure') {
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
                 $query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
                       $qb = $this->dbAdapter->createQueryBuilder();
                            $qb->select('u.user_id,f.network,f.friend_id');
                            $qb->from('Application\Entity\EventFriend', 'ef');
                            $qb->join('Application\Entity\Friend', 'f','WITH', 'ef.friend_id = f.friend_id');
                            $qb->leftjoin('Application\Entity\User', 'u', 'WITH', 'u.username = f.social_username');
                            $qb->where('ef.event_id = ?1');
                            $qb->setParameter(1, $event_id);
                    
                    
                    
                    
                   
                    $efusers = $qb->getQuery()->getResult();
                    $nmessage = $userOBj->username . ' Has commented on '.$eventOBj->name.' event';

                    $cdata['addNotification']['meta'] = $nmessage;
                foreach ($efusers as $ef) {
                    if($ef['network'] == 'memreas'){
                        $cdata = array('addNotification' => array(
                            'user_id' =>$ef['user_id'],
                            'meta' => $nmessage,
                            'notification_type' => \Application\Entity\Notification::ADD_COMMENT,
                            'links' => json_encode(array(
                                'event_id' => $event_id,
                                'from_id' => $user_id,
                                )),
                            )
                        );

                        $this->AddNotification->exec($cdata);
                        $this->notification->add($ef['user_id']);
                       }else{
                        $this->notification->addFriend($ef['friend_id']);
                       }
                    
                }
                
 
                    $this->notification->setMessage($cdata['addNotification']['meta']);
                    $this->notification->type = \Application\Entity\Notification::ADD_COMMENT;
                    $this->notification->event_id = $event_id;
                    $this->notification->media_id = $media_id;
                    $this->notification->send();
             }



            //echo '<pre>';print_r($result);exit;
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