<?php
namespace memreas;

use Zend\Session\Container;

use Application\Model\MemreasConstants; 
use memreas\AWSManager;
use memreas\UUID;

class AddMediaEvent {

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
       $is_audio=FALSE;
try {
    $media_id = '';
//--------------------old parameters
    if (isset($_POST['user_id']))
        $user_id = trim($_POST['user_id']);
    else
        $message ='Error : User ID is Mempty';
        throw new \Exception('Error : User ID is Mempty');

    $event_id = (isset($_POST['event_id'])) ?  trim($_POST['event_id']) : '';

    if (isset($_POST['device_id']))
        $divice_id = trim($_POST['device_id']);
    else
        $message='Error : Device ID is Mempty';
       // throw new Exception('Error : Device ID is Mempty');

    $is_profile_pic = isset($_POST['is_profile_pic']) ? trim($_POST['is_profile_pic']) : 0;
    $time = time();
    $is_server_image = isset($_POST['is_server_image']) ? $_POST['is_server_image'] : 0;

//s3path =

    if ($is_server_image == 1) {//dont upload file insert into event_media table
        if (isset($_POST['media_id']) && !empty($_POST['media_id']))
            $media_id = $_POST['media_id'];
        else
           // throw new Exception('Error : Media ID is Mempty
            $message ='Error : Media ID is Mempty';
        $tblEventMedia= new \Application\Entity\EventMedia();
            
				
                $tblEventMedia->media_id=$media_id;
              
                
                $tblEventMedia->event_id=$event_id;
                
                
                
                $this->dbAdapter->persist($tblEventMedia);
                $this->dbAdapter->flush();

        //$q_event_media = "INSERT INTO Application\Entity\EventMedia (media_id, event_id) VALUES ('$media_id', '$event_id')";
        //$query_result1 = mysql_query($q_event_media);
         //$statement = $this->dbAdapter->createStatement($q_event_media);
           // $query_result1 = $statement->execute();
            //$row = $result->current();
     //   $statement = $this->dbAdapter->createQuery($q_event_media);
 // $query_result1 = $statement->getResult();

        if (!$query_result1) {
            throw new Exception('Error : ' . mysql_error());
        } else {
            $status = 'Success';
            $message = "Media Successfully add";
        }
    } else {

        //new parameters
      //  $content_type = trim($_POST['content_type']);
       // $s3file_name = trim($_POST['s3file_name']);
       // $email = trim($_POST['email']);
       // $s3url = trim($_POST['s3url']);
        $isVideo = 0;
       // $s3path = $user_id . '/';
        
        //$media_id = getUUID(); //generate GUUID
                    $media_id = UUID::getUUID($this->dbAdapter);

        
        //create metadata 
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
            $is_audio=1;
            $json_array = array("S3_files" => array("path" => $s3url, "Full" => $s3url,),
                "local_filenames" => array("device" => array("unique_device_identifier1" => $user_id . '_' . $divice_id,),),
                "type" => array("audio" => array("format" => $file_type[1],))
            );
        }
        $json_str = json_encode($json_array);

        //insert into media table

        if ($is_profile_pic) {//if profile pic then remove previous profile pic
            $update_media = "UPDATE Application\Entity\Media  m  SET m.is_profile_pic = '0' WHERE m.user_id ='$user_id' and  m.is_profile_pic = '1'";
           // $rs_is_profil = mysql_query($update_media);
            // $statement3 = $this->dbAdapter->createStatement($update_media);
            //$rs_is_profil = $statement3->execute();
            //$row = $result->current();
            $statement = $this->dbAdapter->createQuery($update_media);
  $rs_is_profil = $statement->getResult();

            if (!$rs_is_profil)
                throw new Exception('Error : ' . mysql_error());
        }
        $tblMedia= new \Application\Entity\Media();
          
		        $tblMedia->media_id=$media_id;
                $tblMedia->user_id=$user_id;
                $tblMedia->is_profile_pic=$is_profile_pic;
                $tblMedia->metadata=$json_str;
               $tblMedia->create_time=$time;
                $tblMedia->update_time=$time;
                $this->dbAdapter->persist($tblMedia);
                $this->dbAdapter->flush();
    /*   $q = "INSERT INTO Application\Entity\Media (media_id,
                        user_id ,
                        is_profile_pic,
                        metadata,
                        create_date,
                        update_date)
                VALUES ('$media_id',
                        '$user_id',
                        '$is_profile_pic',
                        '$json_str',
                        '$time', '$time')";*/
        //$query_result = mysql_query($q);
     //    $statement1 = $this->dbAdapter->createStatement($q);
       //     $query_result = $statement1->execute();
            //$row = $result->current();
     //  $statement = $this->dbAdapter->createQuery($q);
 // $query_result = $statement->getResult();

        if (!$query_result)
            throw new Exception('Error In media table ' . mysql_error());
        if ($is_profile_pic) {
            $q_update = "UPDATE user SET profile_photo = '$is_profile_pic' WHERE user_id ='$user_id'";
            //$r = mysql_query($q_update);
             $statement1 = $this->dbAdapter->createStatement($q_update);
            $r = $statement1->execute();
            if (!$r)
                throw new Exception('Error : ' . mysql_error());
        }else {
            if(!empty($event_id)){
                 $tblEventMedia= new \Application\Entity\EventMedia();
            
				
                $tblEventMedia->media_id=$media_id;
              
                
                $tblEventMedia->event_id=$event_id;
                
                
                
                $this->dbAdapter->persist($tblEventMedia);
                $this->dbAdapter->flush();
                
        //   $q_event_media = "INSERT INTO Application\Entity\eventMedia (media_id, event_id) VALUES ('$media_id', '$event_id')";
            //$query_result1 = mysql_query($q_event_media);
        //    $statement1 = $this->dbAdapter->createStatement($q_event_media);
          //  $query_result1 = $statement1->execute();
         //  $statement = $this->dbAdapter->createQuery($q_event_media);
 // $query_result1 = $statement->getResult();
            if (!$query_result1)
                throw new Exception('Error : ' . mysql_error());
            }
        }
//check media table entry (only for testing)
//$q1 = mysql_query("select * from media where media_id='".$media_id."'") or die(mysql_error());
//if($row=mysql_fetch_assoc($q1))
//print_r ($row);
        if(!$is_audio){
        $message_data = array(
            'user_id' => $user_id,
            'media_id' => $media_id,
            'content_type' => $content_type,
            's3path' => $s3path,
            's3file_name' => $s3file_name,
            'isVideo' => $isVideo,
            'email' => $email
        );
//        echo "<pre>";
//                print_r($message_data);
//Process Message here - 
         $aws_manager = new AWSManager();
        $response = $aws_manager->snsProcessMediaPublish($message_data);
//        echo "<pre>";
//        print_r($response);//exit;
//        var_dump($response);
//        
//        s2
        //what should condition over here
        if ($response ==1) {
            $status = 'Success';
            $message = "Media Successfully add";
        }
        else
            throw new Exception('Error In snsProcessMediaPublish');
        }else{
            $status = 'Success';
            $message = "Media Successfully add";
        }
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
