<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ViewEvents {

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
$user_id = trim($data->viewevent->user_id);
$is_my_event = trim($data->viewevent->is_my_event);
$is_friend_event = trim($data->viewevent->is_friend_event);
$is_public_event = trim($data->viewevent->is_public_event);
$page = trim($data->viewevent->page);
$limit = trim($data->viewevent->limit);
$error_flag = 0;
$type = "";
//------------------set default limit----------------------
if (!isset($limit) || empty($limit)) {
    $limit = 10;
}
$totlecount = 0;
$from = ($page - 1) * $limit;
$date = strtotime(date('d-m-Y'));
header("Content-type: text/xml");
$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
$xml_output .= "<xml><viewevents>";
//---------------------------my events----------------------------
if ($is_my_event) {
    $query_event = "select * 
                from event 
                where user_id='$user_id' 
                    and  (viewable_to >=" . $date . " or viewable_to ='')
                    and  (viewable_from <=" . $date . " or viewable_from ='') 
                    and  (self_destruct >=" . $date . " or self_destruct='') 
                ORDER BY event.create_time DESC
                LIMIT $from , $limit";
//    exit;
   // $result_event = mysql_query($query_event);
    $statement = $this->dbAdapter->createStatement($query_event);
            $result_event = $statement->execute();
            $row = $result_event->current();

    if ($result_event) {
        if ($result_event->count() <= 0) {
            $xml_output.="<status>Failure</status>";
            $xml_output.="<message>No Record Found </message>";
            $xml_output.="<events><event>";
            $xml_output.="<event_id></event_id>";
            $xml_output.="<event_name></event_name>";
            $xml_output.="<friend_can_post></friend_can_post>";
            $xml_output.="<friend_can_share></friend_can_share>";
            $xml_output.="<event_media_type></event_media_type>";
            $xml_output.="<event_media_url></event_media_url>";
            $xml_output.="<event_media_id></event_media_id>";
            $xml_output.="<event_media_video_thum></event_media_video_thum>";
            $xml_output.="<event_media_79x80></event_media_79x80>";
            $xml_output.="<event_media_98x78></event_media_98x78>";
            $xml_output.="<event_media_448x306></event_media_448x306>";
            $xml_output.= "</event></events>";
        } else {
            $xml_output.="<status>Success</status>";
            $xml_output.="<message>My Events List</message>";
            $xml_output.="<page>$page</page>";
            $xml_output.="<events>";
        }if ($result_event->count() > 0) {
            while ($row = mysql_fetch_assoc($result_event)) {//get media
                $xml_output.="<event>";
                $xml_output.="<event_id>" . $row['event_id'] . "</event_id>";
                $xml_output.="<event_name>" . $row['name'] . "</event_name>";

                $xml_output.="<friend_can_post>" . $row['friends_can_post'] . "</friend_can_post>";
                $xml_output.="<friend_can_share>" . $row['friends_can_share'] . "</friend_can_share>";

                $query_event_media = "SELECT event.event_id,event.name,media.media_id,media.metadata
                                    FROM event_media inner join event on event.event_id=event_media.event_id
                                    inner join media on event_media.media_id=media.media_id
                                    where event.user_id='$user_id' and event.event_id='" . $row['event_id'] . "' ORDER BY media.create_date DESC";
                //$query_event_media_result = mysql_query($query_event_media);
                $statement1 = $this->dbAdapter->createStatement($query_event_media);
            $query_event_media_result = $statement1->execute();
          //  $row = $query_event_media_result->current();

                if (!$query_event_media_result) {
                    $xml_output.="<status>Failure</status>";
                    $xml_output.="<message>" . mysql_error() . "</message>";
                    $xml_output.="<events><event>";
                    $xml_output.="<event_id></event_id>";
                    $xml_output.="<event_name></event_name>";
                    $xml_output.="<event_media_type></event_media_type>";
                    $xml_output.="<event_media_url></event_media_url>";
                    $xml_output.="<event_media_id></event_media_id>";
                    $xml_output.="<event_media_video_thum></event_media_video_thum>";
                    $xml_output.= "</event></events>";
                } else {

                    if ($query_event_media_result->count() > 0) {

                        while ($row1 = $query_event_media_result->next()) {
                            $url = "";
                            $type = "";
                            $thum_url = '';
                            $url79x80 = '';
                            $url448x306 = '';
                            $url98x78 = '';
                            if (isset($row1['metadata'])) {
                                $json_array = json_decode($row1['metadata'], true);
                                $url = $json_array['S3_files']['path'];
                                if (isset($json_array['type']['image']) && is_array($json_array['type']['image'])) {
                                    $type = "image";
                                    $url79x80 = $json_array['S3_files']['79x80'];
                                    $url448x306 = $json_array['S3_files']['448x306'];
                                    $url98x78 = $json_array['S3_files']['98x78'];
                                } else if (isset($json_array['type']['video']) && is_array($json_array['type']['video'])) {
                                    $type = "video";
                                    $thum_url = isset($json_array['S3_files']['1080p_thumbails'][0]['Full']) ? $json_array['S3_files']['1080p_thumbails'][0]['Full'] : ''; //get video thum
                                    $url79x80 = isset($json_array['S3_files']['1080p_thumbails'][1]['79x80']) ? $json_array['S3_files']['1080p_thumbails'][1]['79x80'] : '';
                                    $url448x306 = isset($json_array['S3_files']['1080p_thumbails'][2]['448x306']) ? $json_array['S3_files']['1080p_thumbails'][2]['448x306'] : '';
                                    $url98x78 = isset($json_array['S3_files']['1080p_thumbails'][3]['98x78']) ? $json_array['S3_files']['1080p_thumbails'][3]['98x78'] : '';
                                } else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio']))
                                    continue;
                                else
                                    $type = "Type not Mentioned";
                            }
                            $xml_output.="<event_media_type>" . $type . "</event_media_type>";
                            $xml_output.=(!empty($url)) ? "<event_media_url><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url . "]]></event_media_url>" : '<event_media_url></event_media_url>';
                            $xml_output.="<event_media_id>" . $row1['media_id'] . "</event_media_id>";
                            $xml_output.=(!empty($thum_url)) ? "<event_media_video_thum>" . CLOUDFRONT_DOWNLOAD_HOST . $thum_url . "</event_media_video_thum>" : "<event_media_video_thum></event_media_video_thum>";
                            $xml_output.=(!empty($url79x80)) ? "<event_media_79x80><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url79x80 . "]]></event_media_79x80>" : "<event_media_79x80/>";
                            $xml_output.=(!empty($url98x78)) ? "<event_media_98x78><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url98x78 . "]]></event_media_98x78>" : "<event_media_98x78/>";
                            $xml_output.=(!empty($url448x306)) ? "<event_media_448x306><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url448x306 . "]]></event_media_448x306>" : "<event_media_448x306/>";
                            break;
                        }
                    } else {
                        $xml_output.="<event_media_type></event_media_type>";
                        $xml_output.="<event_media_url></event_media_url>";
                        $xml_output.="<event_media_id></event_media_id>";
                        $xml_output.="<event_media_video_thum></event_media_video_thum>";
                        $xml_output.="<event_media_79x80></event_media_79x80>";
                        $xml_output.="<event_media_98x78></event_media_98x78>";
                        $xml_output.="<event_media_448x306></event_media_448x306>";
                    }
                }
                $xml_output.="</event>";
            }
            $xml_output.="</events>";
        }
    } else {
        $xml_output.="<status>Failure</status>";
        $xml_output.="<message>" . mysql_error() . "</message>";
        $xml_output.="<events><event>";
        $xml_output.="<event_id></event_id>";
        $xml_output.="<event_name></event_name>";
        $xml_output.="<friend_can_post></friend_can_post>";
        $xml_output.="<friend_can_share></friend_can_share>";
        $xml_output.="<event_media_type></event_media_type>";
        $xml_output.="<event_media_url></event_media_url>";
        $xml_output.="<event_media_id></event_media_id>";
        $xml_output.="<event_media_video_thum></event_media_video_thum>";
        $xml_output.="<event_media_79x80></event_media_79x80>";
        $xml_output.="<event_media_98x78></event_media_98x78>";
        $xml_output.="<event_media_448x306></event_media_448x306>";
        $xml_output.= "</event></events>";
    }
}
//------------------------for friends event-------------------------
if ($is_friend_event) {
    //get friend id for repected user id
    //for singal user at a time friend id and friend id both r deffer
    $getfriendid_loginuser = "SELECT *
       from friend
       where network='memreas' and social_username in(
                select username
                from user
                where user_id='$user_id')";
 //   $result_getfriendid = mysql_query($getfriendid_loginuser);
    $statement = $this->dbAdapter->createStatement($getfriendid_loginuser);
            $result_getfriendid = $statement->execute();
           // $row = $result->current();

    if (!$result_getfriendid) {
        $error_flag = 1;
        $message = mysql_error();
    } else if ($result_getfriendid->count() <= 0) {
        $error_flag = 2;
        $message = "No Record Found";
    } else {

        while ($row_getfriendid =$result_getfriendid->next()) {
//        print_r($row_getfriendid);
//         $q_getuserid = "SELECT user_id FROM user_friend WHERE friend_id='" . $row_getfriendid['friend_id'] . "'";
//        $result_getuserid = mysql_query($q_getuserid);
//        if (!$result_getuserid) {
//            $error_flag = 1;
//            $message = mysql_error();
//        } else if (mysql_num_rows($result_getuserid) <= 0) {
//            $error_flag = 2;
//            $message = "No Record Found";
//        }
//        else {
            $xml_output.="<status>Success</status>";
            $xml_output.="<message>My Events List</message>";
            $xml_output.="<page>$page</page>";
//            while ($row_getuserid = mysql_fetch_assoc($result_getuserid)) {
//                print_r($row_getuserid);
//             $q_getfriendid = "SELECT *
//       from friend
//       where network='memreas' and social_username in(
//                select username
//                from user
//                where user_id='" . $row_getuserid['user_id'] . "')";
//                $result_getcreaterfriendid = mysql_query($q_getfriendid);
//                if (!$result_getcreaterfriendid) {
//                    $error_flag = 1;
//                    $message = mysql_error();
//                } else {
//                    $row_eventcreater = mysql_fetch_assoc($result_getcreaterfriendid);
//                    print_r($row_eventcreater);
            $q_friendsevent = "select event.event_id,event.name,friends_can_share,friends_can_post,user.*
                    from event_friend,event,user
                    where event.user_id=user.user_id and
                    event.event_id=event_friend.event_id
                    and  (event.viewable_to >=" . $date . " or event.viewable_to ='')
                    and  (event.viewable_from <=" . $date . " or event.viewable_from ='')
                    and  (event.self_destruct >=" . $date . " or event.self_destruct='') 
                    and event_friend.friend_id='" . $row_getfriendid['friend_id'] . "' ORDER BY `user`.`username` ASC,`event`.`create_time` DESC ";
           // $result_friendevent = mysql_query($q_friendsevent) or die(mysql_error());
            $statement = $this->dbAdapter->createStatement($q_friendsevent);
            $result_friendevent = $statement->execute();
           // $row = $result->current();

            $xml_output.="<friends>";
            $user_id = null;
            while ($row_friendsevent = $result_friendevent->next()) {
//                echo "<pre>";
//                print_r($row_friendsevent);
                $array[$row_friendsevent['username']][] = $row_friendsevent;
            }
//            echo "<pre>";print_r($array);exit;
            foreach ($array as $key => $value) {
//                echo "<pre>";print_r($value);
                $url1 = null;
                $xml_output.="<friend>";
                $xml_output.="<event_creator>" . $value[0]['username'] . "</event_creator>";
                if ($value[0]['profile_photo']) {
                    $q = "SELECT * FROM `media` WHERE `user_id` LIKE '" . $value[0]['user_id'] . "' AND `is_profile_pic` =1";
                   // $re = mysql_query($q);
                  $statement = $this->dbAdapter->createStatement($q);
            $re = $statement->execute();
            //$row = $result->current();

                   $row = $re->next();
                    $json_array = json_decode($row['metadata'], true);
                    if (!empty($json_array['S3_files']['path']))
                        $url1 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['path'];

                    if (!empty($json_array['S3_files']['79x80']))
                        $pic_79x80 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['79x80'];

                    if (!empty($json_array['S3_files']['448x306']))
                        $pic_448x306 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['448x306'];

                    if (!empty($json_array['S3_files']['98x78']))
                        $pic_98x78 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['98x78'];
                }
                $xml_output.="<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";
                $xml_output.="<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
                $xml_output.="<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
                $xml_output.="<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";

                $xml_output.="<event_creator_user_id>" . $value[0]['user_id'] . "</event_creator_user_id>";
                $xml_output.="<events>";
                foreach ($value as $row_friendsevent) {
//                print_r($row_friendsevent);
                    $url = '';

                    $xml_output.="<event>";
                    $xml_output.="<event_id>" . $row_friendsevent['event_id'] . "</event_id>";
                    $xml_output.="<event_name>" . $row_friendsevent['name'] . "</event_name>";
                    $xml_output.="<friend_can_post>" . $row_friendsevent['friends_can_post'] . "</friend_can_post>";
                    $xml_output.="<friend_can_share>" . $row_friendsevent['friends_can_share'] . "</friend_can_share>";
                    $query_event_media = "SELECT event.event_id,event.name,media.media_id,media.metadata
                                    FROM event_media inner join event on event.event_id=event_media.event_id
                                    inner join media on event_media.media_id=media.media_id
                                    where  event.event_id='" . $row_friendsevent['event_id'] . "' ORDER BY media.create_date DESC";
                    $query_event_media_result = mysql_query($query_event_media) or die(mysql_error());
                    if (mysql_num_rows($query_event_media_result) > 0) {
                        while ($row = mysql_fetch_assoc($query_event_media_result)) {
                            $url = '';
                            $type = "";
                            $thum_url = '';
                            $url79x80 = '';
                            $url448x306 = '';
                            $url98x78 = '';
                            if (isset($row['metadata'])) {
                                $json_array = json_decode($row['metadata'], true);
                                $url = $json_array['S3_files']['path'];
                                if (isset($json_array['type']['image']) && is_array($json_array['type']['image'])) {
                                    $type = "image";
                                    $url79x80 = $json_array['S3_files']['79x80'];
                                    $url448x306 = $json_array['S3_files']['448x306'];
                                    $url98x78 = $json_array['S3_files']['98x78'];
                                } else if (isset($json_array['type']['video']) && is_array($json_array['type']['video'])) {
                                    $type = "video";
                                    $thum_url = isset($json_array['S3_files']['video_thum_path']) ? $json_array['S3_files']['video_thum_path'] : '';
                                    $url79x80 = isset($json_array['S3_files']['video_thum_79x80']) ? $json_array['S3_files']['video_thum_79x80'] : '';
                                    $url448x306 = isset($json_array['S3_files']['video_thum_448x306']) ? $json_array['S3_files']['video_thum_448x306'] : '';
                                    $url98x78 = isset($json_array['S3_files']['video_thum_98x78']) ? $json_array['S3_files']['video_thum_98x78'] : '';
                                } else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio']))
                                    continue;
                                else
                                    $type = "Type not Mentioned";
                            }
                            $xml_output.="<event_media_type>" . $type . "</event_media_type>";
                            $xml_output.=(!empty($url)) ? "<event_media_url><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url . "]]></event_media_url>" : "<event_media_url/>";
                            $xml_output.="<event_media_id>" . $row['media_id'] . "</event_media_id>";
                            $xml_output.=(!empty($thum_url)) ? "<event_media_video_thum>" . CLOUDFRONT_DOWNLOAD_HOST . $thum_url . "</event_media_video_thum>" : "<event_media_video_thum/>";
                            $xml_output.=(!empty($url79x80)) ? "<event_media_79x80><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url79x80 . "]]></event_media_79x80>" : "<event_media_79x80/>";
                            $xml_output.=(!empty($url98x78)) ? "<event_media_98x78><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url98x78 . "]]></event_media_98x78>" : "<event_media_98x78/>";
                            $xml_output.=(!empty($url448x306)) ? "<event_media_448x306><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url448x306 . "]]></event_media_448x306>" : "<event_media_448x306/>";
                            break;
                        }
                    }else {
                        $xml_output.="<event_media_type></event_media_type>";
                        $xml_output.="<event_media_url><![CDATA[]]></event_media_url>";
                        $xml_output.="<event_media_id></event_media_id>";
                        $xml_output.="<event_media_video_thum></event_media_video_thum>";
                        $xml_output.="<event_media_79x80></event_media_79x80>";
                        $xml_output.="<event_media_98x78></event_media_98x78>";
                        $xml_output.="<event_media_448x306></event_media_448x306>";
                    }
                    $xml_output.="</event>";
                }
                $xml_output.="</events>";
                $xml_output.="</friend>";
            }

            $xml_output.="</friends>";

