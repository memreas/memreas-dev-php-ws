<?php
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Aws\S3\S3Client;
use Application\Entity\EventMedia;

class DeletePhoto
{

    protected $message_data;

    protected $memreas_tables;

    protected $service_locator;

    protected $dbAdapter;

    protected $aws;

    protected $s3;

    public function __construct($message_data, $memreas_tables, $service_locator)
    {
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        // Fetch aws handle
        $this->aws = MemreasConstants::fetchAWS();
        // Fetch the S3 class
        $this->s3 = $this->aws->createS3();
    }

    public function exec()
    {
        $data = simplexml_load_string($_POST['xml']);
        $mediaid = trim($data->deletephoto->mediaid);
        
        error_log("Deleting ---> " . $mediaid . PHP_EOL);
        
        header("Content-type: text/xml");
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<deletephotoresponse>";
        
        if (isset($mediaid) && ! empty($mediaid)) {
            $seldata = "select m from Application\Entity\Media m where m.media_id='$mediaid'";
            
            $statement = $this->dbAdapter->createQuery($seldata);
            $resseldata = $statement->getResult();
            
            if (count($resseldata) > 0) {
                
                // Check if media related to any event
                $media_event = "SELECT em FROM Application\Entity\EventMedia em WHERE em.media_id = '$mediaid'";
                $statement = $this->dbAdapter->createQuery($media_event);
                $result = $statement->getResult();
                if (count($result) > 0) {
                    Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . 'fail::count($result)::', count($result));
                    $xml_output .= '<status>failure</status><message>This media is related to a memreas share.</message>';
                } else {
                    // Mlog::addone(__CLASS__ . __METHOD__ . LINE__ .
                    // 'metadata::',
                    // $resseldata[0]->metadata);
                    // $json_array = json_decode($resseldata[0]->metadata,
                    // true);
                    // if (isset($json_array['S3_files']['type']['image'])) {
                    // $imagename = basename($json_array['S3_files']['path']);
                    // }
                    
                    //
                    // memreasdevsec
                    //
                    $prefix = $resseldata[0]->user_id . '/' . $mediaid;
                    Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . '$prefix::', $prefix);
                    try {
                        
                        $iterator = $this->s3->getIterator('ListObjects', array(
                            'Bucket' => MemreasConstants::S3BUCKET,
                            'Prefix' => $prefix
                        ));
                        
                        foreach ($iterator as $object) {
                            $this->s3->deleteObject(array(
                                'Bucket' => MemreasConstants::S3BUCKET,
                                'Key' => $object['Key']
                            ));
                            Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::S3BUCKET::$object[Key]::deleted::', $object['Key']);
                        }
                        
                        $iterator = $this->s3->getIterator('ListObjects', array(
                            'Bucket' => MemreasConstants::S3HLSBUCKET,
                            'Prefix' => $prefix
                        ));
                        
                        foreach ($iterator as $object) {
                            $this->s3->deleteObject(array(
                                'Bucket' => MemreasConstants::S3HLSBUCKET,
                                'Key' => $object['Key']
                            ));
                            Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::S3HLSBUCKET::$object[Key]::deleted::', $object['Key']);
                        }
                    } catch (\Exception $e) {
                        Mlog::addone(__CLASS__ . __METHOD__ . LINE__ . 'Caught exception::', $e->getMessage());
                        Mlog::addone(__CLASS__ . __METHOD__ . LINE__ . 'Error deleting $prefix::', $prefix);
                    }
                    /*
                     * JM: 28-NOV-2014 below commented - won't work given above
                     * if...
                     */
                    // Remove event media related to this media also
                    // $query_event = "DELETE FROM Application\Entity\EventMedia
                    // em WHERE em.media_id='$mediaid'";
                    // $event_statement = $this->dbAdapter->createQuery (
                    // $query_event );
                    // $event_result = $event_statement->getResult ();
                    
                    try {
                        // Media
                        $delete_media = "DELETE FROM Application\Entity\Media m WHERE m.media_id='{$mediaid}'";
                        $media_statement = $this->dbAdapter->createQuery($delete_media);
                        $delete_media_result = $media_statement->getResult();
                        // Media Device
                        Mlog::addone("_SESSION", $_SESSION);
                        $user_id = $_SESSION['user_id'];
                        $delete_media_device = "DELETE FROM Application\Entity\MediaDevice m WHERE m.media_id='{$mediaid}' and m.user_id='{$user_id}' ";
                        $media_statement = $this->dbAdapter->createQuery($delete_media);
                        $delete_media_result = $media_statement->getResult();
                    } catch (\Exception $e) {
                        Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . 'Caught exception::', $e->getMessage());
                        Mlog::addone(__CLASS__ . __METHOD__ . __LINE__ . 'Error deleting from db::', $delete_media);
                    }
                    
                    // if (count ( $result ) > 0) {
                    if ($delete_media_result) {
                        $xml_output .= "<status>success</status>";
                        $xml_output .= "<message>Media removed successfully</message>";
                        Mlog::addone(__CLASS__ . __METHOD__ . LINE__, '::db entry deleted!');
                    } else {
                        $xml_output .= "<status>failure</status><message>An error occurred</message>";
                        Mlog::addone(__CLASS__ . __METHOD__ . LINE__, '::db entry delete failed');
                    }
                }
            } else
                $xml_output .= "<status>failure</status><message>Given media id is wrong.</message>";
        } else
            $xml_output .= "<status>failure</status><message>Please check media id specified.</message>";
        
        $xml_output .= "<media_id>{$mediaid}</media_id>";
        $xml_output .= "</deletephotoresponse>";
        $xml_output .= "</xml>\n";
        echo $xml_output;
    }
}
?>
