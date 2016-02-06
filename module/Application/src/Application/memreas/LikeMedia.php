<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use Application\memreas\MUUID;
use Application\Model\MemreasConstants;

class LikeMedia {
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
		error_log ( "LikeMedia.xml_input ---->  " . $_POST ['xml'] . PHP_EOL );
		$data = simplexml_load_string ( $_POST ['xml'] );
		$message = ' ';
		$event_id = trim ( $data->likemedia->event_id );
		$media_id = trim ( $data->likemedia->media_id );
		$user_id = trim ( $data->likemedia->user_id );
		$is_like = trim ( $data->likemedia->is_like );
		$time = time ();
		$isIdSet = 0;
		$isEventIdSet = 0;
		$isMediaIdSet = 0;
		$setEventLike = 0;
		$setMediaLike = 0;
		$likeCount = 0;
		
		try {
			/*
			 * Check event id
			 */
			if (isset ( $event_id ) && ! empty ( $event_id )) {
				$isIdSet = 1;
				$isEventIdSet = 1;
			} else {
				$event_id = "";
				$isEventIdSet = 0;
			}
			$status = 'ok';
			if (! $isIdSet) {
				$message = 'event_id and media_id are empty';
				$status = 'Failure';
			} else {
				
				if (isset ( $media_id ) && ! empty ( $media_id )) {
					error_log ( "LikeMedia.media_id ---->  " . $media_id . PHP_EOL );
					error_log ( "LikeMedia.isMediaIdSet ---->  " . $isMediaIdSet . PHP_EOL );
					$isMediaIdSet = 1;
				} else {
					$media_id = "";
					$isMediaIdSet = 0;
				}
				
				if (! isset ( $is_like ) || empty ( $is_like )) {
					$message = 'is_like is empty';
					$status = 'Failure';
				}
				
				if (! isset ( $user_id ) || empty ( $user_id )) {
					$message = 'user_id is empty';
					$status = 'Failure';
				}
				
				if ($status != 'Failure') {
					/*
					 * Determine if Event Like or Media Like
					 */
					if ($isEventIdSet && ! $isMediaIdSet) {
						error_log ( "LikeMedia.setEventLike ---->  " . $setEventLike . PHP_EOL );
						$setEventLike = 1;
						/*
						 * Check event_id for like
						 */
						$q = "select c.comment_id from Application\Entity\Comment c" . " where c.event_id='$event_id'" . " and c.type = 'like' " . " and c. user_id='$user_id'" . " and c.like=1";
						$statement = $this->dbAdapter->createQuery ( $q );
						$row = $statement->getResult ();
						if ($setEventLike && ! empty ( $row [0] )) {
							$status = 'Failure';
							$message = 'like already added...';
						}
					} else if ($isEventIdSet && $isMediaIdSet) {
						$setMediaLike = 1;
						/*
						 * Check event_id for like
						 */
						$q = "select c from Application\Entity\Comment c" . " where c.event_id='$event_id'" . " and c.media_id='$media_id'" . " and c.type = 'like' " . " and c. user_id='$user_id'";
						$statement = $this->dbAdapter->createQuery ( $q );
						// error_log("SQL--->".$statement->getSql().PHP_EOL);
						$row = $statement->getResult ();
						if ($setMediaLike && ! empty ( $row [0] )) {
							$status = 'Failure';
							$message = 'like already added...';
						}
					}
					
					/*
					 * Fetch like count
					 */
					
					if ($status == 'ok') {
						/*
						 * If ok insert...
						 */
						if ($status != 'Failure') {
							$comment_id = MUUID::fetchUUID ();
							if (empty ( $event_id )) {
								$event_id = $result_event_media [0]->event_id;
							}
							// ! empty ( $row [0] )
							$tblComment = new \Application\Entity\Comment ();
							$tblComment->comment_id = $comment_id;
							$tblComment->media_id = $media_id;
							$tblComment->user_id = $user_id;
							$tblComment->type = 'like';
							$tblComment->like = 1;
							$tblComment->event_id = $event_id;
							$tblComment->create_time = $time;
							$tblComment->update_time = $time;
							
							$this->dbAdapter->persist ( $tblComment );
							$this->dbAdapter->flush ();
							
							$status = "Success";
							$message .= "like added...";
						}
					}
				}
			} // end if
		} catch ( Exception $e ) {
			error_log ( "Caught exception: ," . $e->getMessage () . PHP_EOL );
			$status = 'Failure';
			$message = 'failed to add like...';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<likemediaresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		$xml_output .= "<message>" . $message . "</message>";
		$xml_output .= "</likemediaresponse>";
		$xml_output .= "</xml>";
		error_log ( "LikeMedia.xml_output ---->  $xml_output" . PHP_EOL );
		echo $xml_output;
	}
}

?>