//                }
//            }
//        }
        }
    }
}

/*
  if ($is_friend_event) {


  //   $q = "SELECT  event.event_id ,event.name,uf.friend_id,friend.social_username,friend.url_image
  //          FROM user_friend as uf
  //          inner join friend on uf.friend_id=friend.friend_id
  //          inner join event  on uf.friend_id=event.user_id
  //          where uf.user_id='$user_id'
  //          ORDER BY uf.friend_id ASC
  //            LIMIT $from , $limit";//
  //get friend id
  $getuser="SELECT friend.friend_id ,event_friend.event_id
  from friend
  where network='memreas' and social_username in(
  select username
  from user
  where user_id='$user_id')";
  $resultgetuser = mysql_query($getuser);
  if (!$resultgetuser) {
  $error_flag = 1;
  $message = mysql_error();
  } else if (mysql_num_rows($resultgetuser) <= 0) {
  $error_flag = 2;
  $message = "No Record Found";
  } else if (mysql_num_rows($resultgetuser) > 0) {
  $row_getuser=  mysql_fetch_assoc($resultgetuser);
  //-------------------get user's friends and his name & pic
  $q = "SELECT  uf.friend_id,uf.user_id,friend.social_username,friend.url_image
  FROM user_friend as uf
  inner join friend on uf.friend_id=friend.friend_id
  where uf.friend_id='".$row_getuser['friend_id']."'
  ORDER BY uf.friend_id ASC
  LIMIT $from , $limit";
  $result = mysql_query($q);
  if (!$result) {
  $error_flag = 1;
  $message = mysql_error();
  } else if (mysql_num_rows($result) <= 0) {
  $error_flag = 2;
  $message = "No Record Found";
  } else if (mysql_num_rows($result) > 0) {
  $xml_output.="<status>Success</status>";
  $xml_output.="<message>My Friends Event List</message>";
  $xml_output.="<page>$page</page>";

  while ($row2 = mysql_fetch_array($result)) {//get media
  //        echo "<pre>";print_r($row2);
  //            $q_freinds_event = "select event_id,name from event where user_id='" . $row2['user_id']."' ORDER BY create_time DESC";
  $q_freinds_event="SELECT event.*
  FROM friend
  INNER JOIN event_friend ON friend.friend_id = event_friend.friend_id
  INNER JOIN event ON event_friend.event_id = event.event_id
  where event_friend.friend_id='".$row_getuser['friend_id']."'";
  $rfe = mysql_query($q_freinds_event);
  if (!$rfe) {
  $error_flag = 1;
  $message = mysql_error();
  }
  //            else if (mysql_num_rows($rfe)<= 0){
  //                $error_flag = 2;
  //                $message ="Record not found";
  //            }
  else if (mysql_num_rows($rfe)> 0){
  //                print_r($rfe);
  $xml_output.="<friends>";
  $xml_output.="<friend>";
  $xml_output.="<event_creator>" . $row2['social_username'] . "</event_creator>";
  $xml_output.="<profile_pic><![CDATA[" . $row2['url_image'] . "]]></profile_pic>";
  $xml_output.="<event_creator_user_id>" . $row2['friend_id'] . "</event_creator_user_id>";
  $xml_output.="<events>";
  while ($row4 = mysql_fetch_assoc($rfe)) {
  $xml_output.="<event>";
  $xml_output.="<event_id>" . $row4['event_id'] . "</event_id>";
  $xml_output.="<event_name>" . $row4['name'] . "</event_name>";

  $query_event_friend = "SELECT  event.event_id ,event.name,media.media_id,media.metadata
  FROM event inner join event_media on event.event_id=event_media.event_id
  inner join media on event_media.media_id=media.media_id
  where event.event_id='" . $row4['event_id'] . "' and  event.event_id='".$row4['event_id']."' ORDER BY media.create_date DESC LIMIT 1";
  $result_event_friend = mysql_query($query_event_friend);
  if ($result_event_friend) {
  if ($row = mysql_fetch_assoc($result_event_friend)) {
  $url = '';
  $type="";
  if (isset($row['metadata'])) {
  $json_array = json_decode($row['metadata'], true);
  $url = $json_array['S3_files']['path'];
  if (isset($json_array['type']['image']) && is_array($json_array['type']['image']))
  $type = "image";
  else if (isset($json_array['type']['video']) && is_array($json_array['type']['video']))
  $type = "video";
  else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio']))
  $type = "audio";
  else
  $type = "Type not Mentioned";
  }
  $xml_output.="<event_media_type>" . $type . "</event_media_type>";
  $xml_output.="<event_media_url><![CDATA[" . $url . "]]></event_media_url>";
  $xml_output.="<event_media_id>" . $row['media_id'] . "</event_media_id>";
  }
  } else {
  $xml_output.="<event_media_type></event_media_type>";
  $xml_output.="<event_media_url></event_media_url>";
  $xml_output.="<event_media_id></event_media_id>";
  }
  $xml_output.= "</event>";
  }$xml_output.= "</events>";
  $xml_output.="</friend>";
  $xml_output.="</friends>";
  }
  }
  }} */

