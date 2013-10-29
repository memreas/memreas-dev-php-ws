<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\AddNotification;
use Application\memreas\UUID;

class AddMediaEvent {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    protected $AddNotification;
    protected $notification;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("AddMediaEvent __construct...");
        error_log("AddMediaEvent __construct message_data..." . print_r($message_data, true) . PHP_EOL);
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
        if (!$this->AddNotification) {
            $this->AddNotification = new AddNotification($message_data, $memreas_tables, $service_locator);
        }
        if (!$this->notification) {
            $this->notification = new Notification($service_locator);
        }
    }

    public function exec() {
        error_log("AddMediaEvent exec...");
        error_log("AddMediaEvent _POST ----> " . print_r($_POST, true) . PHP_EOL);
        error_log("AddMediaEvent _POST[user_id] ----> " . $_POST['user_id'] . PHP_EOL);
        $is_audio = FALSE;
        try {
            $media_id = '';
//--------------------old parameters
            //Fetch user_id
            if (isset($_POST['user_id'])) {
                $user_id = trim($_POST['user_id']);
            } else {
                $message = 'Error : User ID is Mempty';
                throw new \Exception('Error : User ID is Mempty');
            }

            error_log("AddMediaEvent exec user_id ----> " . $user_id . PHP_EOL);

            //Fetch event_id
            $event_id = (isset($_POST['event_id'])) ? trim($_POST['event_id']) : '';
            if (isset($_POST['device_id']))
                $divice_id = trim($_POST['device_id']);
            else
                $message = 'Error : Device ID is Mempty';
            // throw new Exception('Error : Device ID is Mempty');

            error_log("AddMediaEvent exec event_id ----> " . $event_id . PHP_EOL);

            $is_profile_pic = isset($_POST['is_profile_pic']) ? trim($_POST['is_profile_pic']) : 0;
            $time = time();
            $is_server_image = isset($_POST['is_server_image']) ? $_POST['is_server_image'] : 0;


            //////////////////////////////////////////////////////////////////////
            // dont upload file if server image just insert into event_media table
            //////////////////////////////////////////////////////////////////////
            if ($is_server_image == 1) {

                error_log("AddMediaEvent exec eis_server_image == 1 " . PHP_EOL);

                if (isset($_POST['media_id']) && !empty($_POST['media_id']))
                    $media_id = $_POST['media_id'];
                else
                // throw new Exception('Error : Media ID is Mempty
                    $message = 'Error : Media ID is Mempty';

                $tblEventMedia = new \Application\Entity\EventMedia();
                $tblEventMedia->media_id = $media_id;
                $tblEventMedia->event_id = $event_id;
                $this->dbAdapter->persist($tblEventMedia);
                $this->dbAdapter->flush();

                //$q_event_media = "INSERT INTO Application\Entity\EventMedia (media_id, event_id) VALUES ('$media_id', '$event_id')";
                //$query_result1 = mysql_query($q_event_media);
                //$statement = $this->dbAdapter->createStatement($q_event_media);
                // $query_result1 = $statement->execute();
                //$row = $result->current();
                //   $statement = $this->dbAdapter->createQuery($q_event_media);
                // $query_result1 = $statement->getResult();


                $status = 'Success';
                $message = "Media Successfully add";
            }
            else {
                /////////////////////////////////////////////////
                // insert into media and event media 
                /////////////////////////////////////////////////

                error_log("AddMediaEvent exec is_server_image == 1 else " . PHP_EOL);

                ////////////////////////////////////
                // fetch parameters to upload to S3
                ////////////////////////////////////
                $content_type = trim($_POST['content_type']);
                $s3file_name = trim($_POST['s3file_name']);
                $email = trim($_POST['email']);
                $s3url = trim($_POST['s3url']);
                $isVideo = 0;
                $s3path = $user_id . '/';
                //$media_id = getUUID(); //generate GUUID
                $media_id = UUID::getUUID($this->dbAdapter);

                /////////////////////////////////////////
                //create metadata based on content type 
                /////////////////////////////////////////
                $file_type = explode('/', $content_type);
                if (strcasecmp($file_type[0], 'image') == 0) {
                    $s3path = $user_id . '/image/';
                    $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,),
                        "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
                        "type" => array("image" => array("format" => $file_type[1]))
                    );
                } else
                if (strcasecmp('video', $file_type[0]) == 0) {
                    $isVideo = 1;
                    $s3path = $user_id . '/media/';
                    $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url),
                        "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
                        "type" => array("video" => array("format" => $file_type[1],))
                    );
                } else
                if (strcasecmp('audio', $file_type[0]) == 0) {
                    $is_audio = 1;
                    $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,),
                        "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
                        "type" => array("audio" => array("format" => $file_type[1],))
                    );
                }
                $json_str = json_encode($json_array);

                /////////////////////////////////////////
                //check media type and update tables... 
                /////////////////////////////////////////
                if ($is_profile_pic) {
                    //if profile pic then update media
                    $update_media = "UPDATE Application\Entity\Media  m  SET m.is_profile_pic = $is_profile_pic WHERE m.user_id ='" . $user_id;
                    // $rs_is_profil = mysql_query($update_media);
                    // $statement3 = $this->dbAdapter->createStatement($update_media);
                    //$rs_is_profil = $statement3->execute();
                    //$row = $result->current();
                    $statement = $this->dbAdapter->createQuery($update_media);
                    $rs_is_profil = $statement->getResult();

                    if (!$rs_is_profil)
                        throw new Exception('Error : ' . mysql_error());

                    error_log("AddMediaEvent exec - just udpated Media " . PHP_EOL);
                } else {
                    //insert into media table
                    $now = date('Y-m-d H:i:s');
                    $tblMedia = new \Application\Entity\Media();
                    $tblMedia->media_id = $media_id;
                    $tblMedia->user_id = $user_id;
                    $tblMedia->is_profile_pic = $is_profile_pic;
                    $tblMedia->metadata = $json_str;
                    $tblMedia->create_date = $now;
                    $tblMedia->update_date = $now;
                    $this->dbAdapter->persist($tblMedia);
                    $this->dbAdapter->flush();
                    error_log("AddMediaEvent exec - just inserted Media " . PHP_EOL);
                }

                $event_id = isset($_POST['event_id']) ? trim($_POST['event_id']) : null;
                if (isset($event_id)) {
                    $tblEventMedia = new \Application\Entity\EventMedia();
                    $tblEventMedia->media_id = $media_id;
                    $tblEventMedia->event_id = $event_id;
                    $this->dbAdapter->persist($tblEventMedia);
                    $this->dbAdapter->flush();
                    error_log("AddMediaEvent exec - just inserted EventMedia " . PHP_EOL);

                    //   $q_event_media = "INSERT INTO Application\Entity\eventMedia (media_id, event_id) VALUES ('$media_id', '$event_id')";
                    //$query_result1 = mysql_query($q_event_media);
                    //    $statement1 = $this->dbAdapter->createStatement($q_event_media);
                    //  $query_result1 = $statement1->execute();
                    //  $statement = $this->dbAdapter->createQuery($q_event_media);
                    // $query_result1 = $statement->getResult();
                    //if (!$query_result1)
                    //	throw new Exception('Error : ' . mysql_error());
                }

                //if (!$is_audio) {
                if (!$is_server_image) {
                    $message_data = array(
                        'user_id' => $user_id,
                        'media_id' => $media_id,
                        'content_type' => $content_type,
                        's3path' => $s3path,
                        's3file_name' => $s3file_name,
                        'isVideo' => $isVideo,
                        'email' => $email
                    );

                    $aws_manager = new AWSManagerSender($this->service_locator);
                    $response = $aws_manager->snsProcessMediaPublish($message_data);

                    if ($response == 1) {
                        $status = 'Success';
                        $message = "Media Successfully add";
                    }
                    else
                        throw new Exception('Error In snsProcessMediaPublish');
                }else {
                    $status = 'Success';
                    $message = "Media Successfully add";
                }
            }

            /*
             * @todo send to all particiepent
             * 
             */
            $query = "SELECT ef.friend_id FROM  Application\Entity\EventFriend as ef  where ef.event_id = '$event_id'";
            $statement = $this->dbAdapter->createQuery($query);
            $efusers = $statement->getResult();
            foreach ($efusers as $ef) {
                $data = array('addNotification' => array(
                        'user_id' => $ef['friend_id'],
                        'meta' => 'New Media added',
                        'notification_type' => \Application\Entity\Notification::ADD_MEDIA,
                        'links' => json_encode(array(
                            'event_id' => $event_id,
                            'from_id' => $user_id,
                            'friend_id' => $ef['friend_id'],
                        )),
                    )
                );
                $this->AddNotification->exec($data);
                $this->notification->add($ef['friend_id']);
            }


            if (!empty($data['addNotification']['meta'])) {
                $this->notification->setMessage($data['addNotification']['meta']);

                $this->notification->type = \Application\Entity\Notification::ADD_MEDIA;

                $this->notification->event_id = $event_id;



                $this->notification->send();
            }
        } catch (\Exception $exc) {
            $status = 'Failure';
            $message = $exc->getMessage();
        }
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output.= "<addmediaeventresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>$message</message>";
        $xml_output.= "<media_id>$media_id</media_id>";
        $xml_output.= "</addmediaeventresponse>";
        $xml_output.= "</xml>";
        ob_clean();
        echo $xml_output;
        error_log($xml_output, 0);
        error_log("EXIT addmediaevent.php...");
    }

}

?>
