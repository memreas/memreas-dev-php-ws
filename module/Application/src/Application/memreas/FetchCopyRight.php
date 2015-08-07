<?php
namespace Application\memreas;
use Application\memreas\MUUID;
use Application\memreas\Mlog;

class FetchCopyRight
{
    public function __construct($message_data, $memreas_tables, $service_locator) {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
    }
    
    public function exec ()
    {
        $copyright_id = '';
        $copyright_id_md5 = '';
        try {
            $data = simplexml_load_string($_POST['xml']);
            
            $status = 'failure';
            $media_id = MUUID::fetchUUID();
            $now = date('Y-m-d H:i:s');
            $user_id = $_SESSION['user_id'];
            $copyright_id = MUUID::fetchUUID();
            $copyright_id_md5 = md5($copyright_id);
            $meta_array = [];
            $meta_array['copyright_id'] = $copyright_id;
            $meta_array['copyright_id_md5'] = $copyright_id_md5;
            $metadata_json = json_encode($meta_array);
            
            $tblCopyright = new \Application\Entity\Copyright();
            $tblCopyright->copyright_id = $copyright_id;
            $tblCopyright->user_id = $user_id;
            $tblCopyright->media_id = $media_id;
            $tblCopyright->metadata = $metadata_json;
            $tblCopyright->validated = 0;
            $tblCopyright->create_date = $now;
            $tblCopyright->update_time = $now;
            $this->dbAdapter->persist($tblCopyright);
            $this->dbAdapter->flush();
            
            $status = 'success';
        } catch (Exception $e) {
            Mlog::addone('Caught exception: ', $e->getMessage());
        }
        
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<fetchcopyrightresponse>";
        $xml_output .= "<status>$status</status>";
        $xml_output .= "<media_id>$media_id</media_id>";
        $xml_output .= "<copyright_id_md5>$copyright_id_md5</copyright_id_md5>";
        $xml_output .= "</fetchcopyrightresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
        error_log("fetchcopyrightresponse ---> " . $xml_output . PHP_EOL);
    }
}
?>