if ($error_flag) {
//    echo $xml_output;
    $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
    $xml_output .= "<xml><viewevents>";
    $xml_output.="<status>Failure</status>";
    $xml_output.="<message>$message</message>";
    $xml_output.="<friends>";
    $xml_output.="<friend>";
    $xml_output.="<event_creator></event_creator>";
    $xml_output.="<profile_pic></profile_pic>";
    $xml_output.="<profile_pic_79x80></profile_pic_79x80>";
    $xml_output.="<profile_pic_448x306></profile_pic_448x306>";
    $xml_output.="<profile_pic_98x78></profile_pic_98x78>";
    $xml_output.="<event_creator_user_id></event_creator_user_id>";

    $xml_output.="<events><event>";
    $xml_output.="<event_id></event_id>";
    $xml_output.="<event_name></event_name>";
    $xml_output.="<friend_can_post></friend_can_post>";
    $xml_output.="<friend_can_share></friend_can_share>";
    $xml_output.="<event_media_type></event_media_type>";
    $xml_output.="<event_media_url></event_media_url>";
    $xml_output.="<event_media_id></event_media_id>";
    $xml_output.="<event_media_video_thum></event_media_video_thum>";
    $xml_output.="<event_media_79x80></event_media_79x80>";
    $xml_output.="<event_media_98x78></event_media_98x78>";
    $xml_output.="<event_media_448x306></event_media_448x306>";
    $xml_output.= "</event></events>";
    $xml_output.="</friend>";
    $xml_output.="</friends>";
}
//  }
//-----------------------------public events-----------------------------
if ($is_public_event) {

    $q_public = "select distinct event.user_id,event.user_id ,user.username,user.profile_photo
    from event inner join user on event.user_id=user.user_id
    where event.public=1  
    and event.user_id != '$user_id' 
    and  (event.viewable_to >=" . $date . " or event.viewable_to ='')
    and  (event.viewable_from <=" . $date . " or event.viewable_from ='')     
    and  (event.self_destruct >=" . $date . " or event.self_destruct='') 
    ORDER BY event.create_time DESC LIMIT $from , $limit";
  //  $result_pub = mysql_query($q_public);
    $statement = $this->dbAdapter->createStatement($q_public);
            $result_pub = $statement->execute();
            //$row = $result->current();

    if (!$result_pub) {
        $xml_output.="<status>Failure</status>";
        $xml_output.="<message>" . mysql_error() . "</message>";
        $xml_output.="<page>0</page>";
        $xml_output.="<friends>";
        $xml_output.="<friend>";
        $xml_output.="<event_creator></event_creator>";
        $xml_output.="<profile_pic><![CDATA[]]></profile_pic>";
        $xml_output.="<profile_pic_79x80></profile_pic_79x80>";
        $xml_output.="<profile_pic_448x306></profile_pic_448x306>";
        $xml_output.="<profile_pic_98x78></profile_pic_98x78>";
        $xml_output.="<event_creator_user_id></event_creator_user_id>";
        $xml_output.="<events><event>";
        $xml_output.="<event_id></event_id>";
        $xml_output.="<event_name></event_name>";
        $xml_output.="<friend_can_post></friend_can_post>";
        $xml_output.="<friend_can_share></friend_can_share>";
        $xml_output.="<event_media_type></event_media_type>";
        $xml_output.="<event_media_url></event_media_url>";
        $xml_output.="<event_media_id></event_media_id>";
        $xml_output.="<event_media_video_thum></event_media_video_thum>";
        $xml_output.="<event_media_79x80></event_media_79x80>";
        $xml_output.="<event_media_98x78></event_media_98x78>";
        $xml_output.="<event_media_448x306></event_media_448x306>";
        $xml_output.= "</event></events>";
        $xml_output.="</friend>";
        $xml_output.="</friends>";
    }
    if ($result_pub->count() <= 0) {
        $xml_output.="<status>Failure</status>";
        $xml_output.="<message>No record found</message>";
        $xml_output.="<page>0</page>";
        $xml_output.="<friends>";
        $xml_output.="<friend>";
        $xml_output.="<event_creator></event_creator>";
        $xml_output.="<profile_pic><![CDATA[]]></profile_pic>";
        $xml_output.="<profile_pic_79x80></profile_pic_79x80>";
        $xml_output.="<profile_pic_448x306></profile_pic_448x306>";
        $xml_output.="<profile_pic_98x78></profile_pic_98x78>";
        $xml_output.="<event_creator_user_id></event_creator_user_id>";
        $xml_output.="<events><event>";
        $xml_output.="<event_id></event_id>";
        $xml_output.="<event_name></event_name>";
        $xml_output.="<friend_can_post></friend_can_post>";
        $xml_output.="<friend_can_share></friend_can_share>";
        $xml_output.="<event_media_type></event_media_type>";
        $xml_output.="<event_media_url></event_media_url>";
        $xml_output.="<event_media_id></event_media_id>";
        $xml_output.="<event_media_video_thum></event_media_video_thum>";
        $xml_output.="<event_media_79x80></event_media_79x80>";
        $xml_output.="<event_media_98x78></event_media_98x78>";
        $xml_output.="<event_media_448x306></event_media_448x306>";
        $xml_output.= "</event>";
        $xml_output.="</events></friend>";
        $xml_output.="</friends>";
    } else {
        $xml_output.="<status>Success</status>";
        $xml_output.="<message>Public Event List</message>";
        $xml_output.="<page>$page</page>";
        $xml_output.="<friends>";

        while ($row3 = $result_pub->next()) {
            $pic = '';
            $xml_output.="<friend>";
            $xml_output.="<event_creator>" . $row3['username'] . "</event_creator>";
            if ($row3['profile_photo']) {

                $q_profile_photo = "select * from media where is_profile_pic=1 and user_id='" . $row3['user_id'] . "' limit 1";
               // $result_profile_pic = mysql_query($q_profile_photo) or die(mysql_error());
                $statement = $this->dbAdapter->createStatement($q_profile_photo);
            $result_profile_pic = $statement->execute();
            //$row = $result->current();

                if ($result_profile_pic) {
                    if ($row6 = $result_profile_pic->next()) {
                        $json_array = json_decode($row6['metadata'], true);
//                        echo "<pre>";
//                        print_r($json_array['S3_files']);
                        if (!empty($json_array['S3_files']['path']))
                            $pic = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['path'];

                        if (!empty($json_array['S3_files']['79x80']))
                            $pic_79x80 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['79x80'];

                        if (!empty($json_array['S3_files']['448x306']))
                            $pic_448x306 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['448x306'];

                        if (!empty($json_array['S3_files']['98x78']))
                            $pic_98x78 = CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['98x78'];
                    }
                }
            }
            $xml_output.="<profile_pic><![CDATA[" . $pic . "]]></profile_pic>";
            $xml_output.="<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
            $xml_output.="<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
            $xml_output.="<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";
            $xml_output.="<event_creator_user_id>" . $row3['user_id'] . "</event_creator_user_id>";
            $qub_event = "select * 
                    from event 
                    where public=1 and user_id='" . $row3['user_id'] . "' 
                          and  (event.viewable_to >=" . $date . " or event.viewable_to ='')
                          and  (event.viewable_from <=" . $date . " or event.viewable_from ='')
                          and  (event.self_destruct >=" . $date . " or event.self_destruct='') 
                          ORDER BY event.create_time DESC";
           // $result2 = mysql_query($qub_event) or die(mysql_error
            $statement = $this->dbAdapter->createStatement($qub_event);
            $result2 = $statement->execute();
           // $row = $result->current();

            if ($result2) {
                $xml_output.="<events>";
                while ($row5 = mysql_fetch_array($result2)) {

                    $xml_output.="<event>";
                    $xml_output.="<event_id>" . $row5['event_id'] . "</event_id>";
                    $xml_output.="<event_name>" . $row5['name'] . "</event_name>";
                    $xml_output.="<friend_can_post>" . $row5['friends_can_post'] . "</friend_can_post>";
                    $xml_output.="<friend_can_share>" . $row5['friends_can_share'] . "</friend_can_share>";
                    $query_event_public = "SELECT event_media.event_id,media.media_id,media.metadata
                                    FROM media inner join event_media
                                    on  event_media.media_id=media.media_id 
                                    where event_media.event_id='" . $row5['event_id'] . "' and event_media.event_id='" . $row5['event_id'] . "' ORDER BY media.create_date DESC";
                    //$result_event_public = mysql_query($query_event_public) or die(mysql_error());
                    
                    $statement = $this->dbAdapter->createStatement($query_event_public);
            $result_event_public = $statement->execute();
            //$row = $result->current();

                    $only_audio_in_event = 0;
                    if ($result_event_public->count() > 0) {
                        while ($row = $result_event_public->next()) {
//                            echo "<pre>";
//                            print_r($row);
                            $url = '';
                            $type = "";
                            $thum_url = '';
                            $url79x80 = '';
                            $url448x306 = '';
                            $url98x78 = '';
                            
                            if (isset($row['metadata'])) {
                                $json_array = json_decode($row['metadata'], true);
                                $url = $json_array['S3_files']['path'];
                                if (isset($json_array['type']['image']) && is_array($json_array['type']['image'])) {
                                    $type = "image";
                                    $url79x80 = $json_array['S3_files']['79x80'];
                                    $url448x306 = $json_array['S3_files']['448x306'];
                                    $url98x78 = $json_array['S3_files']['98x78'];
                                } else if (isset($json_array['type']['video']) && is_array($json_array['type']['video'])) {
                                    $type = "video";
                                    $thum_url = isset($json_array['S3_files']['1080p_thumbails'][0]) ? $json_array['S3_files']['1080p_thumbails'][0] : '';
                                    $url79x80 = '';
                                    $url448x306 = '';
                                    $url98x78 = '';
                                } else if (isset($json_array['type']['audio']) && is_array($json_array['type']['audio'])) {
                                    $only_audio_in_event = 1;
                                    continue;
                                }
                                else
                                    $type = "Type not Mentioned";
                            }
                            $only_audio_in_event = 0;
                            $xml_output.="<event_media_type>" . $type . "</event_media_type>";
                            $xml_output.=(!empty($url)) ? "<event_media_url><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url . "]]></event_media_url>" : "<event_media_url/>";
                            $xml_output.="<event_media_id>" . $row['media_id'] . "</event_media_id>";
                            $xml_output.=(!empty($thum_url)) ? "<event_media_video_thum><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $thum_url . "]]></event_media_video_thum>" : "<event_media_video_thum/>";
                            $xml_output.=(!empty($url79x80)) ? "<event_media_79x80><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url79x80 . "]]></event_media_79x80>" : "<event_media_79x80/>";
                            $xml_output.=(!empty($url98x78)) ? "<event_media_98x78><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url98x78 . "]]></event_media_98x78>" : "<event_media_98x78/>";
                            $xml_output.=(!empty($url448x306)) ? "<event_media_448x306><![CDATA[" . CLOUDFRONT_DOWNLOAD_HOST . $url448x306 . "]]></event_media_448x306>" : "<event_media_448x306/>";
                            break;
                        }if ($only_audio_in_event) {
                            $xml_output.="<event_media_type></event_media_type>";
                            $xml_output.="<event_media_url></event_media_url>";
                            $xml_output.="<event_media_id></event_media_id>";
                            $xml_output.="<event_media_video_thum></event_media_video_thum>";
                            $xml_output.="<event_media_79x80></event_media_79x80>";
                            $xml_output.="<event_media_98x78></event_media_98x78>";
                            $xml_output.="<event_media_448x306></event_media_448x306>";
                        }
                    } else {
                        $xml_output.="<event_media_type></event_media_type>";
                        $xml_output.="<event_media_url></event_media_url>";
                        $xml_output.="<event_media_id></event_media_id>";
                        $xml_output.="<event_media_video_thum></event_media_video_thum>";
                        $xml_output.="<event_media_79x80></event_media_79x80>";
                        $xml_output.="<event_media_98x78></event_media_98x78>";
                        $xml_output.="<event_media_448x306></event_media_448x306>";
                    }
                    $xml_output.=" </event>";
                }
                $xml_output.="</events>";
            }
            $xml_output.="</friend>";
        }
        $xml_output.="</friends>";
    }
}

$xml_output.='</viewevents>';
$xml_output.='</xml>';
echo $xml_output;

    }

}

?>
