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
use Application\memreas\Utility;

class ListComments {
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
		
		// $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
	}
	
	/*
	 *
	 */
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$event_id = trim ( $data->listcomments->event_id );
		$media_id = 0;
		if (! empty ( $data->listcomments->media_id )) {
			$media_id = trim ( $data->listcomments->media_id );
		}
		
		$page = trim ( $data->listcomments->page );
		if (empty ( $page )) {
			$page = 1;
		}
		
		$limit = trim ( $data->listcomments->limit );
		if (empty ( $limit )) {
			$limit = 10;
		}
		
		$from = ($page - 1) * $limit;
		
		// $q_comment = "SELECT COUNT(c.type) as totale_comment FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and (c.type='text' or c.type='audio')";
		
		$qb = $this->dbAdapter->createQueryBuilder ();
		$qb->select ( 'c.type,c.audio_id,c.text,u.username, u.user_id,c.media_id,c.event_id, c.create_time' );
		$qb->from ( 'Application\Entity\Comment', 'c' );
		$qb->join ( 'Application\Entity\User', 'u', 'WITH', 'c.user_id = u.user_id' );
		if (! empty ( $event_id )) {
			// $qb->where ( "c.event_id=?1 AND (c.type='text' or c.type='audio' or c.type='like')" );
			$qb->where ( "c.event_id=?1" );
			$qb->setParameter ( 1, $event_id );
		}
		
		if (! empty ( $media_id )) {
			$qb->andWhere ( "c.media_id=?2" );
			$qb->setParameter ( 2, $media_id );
		}
		
		$qb->orderBy ( 'c.create_time', 'DESC' );
		
		$qb->setMaxResults ( $limit );
		$qb->setFirstResult ( $from );
		// error_log("dql ---> ".$qb->getQuery()->getSql().PHP_EOL);
		$result_comment = $qb->getQuery ()->getResult ();
		
		$output .= '<comments>';
		
		if (count ( $result_comment ) <= 0) {
			$status = "Success";
			$message = "No TEXT Comment For this Event";
		} else {
			foreach ( $result_comment as $value ) {
				$output .= '<comment>';
				$output .= "<comment_event_id>" . $value ['event_id'] . "</comment_event_id>";
				$output .= "<comment_media_id>" . $value ['media_id'] . "</comment_media_id>";
				$output .= "<comment_text>" . $value ['text'] . "</comment_text>";
				$output .= "<type>" . $value ['type'] . "</type>";
				$audio_url = '';
				if ($value ['type'] == 'audio') {
					$audio_row = $this->dbAdapter->find ( 'Application\Entity\Media', $value ['audio_id'] );
					// $audio_row = $this->dbAdapter->find ( 'Application\Entity\Media', $value ['media_id'] );
					// $json_array = json_decode ( $audio_row ['metadata'], true );
					// error_log("metadata-----> ".print_r($audio_row,true).PHP_EOL);
					
					if ($audio_row) {
						$json_array = json_decode ( $audio_row->metadata, true );
						if (isset ( $json_array ['S3_files'] ['type'] ['audio'] )) {
							if (isset ( $json_array ['S3_files'] ['audio'] ) || array_key_exists ( 'audio', $json_array ['S3_files'] ))
								$audio_url = $json_array ['S3_files'] ['audio'];
							else
								$audio_url = $json_array ['S3_files'] ['path'];
							$output .= "<audio_media_url><![CDATA[" . $this->url_signer->signArrayOfUrls ( $audio_url ) . "]]></audio_media_url>";
						}
					} else {
						$output .= "<audio_media_url></audio_media_url>";
					}
				}
				
				$output .= "<username>" . $value ['username'] . "</username>";
				$media_row = $this->dbAdapter->createQueryBuilder ()->select ( 'm' )->from ( 'Application\Entity\Media', 'm' )->where ( "m.user_id = '{$value['user_id']}' AND m.is_profile_pic = 1" )->getQuery ()->getResult ();
				if ($media_row) {
					$json_array_profile = json_decode ( $media_row [0]->metadata, true );
				}
				
				$url1 = MemreasConstants::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
				if (! empty ( $json_array_profile ['S3_files'] ['thumbnails'] ['79x80'] [0] ))
					$url1 = $json_array_profile ['S3_files'] ['thumbnails'] ['79x80'] [0];
				$output .= "<profile_pic><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url1 ) . "]]></profile_pic>";
				$output .= '<commented_about>' . Utility::formatDateDiff ( $value ['create_time'] ) . '</commented_about>';
				
				$output .= '</comment>';
			}
		}
		$output .= '</comments>';
		if ($frmweb) {
			return $output;
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<listcommentsresponse>";
		$xml_output .= $output;
		$xml_output .= "</listcommentsresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
