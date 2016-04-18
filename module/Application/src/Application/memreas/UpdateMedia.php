<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */

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

class UpdateMedia {
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
	
	/*
	 *
	 */
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$status = $message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$media_arr = trim ( $data->updatemedia->media->count () );
		
		try {
			if ($data->updatemedia->media->count ()) {
				foreach ( $data->updatemedia->media as $media ) {
					$media_id = trim ( $media->media_id );
					
					$address = (isset ( $media->location->address ) && ! empty ( $media->location->address )) ? trim ( $media->location->address ) : "";
					$latitude = (isset ( $media->location->latitude ) && ! empty ( $media->location->latitude )) ? trim ( $media->location->latitude ) : "";
					$longitude = (isset ( $media->location->longitude ) && ! empty ( $media->location->longitude )) ? trim ( $media->location->longitude ) : "";
					
					$query = $this->dbAdapter->createQueryBuilder ();
					$query->select ( "m" )->from ( "\Application\Entity\Media", "m" )->where ( "m.media_id = '{$media_id}'" );
					$media = $query->getQuery ()->getResult ();
					
					if (empty ( $media )) {
						$message = 'Media does not exist';
						$status = 'Failure';
					} else {
						$metadata = $media [0]->metadata;
						$metadata = json_decode ( $metadata, true );
						
						// Update media location
						unset ( $metadata ["S3_files"] ["location"] );
						$metadata ["S3_files"] ["location"] ["address"] = $address;
						$metadata ["S3_files"] ["location"] ["latitude"] = $latitude;
						$metadata ["S3_files"] ["location"] ["longitude"] = $longitude;
						$metadata = json_encode ( $metadata );
						
						Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$address', $address);
						Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$latitude', $latitude);
						Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$longitude', $longitude);
						
						$media = $media [0];
						$media->metadata = $metadata;
						$this->dbAdapter->persist ( $media );
						$this->dbAdapter->flush ();
						
						// error_log("Inside else section set media.metadata ---> ".$media->metadata.PHP_EOL);
						$message = 'Media updated';
						$status = 'Success';
						

						//
						// invalidate cache so update occurs
						//
						$redis = AWSMemreasRedisCache::getHandle();
						$redis->invalidateCache('viewmediadetails_'.$media_id);
					}
				}
				$output .= '<media_id>' . $media_id . '</media_id>';
			}
		} catch ( \Exception $e ) {
			Mlog::addone ( __CLASS__ . __METHOD__ . LINE__ . 'Caught exception::', $e->getMessage () );
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
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$xml_output::', $xml_output );
	}
}

?>
