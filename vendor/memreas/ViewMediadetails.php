<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;

class ViewMediadetails {

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
        $media_id = trim($data->viewmediadetails->media_id);
        $error_flag = 0;
        $totale_like = 0;
        $totale_comment = 0;
        $last_comment = '';
        $message = "";
        $path = '';
        $audio_text = '';
        if ((!isset($media_id)) || empty($media_id)) {
            $status = "Failure";
            $message = "Plz enter media id";
        } else {
            $q_like = "SELECT COUNT(type) as totale_like FROM comment WHERE media_id='$media_id' and type='like'";
            //$result_like = mysql_query($q_like);
            $statement = $this->dbAdapter->createStatement($q_like);
            $result_like = $statement->execute();
            if (!$result_like) {
                $status = "Failure";
                $message.= mysql_error();
            } else {
                $row_like = $result_like->current();
                $totale_like = $row_like['totale_like'];
            }
            $q_comment = "SELECT COUNT(type) as totale_comment FROM comment WHERE media_id='$media_id' and (type='text' or type='audio')";
            $q_last_comment = "select text,audio_id from comment where media_id='$media_id' and (type='text' or type='audio') ORDER BY `create_time` DESC limit 1";

            //$result_comment = mysql_query($q_comment);
            $statement = $this->dbAdapter->createStatement($q_comment);
            $result_comment = $statement->execute();
            // $result_last_comment = mysql_query($q_last_comment);
            $statement = $this->dbAdapter->createStatement($q_last_comment);
            $result_last_comment = $statement->execute();
            if (!$result_last_comment) {
                $status = "Failure";
                $message.= mysql_error();
            } else if ($result_last_comment->count() <= 0) {
                $status = "Success";
                $message = "No TEXT Comment For this media";
            } else {
                $row_last = $result_last_comment->current();
                $last_comment = $row_last['text'];
                if (!empty($row_last['audio_id'])) {
                    $qaudiotext = "Select media_id,metadata from media where media_id='" . $row_last['audio_id'] . "'";
                    //$result_audiotext = mysql_query($qaudiotext);
                    $statement = $this->dbAdapter->createStatement($qaudiotext);
                    $result_audiotext = $statement->execute();
                    if ($row1 = $result_audiotext->current()) {
                        $json_array = json_decode($row1['metadata'], true);
                        $path = $json_array['S3_files']['path'];
                    }
                }
            }
            if (!$result_comment) {
                $status = "Failure";
                $message.= mysql_error();
            } else {
                $row = $result_comment->current();
                $totale_comment = $row['totale_comment'];
                $status = "Success";
                $message.="Media Details";
            }


//    $q_comment = "Select media_id,metadata from media where media_id=(SELECT audio_id FROM comment WHERE comment.media_id='$media_id' and comment.type='audio' ORDER BY create_time DESC LIMIT 1)";
//    $result_audio = mysql_query($q_comment);
//    if (!$result_audio) {
//        $status = "Failure";
//        $message.= mysql_error();
//    } else if ($row1 = mysql_fetch_assoc($result_audio)) {
//        $qaudiotext = "SELECT text FROM comment WHERE comment.media_id='$media_id' and comment.type='audio' ORDER BY create_time DESC LIMIT 1";
//        $result_audiotext = mysql_query($qaudiotext);
//        if ($row = mysql_fetch_assoc($result_audiotext))
//            $audio_text = $row['text'];
//        $json_array = json_decode($row1['metadata'], true);
//        $path = $json_array['S3_files']['path'];
//    }
        }
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";

        $xml_output.= "<viewmediadetailresponse>";
        $xml_output.= "<status>$status</status>";
        $xml_output.= "<message>$message</message>";
        $xml_output.="<totle_like_on_media>$totale_like</totle_like_on_media>";
        $xml_output.="<totle_comment_on_media>$totale_comment</totle_comment_on_media>";
        $xml_output.="<last_comment>$last_comment</last_comment>";
        $xml_output.=(!empty($path)) ? "<audio_url>" . CLOUDFRONT_DOWNLOAD_HOST . $path . "</audio_url>" : "<audio_url></audio_url>";
        $xml_output.="<last_audiotext_comment>$audio_text</last_audiotext_comment>";
        $xml_output.= "</viewmediadetailresponse>";
        $xml_output.= "</xml>";
        echo $xml_output;
    }

}

?>
