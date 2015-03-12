<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class ListAllmedia {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $url_signer;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function exec() {
//error_log("ListAllmedia.exec xml ---> " . $_POST['xml'] . PHP_EOL);
		$data = simplexml_load_string ( $_POST ['xml'] );
		$message = ' ';
		$containt = ' ';
		$user_id = trim ( $data->listallmedia->user_id );
		if (! empty ( $_SESSION ['user'] ['user_id'] )) {
			$user_id = $_SESSION ['user'] ['user_id'];
		}
		
		$event_id = trim ( $data->listallmedia->event_id );
		$device_id = trim ( $data->listallmedia->device_id );
		$error_flag = 0;
		
		if (isset ( $data->listallmedia->page ) && ! empty ( $data->listallmedia->page )) {
			$page = $data->listallmedia->page;
		} else {
			$page = 1;
		}
		if (! isset ( $data->listallmedia->limit ) || empty ( $data->listallmedia->limit ) || $data->listallmedia->limit == 0)
			$limit = 10;
		else
			$limit = trim ( $data->listallmedia->limit );
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version='1.0' encoding='utf-8' ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<listallmediaresponse>";
		$xml_output .= "<medias>";
		
		$from = ($page - 1) * $limit;
		
		if (empty ( $event_id )) {
			$q1 = "select m from Application\Entity\Media m where m.user_id='$user_id' ORDER BY m.create_date DESC";
			$statement = $this->dbAdapter->createQuery ( $q1 );
			$statement->setMaxResults ( $limit );
			$statement->setFirstResult ( $from );
			$result = $statement->getArrayResult ();
		} else {
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'media.user_id', 'media.media_id', 'media.metadata', 'media.create_date' );
			$qb->from ( 'Application\Entity\Media', 'media' );
			$qb->join ( 'Application\Entity\EventMedia', 'em', 'WITH', 'media.media_id = em.media_id' );
			$qb->join ( 'Application\Entity\Event', 'event', 'WITH', 'em.event_id = event.event_id' );
			$qb->where ( 'event.event_id = ?1' );
			$qb->orderBy ( 'media.create_date', 'DESC' );
			$qb->setParameter ( 1, $event_id );
			$qb->setMaxResults ( $limit );
			$qb->setFirstResult ( $from );
			
			$result = $qb->getQuery ()->getArrayResult ();
		}
		
		if (count ( $result ) <= 0) {
			$error_flag = 2;
			$message = "No Record found for this Event";
		} else {
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u.username', 'm.metadata' );
			$qb->from ( 'Application\Entity\User', 'u' );
			
			// Changed by JMeah 31-AUG-2014 - not all users will have profile pics...
			// $qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
			$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id' );
			
			$qb->where ( 'u.user_id=?1 AND m.is_profile_pic = 1 ' );

			$qb->setParameter ( 1, $result [0] ['user_id'] );
			$oUserProfile = $qb->getQuery ()->getResult ();
			$eventRepo = $this->dbAdapter->getRepository('Application\Entity\Event');
			$path = $eventRepo->getProfileUrl($oUserProfile[0]['metadata']);
			// If profie pic set it
			$xml_output .= "<profile_pic><![CDATA[" . $path . "]]></profile_pic>";
				
 			$url1 = '';
			$xml_output .= "<username>" . $oUserProfile [0] ['username'] . "</username>";
			$xml_output .= "<page>$page</page>";
			$xml_output .= "<status>Success</status>";
			$xml_output .= "<user_id>" . $result [0] ['user_id'] . "</user_id>";
			$xml_output .= "<event_id>" . $event_id . "</event_id>";
			$xml_output .= "<message>Media List</message>";
				
			$checkHasMedia = false;
			foreach ( $result as $row ) {
				$json_array = json_decode ( $row ['metadata'], true );
				
				// Ignore user profile media
				if (! isset ( $row ['is_profile_pic'] ) || ! $row ['is_profile_pic']) {
					$checkHasMedia = true;
					$url79x80 = '';
					$url98x78 = '';
					$url448x306 = '';
					$url1280x720 = '';
					$thum_url = '';
					$download_url = '';
					$is_download = 0;
					
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$type = "image";
						$url79x80 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : "";
						$url98x78 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] )) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : "";
						$url448x306 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] )) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : "";
						$url1280x720 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['1280x720'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['1280x720'] )) ? $json_array ['S3_files'] ['thumbnails'] ['1280x720'] : "";
					} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
						$type = "video";
						$thum_url = (isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] )) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : "";
						$url79x80 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : "";
						$url98x78 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] )) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : "";
						$url448x306 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] )) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : "";
						$url1280x720 = (isset ( $json_array ['S3_files'] ['thumbnails'] ['1280x720'] ) && ! empty ( $json_array ['S3_files'] ['thumbnails'] ['1280x720'] )) ? $json_array ['S3_files'] ['thumbnails'] ['1280x720'] : "";
					} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] )) {
						$type = "audio";
						continue;
					} else
						$type = "Type not Mentioned";
					$url = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : $json_array ['S3_files'] ['path'];
					$media_name = basename ( $json_array ['S3_files'] ['path'] );
					//Prefix added for matching and sync...
					$media_name_prefix = pathinfo( $media_name )[ 'filename' ];
					if (isset ( $json_array ['local_filenames'] ['device'] )) {
						$device = ( array ) $json_array ['local_filenames'] ['device'];
					} else {
						$device = array ();
					}
					if (in_array ( $user_id . '_' . $device_id, $device )) {
						$is_download = 1;
					}
					
					// output xml
					$xml_output .= "<media>";
					$xml_output .= "<media_id>" . $row ['media_id'] . "</media_id>";

					$format = 'Y-m-d H:i:s';
					$date = \DateTime::createFromFormat($format, $row ['create_date']);
					$xml_output .= "<media_date>" . $date->getTimestamp() . "</media_date>";
						
					//main
					$xml_output .= "<main_media_url><![CDATA[" . $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['path'] ) . "]]></main_media_url>";
					
					//web
					$path = isset ( $json_array ['S3_files'] ['web'] ) ? $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['web'] ) : '';
					$xml_output .= isset ( $json_array ['S3_files'] ['web'] ) ? "<media_url_web><![CDATA[" . $path . "]]></media_url_web>" : '';
					
					//1080p
					$path = isset ( $json_array ['S3_files'] ['1080p'] ) ? $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['1080p'] ) : '';
					$xml_output .= isset ( $json_array ['S3_files'] ['1080p'] ) ? "<media_url_1080p><![CDATA[" . $path . "]]></media_url_1080p>" : '';

					//download
					$path = isset ( $json_array ['S3_files'] ['download'] ) ? $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['download'] ) : '';
					$xml_output .= isset ( $json_array ['S3_files'] ['download'] ) ? "<media_url_download><![CDATA[" . $path . "]]></media_url_download>" : '';
					
					if ($type == "video") {
						/*
						 * $path = isset($json_array ['S3_files'] ['web']) && !empty($json_array ['S3_files'] ['web']) ? $json_array ['S3_files'] ['web'] : ""; $path = $this->url_signer->signArrayOfUrls($path); $xml_output .= isset($json_array ['S3_files'] ['web']) ? "<media_url_web><![CDATA[" . $path . "]]></media_url_web>" : '';
						 */
						if (isset ( $json_array ['S3_files'] ['1080p'] ) && ! empty ( $json_array ['S3_files'] ['1080p'] )) {
							//progressive download
							$path = $json_array ['S3_files'] ['1080p'] ;
							$path = $this->url_signer->signArrayOfUrls ( $path );
							$xml_output .= "<media_url_1080p><![CDATA[" . $path . "]]></media_url_1080p>";

							//rtmp
							$path = $this->url_signer->signArrayOfUrls ( $path );
							$xml_output .= isset ( $json_array ['S3_files'] ['1080p'] ) ? "<media_url_1080p_rtmp><![CDATA[" . $path . "]]></media_url_1080p_rtmp>" : '';
						}
						
						if (isset ( $json_array ['S3_files'] ['hls'] ) && ! empty ( $json_array ['S3_files'] ['hls'] )) {
							$path = $this->url_signer->signArrayOfUrls ( $path );
							$xml_output .= "<media_url_hls><![CDATA[" . $path . "]]></media_url_hls>";
						}
					}
					
					$xml_output .= "<is_downloaded>$is_download</is_downloaded>";
					if (isset ( $data->listallmedia->metadata )) {
						$xml_output .= "<metadata><![CDATA[" . $row ['metadata'] . "]]></metadata>";
					}
					
					$xml_output .= "<event_media_video_thum>";
					$path = $this->url_signer->signArrayOfUrls ( $thum_url );
					$xml_output .= (! empty ( $thum_url )) ? $path : '';
					$xml_output .= "</event_media_video_thum>";
					
					/*
					 * 5-SEP-2014 JM Change to allow for multiple thumbnails in response sends back simple json encoded array
					 */
					$xml_output .= "<media_url_79x80><![CDATA[";
					$xml_output .= $this->url_signer->signArrayOfUrls ( $url79x80 );
					$xml_output .= "]]></media_url_79x80>";
					
					$xml_output .= "<media_url_98x78><![CDATA[";
					$xml_output .= $this->url_signer->signArrayOfUrls ( $url98x78 );
					$xml_output .= "]]></media_url_98x78>";
					
					$xml_output .= "<media_url_448x306><![CDATA[";
					$xml_output .= $this->url_signer->signArrayOfUrls ( $url448x306 );
					$xml_output .= "]]></media_url_448x306>";
					
					$xml_output .= "<media_url_1280x720><![CDATA[";
					$xml_output .= $this->url_signer->signArrayOfUrls ( $url1280x720 );
					$xml_output .= "]]></media_url_1280x720>";
					
					$xml_output .= "<type>$type</type>";
					$xml_output .= "<media_name><![CDATA[" . $media_name . "]]></media_name>";
					$xml_output .= "<media_name_prefix><![CDATA[" . $media_name_prefix . "]]></media_name_prefix>";
					$xml_output .= "</media>";
				}
			}
			
			// If has profile image but has no more media
			if (! $checkHasMedia) {
				$xml_output = str_replace ( array (
						'<status>Success</status>',
						'<message>Media List</message>' 
				), array (
						'<status>Failure</status>',
						'<message>No Record found for this Event</message>' 
				), $xml_output );
			}
		}
		
		if ($error_flag) {
			$xml_output .= "<status>Failure</status>";
			$xml_output .= "<user_id></user_id>";
			$xml_output .= "<event_id>" . $event_id . "</event_id>";
			$xml_output .= "<message>$message</message>";
			/*
			 * $xml_output .= "<media>"; $xml_output .= "<media_id></media_id>"; $xml_output .= "<metadata></metadata>"; $xml_output .= "<is_downloaded></is_downloaded>"; $xml_output .= "<event_media_video_thum></event_media_video_thum>"; $xml_output .= "<media_url_79x80></media_url_79x80>"; $xml_output .= "<media_url_98x78></media_url_98x78>"; $xml_output .= "<media_url_448x306></media_url_448x306>"; $xml_output .= "<type></type>"; $xml_output .= "<media_name></media_name>"; $xml_output .= "</media>";
			 */
		}
		
		$xml_output .= "</medias>";
		$xml_output .= "</listallmediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
//error_log("ListAllmedia.exec xml_output ---> " . $xml_output . PHP_EOL);
	}
}

?>
