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
use Application\Entity\Notification;
use Application\memreas\Utility;

class ListNotification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $xml_output;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	
	/*
	 * Sample Notification xml
	 * <!-- Request -->
	 * <xml>
	 * <updatenotification>
	 * <notification>
	 * <notification_id>5f173f40-2d87-11e3-b8a8-27e1f11594a6</notification_id>
	 * <!-- status 0 - accept, 1 - decline, 2 - ignore, 3 - rejected(not used) -->
	 * <status>2</status>
	 * <message> optional </message>
	 * </notification>
	 * </updatenotification>
	 * </xml>
	 *
	 * <!-- Response -->
	 * <xml>
	 * <listnotificationresponse>
	 * <status>success</status>
	 * <notifications>
	 * <notification>
	 * <notification_id>e5285106-47be-11e3-85d4-22000a8a1935</notification_id>
	 * <meta>vinod Has commented on yuu event</meta>
	 * <notification_type>3</notification_type>
	 * </notification>
	 * <notification>
	 * <notification_id>ae0232fa-47b9-11e3-85d4-22000a8a1935</notification_id>
	 * <meta>vinod want to add you to yuu event</meta>
	 * <notification_type>2</notification_type>
	 * </notification>
	 * </notifications>
	 * </listnotificationresponse>
	 * </xml>
	 */
	public function exec() {
		try {
			$oClass = new \ReflectionClass ( 'Application\Entity\Notification' );
			$array = $oClass->getConstants ();
			unset ( $array ['EMAIL'], $array ['MEMREAS'], $array ['NONMEMREAS'] );
			$array = array_flip ( $array );
			
			Mlog::addone ( basename ( __FILE__ ) . 'inbound xml', $_POST ['xml'] );
			$error_flag = 0;
			$message = '';
			$data = simplexml_load_string ( $_POST ['xml'] );
			$receiver_uid = trim ( $data->listnotification->receiver_uid );
			header ( "Content-type: text/xml" );
			$this->xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$this->xml_output .= "<xml>";
			$this->xml_output .= "<listnotificationresponse>";
			if (! empty ( $receiver_uid )) {
				
				/**
				 * Fetch list of notifications
				 */
				$query_user_notification = "
					SELECT m 
					FROM Application\Entity\Notification m   
					where m.receiver_uid ='$receiver_uid' 
					AND m.is_read = '0' 
					ORDER BY m.update_time DESC";
				$statement = $this->dbAdapter->createQuery ( $query_user_notification );
				$result = $statement->getArrayResult ();
				
				$this->xml_output .= "<notifications>";
				if (count ( $result ) > 0) {
					
					$count = 0;
					$this->xml_output .= "<status>success</status>";
					$eventRepository = $this->dbAdapter->getRepository ( 'Application\Entity\Event' );
					foreach ( $result as $row ) {
						
						$this->xml_output .= "<notification>";
						$meta = json_decode ( $row ['meta'], true );
						$from_user_id = $meta ['sent'] ['sender_user_id'];
						
						/**
						 * Fetch Profile Pics
						 */
						$this->fetchPics ( $from_user_id );
						
						/**
						 * Fetch event id
						 */
						if (isset ( $meta ['event_id'] ))
							$this->xml_output .= "<event_id>{$meta ['sent']['event_id']}</event_id>";
						else
							$this->xml_output .= "<event_id></event_id>";
						
						/**
						 * Fetch Notification data
						 */
						$this->xml_output .= "<notification_id>{$row['notification_id']}</notification_id>";
						$this->xml_output .= "<meta><![CDATA[{$row['meta']}]]></meta>";
						$this->xml_output .= "<notification_type>{$row['notification_type']}</notification_type>";
						if (($row ['notification_type'] == '1') || ($row ['notification_type'] == \Application\Entity\Notification::ADD_FRIEND) || ($row ['notification_type'] == '2') || ($row ['notification_type'] == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT)) {
							$this->xml_output .= "<message>" . $meta ['sent'] ['message'] . "</message>";
						} else if (($row ['notification_type'] == '1') || ($row ['notification_type'] == 'ADD_FRIEND_RESPONSE') || ($row ['notification_type'] == '7') || ($row ['notification_type'] == \Application\Entity\Notification::ADD_FRIEND_TO_EVENT_RESPONSE)) {
							$this->xml_output .= "<message>" . $meta ['received'] ['message'] . "</message>";
						}
						$this->xml_output .= "<notification_status>{$row['status']}</notification_status>";
						$this->xml_output .= "<notification_updated>{$row['update_time']}</notification_updated>";
						$this->xml_output .= '<updated_about>' . Utility::formatDateDiff ( $row ['update_time'] ) . '</updated_about>';
						
						/**
						 * Handle Notification Types
						 */
						if ($row ['notification_type'] == Notification::ADD_FRIEND_TO_EVENT || $row ['notification_type'] == Notification::ADD_MEDIA) {
							/**
							 * Handle ADD_FRIEND_TO_EVENT
							 */
							Mlog::addone ( __FILE__, 'Handle ADD_FRIEND_TO_EVENT' );
							Mlog::add ( $meta, 'j', 1 );
							$this->handleAddFriendToEvent ( $eventRepository, $meta ['sent'] ['event_id'] );
						} else if ($row ['notification_type'] == Notification::ADD_COMMENT) {
							/**
							 * Handle ADD_COMMENT
							 */
							$commentId = isset ( $meta ['comment_id'] ) ? $meta ['comment_id'] : '0';
							$this->handleAddComment ( $commentId );
						} else {
							/**
							 * Handle empty | null
							 */
							$this->handleEmpty ();
						}
					}
					$this->xml_output .= "</notification>";
				} // end for each
				
				if (count ( $result ) == 0) {
					$this->xml_output .= "<status>failure</status>";
					$this->xml_output .= "<message>No record found</message>";
				}
			} else {
				$this->xml_output .= "<status>failure</status>";
				$this->xml_output .= "<message>No record found</message>";
			}
			
			$this->xml_output .= "</notifications></listnotificationresponse>";
			$this->xml_output .= "</xml>";
			echo $this->xml_output;
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'listnotifications error ->' . $e->getMessage ();
			$this->xml_output = "
					<xml>
						<listnotificationresponse>
							<status>" . $status . "</status>
							<message>" . $message . "</message>
						</listnotificationresponse>
					</xml>";
			echo $this->xml_output;
		}
		error_log ( '$this->xml_output--->' . $this->xml_output . PHP_EOL );
	} // end exec()
	
	/**
	 * Handle ADD_FRIEND_TO_EVENT
	 */
	public function handleAddFriendToEvent($eventRepository, $event_id) {
		$eventMedia = $eventRepository->getEventMedia ( $event_id, 1 );
		Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$eventMedia', $eventMedia );
		
		// echo'<pre>';print_r($eventMedia);
		$eventMediaUrl = '';
		if (isset ( $eventMedia [0] )) {
			$eventMediaUrl = $eventRepository->getEventMediaUrl ( $eventMedia [0] ['metadata'], 'thumb' );
			Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$eventMedia[0][metadata]', $eventMedia [0] ['metadata'] );
			$this->xml_output .= "<event_media_url><![CDATA[" . json_encode ( $eventMediaUrl ) . "]]></event_media_url>";
		}
	}
	
	/**
	 * Handle ADD_COMMENT
	 */
	public function handleAddComment($commenId) {
		$comment = $this->dbAdapter->find ( 'Application\Entity\Comment', $commenId );
		
		if (! empty ( $comment )) {
			$this->xml_output .= "<comment><![CDATA[$comment->text]]></comment>";
			$this->xml_output .= "<comment_id>$comment->comment_id</comment_id>";
			$this->xml_output .= "<comment_time>$comment->create_time</comment_time>";
			$this->xml_output .= "<media_id>$comment->media_id</media_id>";
			$mediaOBj = $this->dbAdapter->find ( 'Application\Entity\Media', $comment->media_id );
			
			// $eventRepository->getEventMediaUrl($eventMedia[0]['metadata'], 'thumb');
			$url = MemreasConstants::ORIGINAL_URL . '/memreas/img/pic-1.jpg';
			if ($mediaOBj) {
				$json_array = json_decode ( $mediaOBj->metadata, true );
				$url = $eventRepository->getEventMediaUrl ( $mediaOBj->metadata, 'thumb' );
			}
			$path = $this->url_signer->fetchSignedURL ( $url );
			$this->xml_output .= "<media_path><![CDATA[" . $path . "]]></media_path>";
			
			$this->xml_output .= "<media_type></media_type>";
			if ($json_array ['S3_files'] ['file_type'] == 'video') {
				$this->xml_output .= '<media_type>' . $json_array ['S3_files'] ['type'] ['video'] ['format'] . '</media_type>';
			} else if ($json_array ['S3_files'] ['file_type'] == 'audio') {
				$this->xml_output .= '<media_type>' . $json_array ['S3_files'] ['type'] ['audio'] ['format'] . '</media_type>';
			} else if ($json_array ['S3_files'] ['file_type'] == 'image') {
				$this->xml_output .= '<media_type>' . $json_array ['S3_files'] ['type'] ['image'] ['format'] . '</media_type>';
			}
		}
	}
	
	/**
	 * Handle empty | null
	 */
	public function handleEmpty() {
		$this->xml_output .= "<comment><![CDATA[]]></comment>";
		$this->xml_output .= "<comment_id></comment_id>";
		$this->xml_output .= "<comment_time></comment_time>";
		$this->xml_output .= "<media_id></media_id>";
	}
	
	/**
	 * Fetch Profile pics and sign...
	 */
	public function fetchPics($from_user_id) {
		if (! empty ( $from_user_id )) {
			
			$from_user = $this->dbAdapter->getRepository ( 'Application\Entity\User' )->findOneBy ( array (
					'user_id' => $from_user_id 
			) );
			$profile_pic = $this->dbAdapter->getRepository ( 'Application\Entity\Media' )->findOneBy ( array (
					'user_id' => $from_user_id,
					'is_profile_pic' => 1 
			) );
			if ($profile_pic)
				$json_array = json_decode ( $profile_pic->metadata, true );
			$url1 = MemreasConstants::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
			if (! empty ( $json_array ['S3_files'] ['path'] )) {
				$url1 = $json_array ['S3_files'] ['path'];
			}
			$pic_79x80 = '';
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) {
				$pic_79x80 = $json_array ['S3_files'] ['thumbnails'] ['79x80'];
			}
			$pic_448x306 = '';
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] )) {
				$pic_448x306 = $json_array ['S3_files'] ['thumbnails'] ['448x306'];
			}
			$pic_98x78 = '';
			if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] )) {
				$pic_98x78 = $json_array ['S3_files'] ['thumbnails'] ['98x78'];
			}
			$this->xml_output .= "<profile_username>$from_user->username</profile_username>";
			$this->xml_output .= "<profile_pic><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url1 ) . "]]></profile_pic>";
			$this->xml_output .= "<profile_pic_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $pic_79x80 ) . "]]></profile_pic_79x80>";
			$this->xml_output .= "<profile_pic_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $pic_448x306 ) . "]]></profile_pic_448x306>";
			$this->xml_output .= "<profile_pic_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $pic_98x78 ) . "]]></profile_pic_98x78>";
		} else {
			// missing user_id
			$this->xml_output .= "<profile_username></profile_username>";
			$this->xml_output .= "<profile_pic><![CDATA[]]></profile_pic>";
			$this->xml_output .= "<profile_pic_79x80><![CDATA[]]></profile_pic_79x80>";
			$this->xml_output .= "<profile_pic_448x306><![CDATA[]]></profile_pic_448x306>";
			$this->xml_output .= "<profile_pic_98x78><![CDATA[]]></profile_pic_98x78>";
		}
	}
} // end class ListNotification

?>
