<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Aws\S3\S3Client;
use Application\Entity\EventMedia;

class DeletePhoto {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		$mediaid = trim ( $data->deletephoto->mediaid );

		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<deletephotoresponse>";

		if (isset ( $mediaid ) && ! empty ( $mediaid )) {
			$seldata = "select m from Application\Entity\Media m where m.media_id='$mediaid'";

			$statement = $this->dbAdapter->createQuery ( $seldata );
			$resseldata = $statement->getResult ();

			if (count ( $resseldata ) > 0) {

                //Check if media related to any event
                $media_event = "SELECT em FROM Application\Entity\EventMedia em WHERE em.media_id = '$mediaid'";
                $statement = $this->dbAdapter->createQuery($media_event);
                $result = $statement->getResult();
                if (count ($result) > 0)
                    $xml_output .= '<status>failure</status><message>This media is related to an event.</message>';
                else{
                    $json_array = json_decode ( $resseldata [0]->metadata, true );
                    if (isset ( $json_array ['S3_files'] ['type'] ['image'] )) {
                        $imagename = basename ( $json_array ['S3_files'] ['path'] );
                    }

                    $query = "DELETE FROM Application\Entity\Media m where m.media_id='$mediaid'";

                    $statement = $this->dbAdapter->createQuery ( $query );
                    $result = $statement->getResult ();

                    //Remove event media related to this media also
                    $query_event = "DELETE FROM Application\Entity\EventMedia em WHERE em.media_id='$mediaid'";
                    $event_statement = $this->dbAdapter->createQuery ( $query_event );
                    $event_result = $event_statement->getResult ();

                    /*
                     * Remove media resource on S3 AMZ - transcoded files should be removed also
                     * Specify your file element in Json metadata, they will be deleted all
                     */
                    $files_prepare = array('path', 'web', '1080p');
                    $S3Client = S3Client::factory(array('key' => MemreasConstants::S3_APPKEY, 'secret' => MemreasConstants::S3_APPSEC));

                    foreach ($files_prepare as $file){
                        //Checking for exist object
                        if (isset($json_array['S3_files'][$file]))
                            $S3Client->deleteObject(array(
                                'Bucket' => MemreasConstants::S3BUCKET,
                                'Key' => $json_array ['S3_files'] [$file]
                            ));
                    }

                    $session = new Container("user");
                    $S3Client->deleteMatchingObjects(MemreasConstants::S3BUCKET, $session->user_id.'/media/thumbnails/79x80/');
                    $S3Client->deleteMatchingObjects(MemreasConstants::S3BUCKET, $session->user_id.'/media/thumbnails/448x306/');
                    $S3Client->deleteMatchingObjects(MemreasConstants::S3BUCKET, $session->user_id.'/media/thumbnails/384x216/');
                    $S3Client->deleteMatchingObjects(MemreasConstants::S3BUCKET, $session->user_id.'/media/thumbnails/98x78/');
                    
                    if (count ( $result ) > 0) {
                        $xml_output .= "<status>success</status>";
                        $xml_output .= "<message>Photo deleted successfully</message>";
                    } else $xml_output .= "<status>failure</status><message>No record found</message>";
                }
			} else $xml_output .= "<status>failure</status><message>Given media id is wrong.</message>";
		} else $xml_output .= "<status>failure</status><message>Please check that you have given media id.</message>";

        $xml_output .= "<media_id>{$mediaid}</media_id>";
		$xml_output .= "</deletephotoresponse>";
		$xml_output .= "</xml>\n";
		echo $xml_output;
	}
}
?>
