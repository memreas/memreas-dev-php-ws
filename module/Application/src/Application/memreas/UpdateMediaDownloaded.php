<?php

/*
 * Update media detail
 * @params: null
 * @Return
 * @Tran Tuan
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Media;
use \Exception;

// Sample xml
// <xml>
// <updatemediadownloaded>
// <media>
// <media_id><media_id>
// <device>
// <device_type><device_type>
// <device_id></device_id>
// <device_local_identifier></device_local_identifier>
// </device>
// </media>
// <updatemediadownloaded>
// <xml>
class UpdateMediaDownloaded {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	
	//
	// exec - purpose is to updated identification info for downloaded media
	//
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$status = $message = 'failure';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$media_arr = trim ( $data->updatemedia->media->count () );
		
		if ($data->updatemediadownloaded->media->count ()) {
			foreach ( $data->updatemediadownloaded->media as $media ) {
				$media_id = trim ( $media->media_id );
				
				$query = $this->dbAdapter->createQueryBuilder ();
				$query->select ( "m" )->from ( "\Application\Entity\Media", "m" )->where ( "m.media_id = '{$media_id}'" );
				$media = $query->getQuery ()->getResult ();
				
				if (empty ( $media )) {
					$message = 'media does not exist';
					$status = 'failure';
				} else {
					$metadata = $media [0]->metadata;
					$metadata = json_decode ( $metadata, true );
					
					// check for device
					$devices = $metadata ["S3_files"] ["device"];
					
					$media = $media [0];
					$media->metadata = $metadata;
					$this->dbAdapter->persist ( $media );
					$this->dbAdapter->flush ();
					
					$message = 'Media updated';
					$status = 'Success';
				}
			}
			$output .= '<media_id>' . $media_id . '</media_id>';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<updatemediaresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</updatemediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
