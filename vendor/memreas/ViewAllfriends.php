<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ViewAllfriends {

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
        $user_id = $data->viewallfriends->user_id;
        $error_flag = 0;
        $count = 0;
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml><friends>";
//echo "<pre>";
//if (isset($data->viewallfriends->limit)) {
//    $pagelimit = $data->viewallfriends->limit;
//} else {
//    $pagelimit = 10;
//}
//
//$page = $data->viewallfriends->page;
//if (isset($data->viewallfriends->page)) {
//    $page = $data->viewallfriends->page;
//} else {
//    $page = 1;
//}
//$page = ($page - 1) * $pagelimit;
        if (!isset($user_id) || empty($user_id)) {
            $error_flag = 1;
            $message = 'User id is empty';
        } else {
            $q = "SELECT u
FROM Application\Entity\User u
WHERE u.user_id != '$user_id'
AND u.role=2
AND u.disable_account =0";

//$q1="SELECT *
//FROM `user_friend` AS uf, friend
//WHERE uf.friend_id = friend.friend_id
//AND uf.user_id = '$user_id' And network not like 'Memreas'";
//$result = mysql_query($q) or die(mysql_err);
            //$statement = $this->dbAdapter->createStatement($q);
            //         $result = $statement->execute();
            //       $row = $result->current();
            $statement = $this->dbAdapter->createQuery($q);
            $result = $statement->getResult();
 //$result1=  mysql_query($q1) or die(mysql_error());
            if (!$result) {
                $error_flag = 1;
                $message = mysql_error();
            }
//else if(!$result1){
//    $error_flag=1;
//    $message=  mysql_error();
//} 
            else {
                if (count($result) > 0
//            || mysql_num_rows($result1)>0
                ) {
                    $xml_output.="<status>Success</status><message>Friends list</message>";
                    foreach ( $result as $row1) {
                        $count++;
                        $view_all_friend[$count]['id'] = $row1->user_id;
                        if (isset($row1->facebook_username) && !empty($row1->facebook_username)) {
                            $view_all_friend[$count]['network'] = 'Facebook';
                        } elseif (isset($row1->twitter_username) && !empty($row1->twitter_username)) {
                            $view_all_friend[$count]['network'] = 'Twitter';
                        } else {
                            $view_all_friend[$count]['network'] = 'Memreas';
                        }
                        $view_all_friend[$count]['social_username'] = $row1->username;
                        $view_all_friend[$count]['url_image'] = '';
                        if (isset($row1->profile_photo) && !empty($row1->profile_photo) && $row1->profile_photo == 1) {
                            $q_profile_photo = "SELECT m
                                        FROM Application\Entity\Media m
                                        WHERE m.`user_id` LIKE '" . $row1->user_id . "'
                                        AND m.`is_profile_pic` =1";
                            // LIMIT 1";
                            $view_all_friend[$count]['q'] = $q_profile_photo;
                            //$r= mysql_query($q_profile_photo) or die(mysql_error());
                            // $statement1 = $this->dbAdapter->createStatement($q_profile_photo);
                            //$r = $statement1->execute();

                            $statement = $this->dbAdapter->createQuery($q_profile_photo);
                            $statement->setMaxResults(1);
                            $r = $statement->getResult();
                            if ($row2 = array_pop($r)) {
                                $json_array = json_decode($row2->metadata, true);
                                $view_all_friend[$count]['url_image'] = (empty($json_array['S3_files']['path'])) ? "" : MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['path'];
                                $view_all_friend[$count]['url_image_79x80'] = (empty($json_array['S3_files']['79x80'])) ? "" : MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['79x80'];
                                $view_all_friend[$count]['url_image_448x306'] = (empty($json_array['S3_files']['448x306'])) ? "" : MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['448x306'];
                                $view_all_friend[$count]['url_image_98x78'] = (empty($json_array['S3_files']['98x78'])) ? "" : MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['98x78'];
                            }
                        }
                    }
//    while ($row = mysql_fetch_array($result1)) {
//        $count++;
//        $view_all_friend[$count]['id']=$row['friend_id'];
//        $view_all_friend[$count]['network']=$row['network'];
//        $view_all_friend[$count]['social_username']=$row['social_username'];
//        $view_all_friend[$count]['url_image']=$row['url_image'];        
//    }
                } else {
                    $error_flag = 2;
                    $message = "No Record Found";
                }
            }
        }
        if ($error_flag) {
            $xml_output.="<status>Failure</status><message>$message</message>";
            $xml_output.="<friend>";
            $xml_output.="<friend_id></friend_id>";
            $xml_output.="<network></network>";
            $xml_output.="<social_username></social_username>";
            $xml_output.="<url><![CDATA[]]></url>";
            $xml_output.="<url_79x80><![CDATA[]]></url_79x80>";
            $xml_output.="<url_448x306><![CDATA[]]></url_448x306>";
            $xml_output.="<url_98x78><![CDATA[]]></url_98x78>";

            $xml_output.="</friend>";
        } else {
            //echo "<pre>";print_r($view_all_friend);
            foreach ($view_all_friend as $friend) {

                $xml_output.="<friend>";
                $xml_output.="<friend_id>" . $friend['id'] . "</friend_id>";
                $xml_output.="<network>" . $friend['network'] . "</network>";
                $xml_output.="<social_username>" . $friend['social_username'] . "</social_username>";
                $xml_output.="<url><![CDATA[" . $friend['url_image'] . "]]></url>";
                $xml_output.="<url_79x80><![CDATA[" . empty($friend['url_image_79x80']) ? '' : $friend['url_image_79x80'] . "]]></url_79x80>";
                $xml_output.="<url_448x306><![CDATA[" . empty($friend['url_image_448x306']) ? '' : $friend['url_image_448x306'] . "]]></url_448x306>";
                $xml_output.="<url_98x78><![CDATA[" . empty($friend['url_image_98x78']) ? '' : $friend['url_image_98x78'] . "]]></url_98x78>";
                $xml_output.="</friend>";
            }
        }
        $xml_output .= "</friends>";
        $group = "SELECT g  FROM Application\Entity\Group g  where g.user_id = '" . $user_id . "'";
//$res=mysql_query($group) or die(mysql_error());
//$statement = $this->dbAdapter->createStatement($group);
        //          $res = $statement->execute();
        $statement = $this->dbAdapter->createQuery($group);
        $res = $statement->getResult();
        $xml_output.="<groups>";
        if (count($res) <= 0) {
            $xml_output.="<group>";
            $xml_output.="<group_id></group_id>";
            $xml_output.="<group_name></group_name>";
            $xml_output.="</group>";
        }
        else
            foreach ($res as $row) {
                $xml_output.="<group>";
                $xml_output.="<group_id>" . $row->group_id . "</group_id>";
                $xml_output.="<group_name>" . $row->group_name . "</group_name>";
                $xml_output.="</group>";
            }
        $xml_output.="</groups>";
        $xml_output .= "</xml>";
//echo "<pre>";print_r($view_all_friend);
        echo $xml_output;
    }

}

?>
