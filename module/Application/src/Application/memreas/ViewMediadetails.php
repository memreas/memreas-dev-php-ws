<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\UUID;

class ViewMediadetails {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		// $this->dbAdapter = $service_locator->get('memreasdevdb');
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		$media_id = trim ( $data->viewmediadetails->media_id );
		$event_id = trim ( $data->viewmediadetails->event_id );
		$error_flag = 0;
		$totale_like = 0;
		$totale_comment = 0;
		$last_comment = '';
		$message = "";
		$path = '';
		$audio_text = '';
		if ((! isset ( $media_id )) || empty ( $media_id )) {
			$status = "Failure";
			$message = "Plz enter media id";
		} else {

			$q_like = "SELECT COUNT(c.type) as totale_like FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and c.type='like'";
			if(!empty($event_id)){

			$q_like = "SELECT COUNT(c.type) as totale_like FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and c.event_id='$event_id' and c.type='like'";

			}
 			// $result_like = mysql_query($q_like);
			// $statement = $this->dbAdapter->createStatement($q_like);
			// $result_like = $statement->execute();
			$statement = $this->dbAdapter->createQuery ( $q_like );
			$result_like = $statement->getResult ();

			if (! $result_like) {
				$status = "Failure";
				$message .= mysql_error ();
			} else {
				$row_like = array_pop ( $result_like );
				$totale_like = $row_like ['totale_like'];
			}
			$q_comment = "SELECT COUNT(c.type) as totale_comment FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and (c.type='text' or c.type='audio')";
			$q_last_comment = "select c.text,c.audio_id from Application\Entity\Comment c where c.media_id='$media_id' and (c.type='text' or c.type='audio') ORDER BY c.create_time DESC "; // limit 1";
			if(!empty($event_id)){
				$q_comment = "SELECT COUNT(c.type) as totale_comment FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and c.event_id='$event_id' and (c.type='text' or c.type='audio')";
				$q_last_comment = "select c.text,c.audio_id from Application\Entity\Comment c where c.media_id='$media_id'  and c.event_id='$event_id' and (c.type='text' or c.type='audio') ORDER BY c.create_time DESC "; // limit 1";
			
			}
	
	
			                                                                                                                                                                                // $result_comment = $statement->execute();
			$statement = $this->dbAdapter->createQuery ( $q_comment );
			$statement->setMaxResults ( 1 );

			$result_comment = $statement->getResult ();

			// $result_last_comment = mysql_query($q_last_comment);
			$statement = $this->dbAdapter->createQuery ( $q_last_comment );
			$result_last_comment = $statement->getResult ();

			if (count ( $result_last_comment ) <= 0) {
				$status = "Success";
				$message = "No TEXT Comment For this media";
			} else {
				$row_last = array_pop ( $result_last_comment );
				$last_comment = $row_last ['text'];
				if (! empty ( $row_last ['audio_id'] )) {
					$qaudiotext = "Select m.media_id,m.metadata from Application\Entity\Media m where m.media_id='" . $row_last ['audio_id'] . "'";
					// $result_audiotext = mysql_query($qaudiotext);
					$statement = $this->dbAdapter->createQuery ( $qaudiotext );
					$result_audiotext = $statement->getResult ();
					if ($row1 = array_pop ( $result_audiotext )) {
						$json_array = json_decode ( $row1 ['metadata'], true );
						$path = $json_array ['S3_files'] ['path'];
					}
				}
			}
			if (! $result_comment) {
				$status = "Failure";
				$message .= mysql_error ();
			} else {
				$row = array_pop ( $result_comment );
				$totale_comment = $row ['totale_comment'];
				$status = "Success";
				$message .= "Media Details";
			}

            //Get media location
            $location_query = "SELECT m.metadata FROM Application\Entity\Media m WHERE m.media_id = '{$media_id}'";
            $statement = $this->dbAdapter->createQuery($location_query);
            $resource = $statement->getResult();
            $metadata = json_decode($resource[0]['metadata']);
            $location = $metadata->S3_files->location;
            if (isset($location->longitude) && !empty($location->longitude)) {
                $longitude = $location->longitude;
                $latitude = $location->latitude;
                $address = $location->address;
            }
            else {
                $address = '';
                $longitude = null;
                $latitude = null;
            }

			// $q_comment = "Select media_id,metadata from media where media_id=(SELECT audio_id FROM comment WHERE comment.media_id='$media_id' and comment.type='audio' ORDER BY create_time DESC LIMIT 1)";
			// $result_audio = mysql_query($q_comment);
			// if (!$result_audio) {
			// $status = "Failure";
			// $message.= mysql_error();
			// } else if ($row1 = mysql_fetch_assoc($result_audio)) {
			// $qaudiotext = "SELECT text FROM comment WHERE comment.media_id='$media_id' and comment.type='audio' ORDER BY create_time DESC LIMIT 1";
			// $result_audiotext = mysql_query($qaudiotext);
			// if ($row = mysql_fetch_assoc($result_audiotext))
			// $audio_text = $row['text'];
			// $json_array = json_decode($row1['metadata'], true);
			// $path = $json_array['S3_files']['path'];
			// }
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";

		$xml_output .= "<viewmediadetailresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "<totle_like_on_media>$totale_like</totle_like_on_media>";
		$xml_output .= "<totle_comment_on_media>$totale_comment</totle_comment_on_media>";
		$xml_output .= "<last_comment>$last_comment</last_comment>";
		$xml_output .= (! empty ( $path )) ? "<audio_url>" . MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $path . "</audio_url>" : "<audio_url></audio_url>";
		$xml_output .= "<last_audiotext_comment>$audio_text</last_audiotext_comment>";
        $xml_output .= "<location><address>{$address}</address><longitude>{$longitude}</longitude><latitude>{$latitude}</latitude></location>";
		$xml_output .= "</viewmediadetailresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
