<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\AddNotification;
use Application\memreas\MUUID;
use \Exception;
use Application\memreas\Email;

class ReTransCoder {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $AddNotification;
	protected $notification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec() {
		try {
			/**
			 * sample xml
			 * '<xml>
			 * ' <sid>...</sid>
			 * ' <media_id>...</media__id>
			 * '</sml>
			 */
			$data = simplexml_load_string ( $_POST ['xml'] );
			$media_id = $data->media_id;
			if (! empty ( $media_id )) {
				$media = $this->dbAdapter->getRepository ( 'Application\Entity\Media' )->findOneBy ( array (
						'media_id' => $media_id 
				) );
				if ($media) {
					/**
					 * Gather data from db entry to form message for transcoder
					 * sample json message data
					 * $message_data = array (
					 * ' user_id' => $user_id,
					 * ' media_id' => $media_id,
					 * ' content_type' => $content_type,
					 * ' s3path' => $s3path,
					 * ' s3file_name' => $s3file_name,
					 * ' s3file_basename_prefix' => $s3file_basename_prefix,
					 * ' is_video' => $is_video,
					 * ' is_audio' => $is_audio
					 * );
					 */

					$meta = json_decode($media->metadata, true);
					$message_data = array (
						'user_id' => $media_id->user_id,
						'media_id' => $media_id,
						'content_type' => $meta['S3_files']['content_type'],
						's3path' => $meta['S3_files']['path'],
						's3file_name' => $meta['S3_files']['s3file_name'],
						's3file_basename_prefix' => $meta['S3_files']['s3file_basename_prefix'],
						'is_video' => empty($meta['S3_files']['is_video'])?0:1,
						'is_audio' => empty($meta['S3_files']['$is_audio'])?0:1 
					);
					
					/**
					 * Now send the message...
					 */
					$aws_manager = new AWSManagerSender ( $this->service_locator );
					$response = $aws_manager->snsProcessMediaPublish ( $message_data );
					
					if ($response == 1) {
						$status = 'Success';
						$message = "Media Successfully add";
					} else {
						throw new \Exception ( 'Error In snsProcessMediaPublish' );
					}
				} else {
					throw new \Exception ( "can't find media by media id" );
				}
			} else {
				throw new \Exception ( "missing media id" );
			}
		} catch ( \Exception $exc ) {
			$status = 'Failure';
			$message = $exc->getMessage ();
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<retranscoderresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "<media_id>$media_id</media_id>";
		$xml_output .= "</retranscoderresponse>";
		$xml_output .= "</xml>";
		ob_clean ();
		echo $xml_output;
		error_log ( "output::" . $xml_output . PHP_EOL );
	} // end exec()
} //end ReTransCoder
