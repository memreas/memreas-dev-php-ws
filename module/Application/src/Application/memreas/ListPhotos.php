<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class ListPhotos {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
		
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$error_flag = 0;
		$message = '';
		$data = simplexml_load_string ( $_POST ['xml'] );
		$userid = trim ( $data->listphotos->userid );
		$device_id = trim ( $data->listphotos->device_id );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<listphotosresponse>";
		if (isset ( $userid ) && ! empty ( $userid )) {
			
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'm.media_id', 'm.metadata' );
			$qb->from ( 'Application\Entity\EventMedia', 'em' );
			$qb->join ( 'Application\Entity\Event', 'e', 'WITH', 'em.event_id = e.event_id' );
			$qb->join ( 'Application\Entity\Media', 'm', 'WITH', 'm.media_id = em.media_id' );
			$qb->where ( 'e.user_id = ?1 and m.user_id!=?1' );
			$qb->orderBy ( 'm.create_date', 'DESC' );
			$qb->setParameter ( 1, $userid );
			$result1 = $qb->getQuery ()->getResult ();
			$query_user_media = "SELECT m FROM Application\Entity\Media m where m.user_id ='$userid' ORDER BY m.create_date DESC";
			$statement = $this->dbAdapter->createQuery ( $query_user_media );
			$result = $statement->getResult ();
			
			if (count ( $result ) > 0 || count ( $result1 ) > 0) {
				$count = 0;
				foreach ( $result as $row ) {
					$json_array = json_decode ( $row->metadata, true );
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$count ++;
						$meta [$count] ['media_id'] = $row->media_id;
						$meta [$count] ['url'] = $json_array ['S3_files'];
						$meta [$count] ['download'] = $json_array ['local_filenames'] ['device'];
					}
				}
				foreach ( $result1 as $row1 ) {
					$json_array = json_decode ( $row1 ['metadata'], true );
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$count ++;
						$meta [$count] ['media_id'] = $row1 ['media_id'];
						$meta [$count] ['url'] = $json_array ['S3_files'];
						$meta [$count] ['download'] = $json_array ['local_filenames'] ['device'];
					}
				}
				
				// echo '<pre>';
				// print_r($meta);//exit;
				$xml_output .= "<status>success</status>";
				$xml_output .= "<noofimage>$count</noofimage>";
				$xml_output .= "<images>";
				foreach ( $meta as $metadata ) {
					
					$xml_output .= "<image>";
					$xml_output .= "<media_id>" . $metadata ['media_id'] . "</media_id>";
					$xml_output .= "<name>" . $this->url_signer->signArrayOfUrls ( $metadata ['url'] ['path'] ) . "</name>";
					$download = 0;
					// print_r();exit;
					foreach ( $metadata ['download'] as $value ) {
						$str = $userid . '_' . $device_id;
						if (strcasecmp ( $value, $str ) == 0)
							$download = 1;
					}
					$xml_output .= "<is_download>" . $download . "</is_download>";
					$xml_output .= "</image>";
				}
				$xml_output .= "</images>";
			}
			// -----------------for users event
			if (count ( $result1 ) == 0 && count ( $result ) == 0) {
				$error_flag = 1;
				$message = "add some photos or videos...";
			}
			if ($error_flag) {
				$xml_output .= "<status>success</status>";
				$xml_output .= "<noofimage>0</noofimage>";
				$xml_output .= "<message>$message</message>";
				$xml_output .= "<images>";
				$xml_output .= "<image><media_id></media_id>";
				$xml_output .= "<name></name>";
				$xml_output .= "</image>";
				$xml_output .= "</images>";
			}
		} else {
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<noofimage>0</noofimage>";
			$xml_output .= "<message>User id is not given.</message>";
			$xml_output .= "<images>";
			$xml_output .= "<image><media_id></media_id>";
			$xml_output .= "<name></name>";
			$xml_output .= "</image>";
			$xml_output .= "</images>";
		}
		
		$xml_output .= "</listphotosresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
