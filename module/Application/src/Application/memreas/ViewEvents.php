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
use Application\memreas\UUID;
use Application\memreas\ListComments;

class ViewEvents {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside ViewEvents :__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->comments = new ListComments ( $message_data, $memreas_tables, $service_locator );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function exec() {
		Mlog::addone ( __CLASS__ . __METHOD__ . '::inbound xml-->', $_POST ['xml'] );
		$data = simplexml_load_string ( $_POST ['xml'] );
		$user_id = trim ( $data->viewevent->user_id );
		$is_my_event = trim ( $data->viewevent->is_my_event );
		$is_friend_event = trim ( $data->viewevent->is_friend_event );
		$is_public_event = trim ( $data->viewevent->is_public_event );
		$page = trim ( $data->viewevent->page );
		$limit = trim ( $data->viewevent->limit );
		$error_flag = 0;
		$type = "";
		$pic_98x78 = '';
		$pic_448x306 = '';
		$pic_79x80 = '';
		// ------------------set default limit----------------------
		if (! isset ( $limit ) || empty ( $limit )) {
			$limit = 10;
		}
		$totlecount = 0;
		$from = ($page - 1) * $limit;
		$date = strtotime ( date ( 'd-m-Y' ) );
		header ( "Content-type: text/xml" );
		
		/*
		 * ---------------------------my events----------------------------
		 */
		if ($is_my_event) {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::my event::', __LINE__ );
			
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml><viewevents>";
			
			/**
			 * MyEvents Query
			 */
			$result_event = $this->fetchMyEvents ( $user_id );
			Mlog::addone ( __CLASS__ . __METHOD__ . '::$this->fetchMyEvents ( $user_id )::', __LINE__ );
			if ($result_event) {
				
				if (count ( $result_event ) <= 0) {
					$xml_output .= "<status>Failure</status>";
					$xml_output .= "<message>No Record Found </message>";
					$xml_output .= "<events></events>";
				} else {
					$xml_output .= "<status>Success</status>";
					$xml_output .= "<message>My Events List</message>";
					$xml_output .= "<page>$page</page>";
					$xml_output .= "<events>";
				}
				if (count ( $result_event ) > 0) {
					foreach ( $result_event as $row ) { // get media
						
						$xml_output .= "<event>";
						$xml_output .= "<event_id>" . $row->event_id . "</event_id>";
						$xml_output .= "<event_name>" . $row->name . "</event_name>";
						$xml_output .= "<event_location>" . $row->location . "</event_location>";
						$xml_output .= "<event_metadata>" . $row->metadata . "</event_metadata>";
						
						$friends_can_post = $row->friends_can_post == 0 ? 0 : 1;
						$xml_output .= "<friend_can_post>" . $friends_can_post . "</friend_can_post>";
						$friends_can_share = $row->friends_can_share == 0 ? 0 : 1;
						$xml_output .= "<friend_can_share>" . $friends_can_share . "</friend_can_share>";
						
						/**
						 * get like count
						 */
						$likeCount = $this->fetchEventLikeCount ( $row->event_id );
						$xml_output .= "<like_count>" . $likeCount . "</like_count>";
						
						/**
						 * get comment count for event
						 */
						$commCount = $this->fetchEventCommentCount ( $row->event_id );
						$xml_output .= "<comment_count>" . $commCount . "</comment_count>";
						
						/**
						 * Fetch event friends...
						 */
						$friends = $this->fetchEventFriends ( $row->event_id );
						
						/**
						 * Generate event friends xml...
						 */
						$xml_output .= $this->generateEventFriendsXML ( $friends );
						
						/**
						 * get comments
						 */
						$xml_output .= $this->fetchEventComments ( $row->event_id );
						
						/**
						 * get event media
						 */
						$query_event_media_result = $this->fetchMyEventsMedia ( $user_id, $row->event_id );
						
						/**
						 * generateMyEventMediaXML
						 */
						$xml_output .= $this->generateMyEventMediaXML ( $query_event_media_result );
						
						$xml_output .= "</event>";
					} // end for loop my events
					$xml_output .= "</events>";
				}
			} else {
				$xml_output .= "<status>Failure</status>";
				$xml_output .= "<message>No Record Found </message>";
				$xml_output .= "<events></events>";
			} // end if else $result_event
		} // end if ($is_my_event)
		
		/*
		 * ------------------------for friends event-------------------------
		 */
		if ($is_friend_event) {
			Mlog::addone ( __CLASS__ . __METHOD__ . '::friends event::', __LINE__ );
			
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml><viewevents>";
			// get friend ids
			
			/**
			 * FriendsEvents Query
			 */
			$result_friendevent = $this->fetchFriendsEvents ( $user_id );
			Mlog::addone ( __CLASS__ . __METHOD__ . '::$result_friendevent::', $result_friendevent );
			if (empty ( $result_friendevent )) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->fetchFriendsEvents ()::', "fail - no records found..." );
				$xml_output .= "<status>Success</status>";
				$xml_output .= "<message>My Friends Events List</message>";
				$xml_output .= "<page>0</page>";
				$xml_output .= "<friends/>";
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . '::$result_friendevent = $this->fetchFriendsEvents ( $user_id )::', __LINE__ );
				$xml_output .= "<status>Success</status>";
				$xml_output .= "<message>My Friends Events List</message>";
				$xml_output .= "<page>$page</page>";
				$xml_output .= "<friends>";
				$array = array ();
				foreach ( $result_friendevent as $er ) {
					$array [$er ['user_id']] [] = $er;
				}
				foreach ( $array as $k => $row_events ) {
					// get friend's event
					$qb = $this->dbAdapter->createQueryBuilder ()->select ( 'm' )->from ( 'Application\Entity\Media', 'm' )->where ( "m.user_id = '{$k}' AND m.is_profile_pic = 1" );
					$profile = $qb->getQuery ()->getResult ();
					
					if (! empty ( $profile )) {
						$json_array = json_decode ( $profile [0]->metadata, true );
						if (! empty ( $json_array ['S3_files'] ['path'] )) {
							$url1 = $json_array ['S3_files'] ['path'];
							$url1 = $this->url_signer->signArrayOfUrls ( $url1 );
						}
						if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] )) {
							$pic_79x80 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] );
						}
						if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] )) {
							$pic_448x306 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] );
						}
						if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ))
							$pic_98x78 = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] );
					} else {
						$url1 = $this->url_signer->signArrayOfUrls (null);
						$pic_79x80 = '';
						$pic_448x306 = '';
						$pic_98x78 = '';
					}
					$xml_output .= "<friend>";
					// echo '<pre>';print_r($row_getfriendid);exit;
					$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $k );
					$xml_output .= "<event_creator>" . $userOBj->username . "</event_creator>";
					$xml_output .= "<event_creator_user_id>" . $userOBj->user_id . "</event_creator_user_id>";
					
					$xml_output .= "<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";
					$xml_output .= "<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
					$xml_output .= "<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
					$xml_output .= "<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";
					
					$xml_output .= "<event_creator_user_id>" . $k . "</event_creator_user_id>";
					$xml_output .= "<events>";
					foreach ( $row_events as $key => $row_friendsevent ) {
						$url = '';
						
						$xml_output .= "<event>";
						$xml_output .= "<event_id>" . $row_friendsevent ['event_id'] . "</event_id>";
						$xml_output .= "<event_name>" . $row_friendsevent ['name'] . "</event_name>";
						$xml_output .= "<event_metadata>" . $row_friendsevent ['metadata'] . "</event_metadata>";
						$xml_output .= "<friend_can_post>" . $row_friendsevent ['friends_can_post'] . "</friend_can_post>";
						$xml_output .= "<friend_can_share>" . $row_friendsevent ['friends_can_share'] . "</friend_can_share>";
						
						/*
						 * Skip if not within viewable from / to
						 */
						$viewable_from = $row_friendsevent ['viewable_from'];
						$viewable_to = $row_friendsevent ['viewable_to'];
						if ((isset ( $viewable_from ) && ! empty ( $viewable_from )) && (isset ( $viewable_to ) && ! empty ( $viewable_to ))) {
							if (($viewable_from >= $date) && ($viewable_to <= $date)) {
								// date is outside of viewable from/to
								error_log ( "friend event date is outside of from / to..." . PHP_EOL );
								continue;
							}
						}
						/*
						 * Skip if not past ghost date
						 */
						$self_destruct = $row_friendsevent ['self_destruct'];
						if (isset ( $self_destruct ) && ! empty ( $self_destruct )) {
							if (($self_destruct < $date) && ($viewable_to <= $date)) {
								// date is outside of viewable from/to
								error_log ( "friend event date is outside of ghost date..." . PHP_EOL );
								continue;
							}
						}
						
						
						/**
						 * Fetch event friends...
						 */
						$friends = $this->fetchEventFriends ( $row_friendsevent ['event_id'] );
						
						/**
						 * Generate event friends xml .
						 */
						$xml_output .= $this->generateEventFriendsXML ( $friends );
						
						/**
						 * get like count
						 */
						$likeCount = $this->fetchEventLikeCount ( $row_friendsevent ['event_id'] );
						$xml_output .= "<like_count>" . $likeCount . "</like_count>";
						
						/**
						 * get comment count for event
						 */
						$commCount = $this->fetchEventCommentCount ( $row_friendsevent ['event_id'] );
						$xml_output .= "<comment_count>" . $commCount . "</comment_count>";
						
						/**
						 * get comments
						 */
						$xml_output .= $this->fetchEventComments ( $row_friendsevent ['event_id'] );
						
						/**
						 * Event Media
						 */
						$query_event_media_result = $this->fetchEventMedia ( $row_friendsevent ['event_id'] );
						
						/**
						 * generateFriendEventMediaXML
						 */
						$xml_output .= $this->generateFriendEventMediaXML ( $query_event_media_result );
						
						$xml_output .= "</event>";
					}
					$xml_output .= "</events>";
					$xml_output .= "</friend>";
				} // end for loop friend events
				$xml_output .= "</friends>";
			}
		} // end if ($is_friend_event)
		
		if ($is_friend_event) {
			if ($error_flag) {
				// echo $xml_output;
				$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
				$xml_output .= "<xml><viewevents>";
				$xml_output .= "<status>Failure</status>";
				$xml_output .= "<message>$message</message>";
				$xml_output .= "<events></events>";
				$xml_output .= "<friends></friends>";
			}
		} // end if ($is_friend_event) {if ($error_flag)}
		
		/*
		 * -----------------------------public events-----------------------------
		 */
		if ($is_public_event) {
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml><viewevents>";
			
			/*
			 * Fetch all public events filter in loop...
			 */
			$result_pub = $this->fetchPublicEvents ();
			
			if (count ( $result_pub ) == 0) {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->fetchPublicEvents ()::', "fail - no records found..." );
				$xml_output .= "<status>Success</status>";
				$xml_output .= "<message>Public Event List</message>";
				$xml_output .= "<page>0</page>";
				$xml_output .= "<events/>";
			} else {
				Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$this->fetchPublicEvents ()::', "success - records found..." . count ( $result_pub ) );
				$xml_output .= "<status>Success</status>";
				$xml_output .= "<message>Public Event List</message>";
				$xml_output .= "<page>$page</page>";
				$xml_output .= "<events>";
				
				foreach ( $result_pub as $public_event_row ) {
					
					if (! MemreasConstants::ALLOW_SELL_MEDIA_IN_PUBLIC) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::Inside if MemreasConstants::ALLOW_SELL_MEDIA_IN_PUBLIC...' );
						$event_json_array = json_decode ( $public_event_row ['metadata'], true );
						//
						// If selling is off then skip this event
						//
						if (! empty ( $event_json_array ['price'] ))
							continue;
					}
					
					/*
					 * Skip if not within viewable from / to
					 */
					$viewable_from = $public_event_row ['viewable_from'];
					$viewable_to = $public_event_row ['viewable_to'];
					if ((isset ( $viewable_from ) && ! empty ( $viewable_from )) && (isset ( $viewable_to ) && ! empty ( $viewable_to ))) {
						if (($viewable_from >= $date) && ($viewable_to <= $date)) {
							// date is outside of viewable from/to
							error_log ( "public event date is outside of from / to..." . PHP_EOL );
							continue;
						}
					}
					/*
					 * Skip if not past ghost date
					 */
					$self_destruct = $public_event_row ['self_destruct'];
					if (isset ( $self_destruct ) && ! empty ( $self_destruct )) {
						if (($self_destruct < $date) && ($viewable_to <= $date)) {
							// date is outside of viewable from/to
							error_log ( "public event date is outside of ghost date..." . PHP_EOL );
							continue;
						}
					}
					/*
					 * Add event entry data...
					 */
					$xml_output .= "<event>";
					$xml_output .= "<event_id>" . $public_event_row ['event_id'] . "</event_id>";
					$xml_output .= "<event_name>" . $public_event_row ['name'] . "</event_name>";
					$xml_output .= "<event_location>" . $public_event_row ['location'] . "</event_location>";
					$xml_output .= "<event_date>" . $public_event_row ['date'] . "</event_date>";
					$xml_output .= "<event_metadata>" . $public_event_row ['metadata'] . "</event_metadata>";
					$xml_output .= "<event_viewable_from>" . $public_event_row ['viewable_from'] . "</event_viewable_from>";
					$xml_output .= "<event_viewable_to>" . $public_event_row ['viewable_to'] . "</event_viewable_to>";
					$xml_output .= "<event_creator>" . $public_event_row ['username'] . "</event_creator>";
					$xml_output .= "<event_creator_user_id>" . $public_event_row ['user_id'] . "</event_creator_user_id>";
					
					/*
					 * Fetch Owner Profile photo...
					 */
					$profile = $this->fetchOwnerProfilePic ( $public_event_row ['user_id'] );
					
					if ($profile) {
						$profile_image = json_decode ( $profile [0] ['metadata'], true );
						if (! empty ( $profile_image ['S3_files'] ['path'] ))
							$pic = $this->url_signer->signArrayOfUrls ( $profile_image ['S3_files'] ['path'] );
						
						if (! empty ( $profile_image ['S3_files'] ['thumbnails'] ['79x80'] )) {
							$pic_79x80 = $this->url_signer->signArrayOfUrls ( $profile_image ['S3_files'] ['thumbnails'] ['79x80'] );
							
							if (! empty ( $profile_image ['S3_files'] ['thumbnails'] ['448x306'] )) {
								$pic_448x306 = $this->url_signer->signArrayOfUrls ( $profile_image ['S3_files'] ['thumbnails'] ['448x306'] );
								
								if (! empty ( $profile_image ['S3_files'] ['thumbnails'] ['98x78'] ))
									$pic_98x78 = $this->url_signer->signArrayOfUrls ( $profile_image ['S3_files'] ['thumbnails'] ['98x78'] );
									
									/*
								 * Set xml output for profile photo data...
								 */
								$xml_output .= "<profile_pic><![CDATA[" . $pic . "]]></profile_pic>";
								$xml_output .= "<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
								$xml_output .= "<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
								$xml_output .= "<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";
							} else {
								$pic = MemreasConstants::ORIGINAL_URL . 'memreas/img/profile-pic.jpg';
								$xml_output .= "<profile_pic><![CDATA[" . $pic . "]]></profile_pic>";
								$xml_output .= "<profile_pic_79x80 />";
								$xml_output .= "<profile_pic_448x306 />";
								$xml_output .= "<profile_pic_98x78 />";
							}
						} // if (! empty ( $profile_image ['S3_files'] ['thumbnails'] ['79x80'] ))
					} // end if ($profile)
					
					/**
					 * Fetch event friends...
					 */
					$friends = $this->fetchEventFriends ( $public_event_row ['event_id'] );
					
					/**
					 * Generate event friends xml .
					 */
					$xml_output .= $this->generateEventFriendsXML ( $friends );
					
					/**
					 * Fetch comment like and count totals...
					 */
					// get like count
					$likeCount = $this->fetchEventLikeCount ( $public_event_row ['event_id'] );
					$xml_output .= "<event_like_total>" . $likeCount . "</event_like_total>";
					// get comment count for event
					$commCount = $this->fetchEventCommentCount ( $public_event_row ['event_id'] );
					$xml_output .= "<event_comment_total>" . $commCount . "</event_comment_total>";
					
					/**
					 * get comments
					 */
					$xml_output .= $this->fetchEventComments ( $public_event_row ['event_id'] );
					
					/*
					 * Fetch event photo thumbs...
					 */
					$result_event_media_public = $this->fetchEventMedia ( $public_event_row ['event_id'] );
					
					/**
					 * generatePublicEventMediaXML
					 */
					$xml_output .= $this->generatePublicEventMediaXML ( $result_event_media_public );
					// }
					// }
					$xml_output .= " </event>";
				} // end for loop public events
				$xml_output .= "</events>";
			}
		} // end if ($is_public_event)
		$xml_output .= '</viewevents>';
		$xml_output .= '</xml>';
		//error_log ( "View Events.xml_output ----> $xml_output" . PHP_EOL );
		echo $xml_output;
	} // end exec()
	
	/**
	 * My Events functions...
	 */
	private function fetchMyEvents($user_id) {
		$query_event = "select e
			from Application\Entity\Event e
			where e.user_id='" . $user_id . "'
			ORDER BY e.create_time DESC";
		$statement = $this->dbAdapter->createQuery ( $query_event );
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::fetchMyEvents SQL::', $query_event );
		return $statement->getResult ();
	}
	private function fetchMyEventsMedia($user_id, $event_id) {
		// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::fetchMyEventsMedia($user_id, $event_id)::', "user_id::$user_id event_id::$event_id" );
		$qb = $this->dbAdapter->createQueryBuilder ();
		$qb->select ( 'event.event_id', 'event.name', 'media.media_id', 'media.metadata' );
		$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
		$qb->join ( 'Application\Entity\Event', 'event', 'WITH', 'event.event_id = event_media.event_id' );
		$qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
		$qb->where ( 'event.user_id = ?1 and event.event_id=?2' );
		$qb->orderBy ( 'media.create_date', 'DESC' );
		$qb->setParameter ( 1, $user_id );
		$qb->setParameter ( 2, $event_id );
		return $qb->getQuery ()->getResult ();
	}
	private function generateMyEventMediaXML($query_event_media_result) {
		$xml = '';
		if (count ( $query_event_media_result ) > 0) {
			foreach ( $query_event_media_result as $row1 ) {
				$url = "";
				$s3file_basename_prefix = "";
				$url_web = "";
				$url_hls = "";
				$type = "";
				$thum_url = '';
				$url79x80 = '';
				$url448x306 = '';
				$url98x78 = '';
				$s3file_download_path = '';
				$s3file_location = '';
				
				if (isset ( $row1 ['metadata'] )) {
					$json_array = json_decode ( $row1 ['metadata'], true );
					$url = $json_array ['S3_files'] ['path'];
					if (isset ( $json_array ['S3_files'] ['s3file_basename_prefix'] )) {
						$s3file_basename_prefix = $json_array ['S3_files'] ['s3file_basename_prefix'];
					}
					if (isset ( $json_array ['S3_files'] ['location'] )) {
						$s3file_location = $json_array ['S3_files'] ['location'];
					}
					if (isset ( $json_array ['S3_files'] ['download'] )) {
						$s3file_download_path = $json_array ['S3_files'] ['download'];
					}
					
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$type = "image";
						$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : '';
						$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : '';
						$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : '';
					} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
						$type = "video";
						$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
						$url_hls = isset ( $json_array ['S3_files'] ['hls'] ) ? $json_array ['S3_files'] ['hls'] : ''; // get web url
						$thum_url = isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : ''; // get video thum
						$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : ''; // get video thum
						$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : ''; // get video thum
						$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : ''; // get video thum
					} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] ))
						continue;
				} else {
					$type = "Type not Mentioned";
				} // end if (isset ( $row1 ['metadata'] ))
				
				try {
					$xml .= "<event_media>";
					$xml .= "<event_media_type>" . $type . "</event_media_type>";
					$xml .= "<event_media_id>" . $row1 ['media_id'] . "</event_media_id>";
					$xml .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
					$xml .= (! empty ( $url )) ? "<event_media_url><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url ) . "]]></event_media_url>" : '<event_media_url></event_media_url>';
					// web - video specific
					$xml .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
					// hls video specific
					$xml .= (! empty ( $url_hls )) ? "<event_media_url_hls><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_hls, MemreasConstants::CLOUDFRONT_HLSSTREAMING_HOST ) . "]]></event_media_url_hls>" : '<event_media_url_hls></event_media_url_hls>';
					$xml .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum></event_media_video_thum>";
					$xml .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
					$xml .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
					$xml .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
					// download urls
					$xml .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode ( $url ) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
					$xml .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode ( $url_web ) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
					$xml .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode ( $s3file_download_path ) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';
					$xml .= (! empty ( $s3file_location )) ? "<event_media_s3file_location><![CDATA[" . json_encode ( $s3file_location ) . "]]></event_media_s3file_location>" : '';
					$xml .= "</event_media>";
				} catch ( Exception $e ) {
					$xml .= "<event_media>";
					$xml .= "<error>" . $e->getMessage () . "</error>";
					$xml .= "</event_media>";
				}
			} // end foreach event media
		} else {
			// don't send back xml tags if empty...
		}
		return $xml;
	} // generateMyEventMediaXML($query_event_media_result)
	
	/**
	 * Friends Events functions...
	 */
	private function fetchFriendsEvents($user_id) {
		$q_friendsevent = "SELECT event.user_id,
			 event.event_id,
			 event.name,
			 event.friends_can_share,
			 event.friends_can_post,
			 event.metadata,
			 event.viewable_from,
			 event.viewable_to,
			 event.self_destruct
			 from Application\Entity\EventFriend event_friend,
			 Application\Entity\Event event
			 WHERE event.event_id=event_friend.event_id
			 AND event_friend.user_approve=1
			 AND event_friend.friend_id='" . $user_id . "'
			 ORDER BY event.create_time DESC ";
		$statement = $this->dbAdapter->createQuery ( $q_friendsevent );
		return $statement->getArrayResult ();
	}
	private function generateEventFriendsXML($friends) {
		$xml = '';
		$xml .= "<event_friends>";
		foreach ( $friends as $friend ) {
			$xml .= "<event_friend>";
			
			if (isset ( $friend ['friend_id'] )) {
				$xml .= "<event_friend_id>" . $friend ['friend_id'] . "</event_friend_id>";
			} else {
				$xml .= "<event_friend_id/>";
			}
			
			if (isset ( $friend ['social_username'] )) {
				$xml .= "<event_friend_social_username>" . $friend ['social_username'] . "</event_friend_social_username>";
			} else {
				$xml .= "<event_friend_social_username/>";
			}
			
			if (isset ( $friend ['url_image'] )) {
				$url = "<event_friend_url_image><![CDATA[" . $this->url_signer->signArrayOfUrls ( $friend ['url_image'] ) . "]]></event_friend_url_image>";
				$xml .= $url;
			} else {
				$xml .= "<event_friend_url_image/>";
			}
			$xml .= "</event_friend>";
		}
		$xml .= "</event_friends>";
		
		return $xml;
	} // end generateMyEventFriendsXML ($friends)
	private function generateFriendEventMediaXML($query_event_media_result) {
		$xml = '';
		if (count ( $query_event_media_result ) > 0) {
			foreach ( $query_event_media_result as $row ) {
				$url = '';
				$s3file_basename_prefix = "";
				$url_web = '';
				$url_hls = '';
				$type = "";
				$thum_url = '';
				$url79x80 = '';
				$url448x306 = '';
				$url98x78 = '';
				$s3file_download_path = '';
				$s3file_location = '';
				if (isset ( $row ['metadata'] )) {
					$json_array = json_decode ( $row ['metadata'], true );
					
					$url = $json_array ['S3_files'] ['path'];
					if (isset ( $json_array ['S3_files'] ['s3file_basename_prefix'] )) {
						$s3file_basename_prefix = $json_array ['S3_files'] ['s3file_basename_prefix'];
					}
					if (isset ( $json_array ['S3_files'] ['location'] )) {
						$s3file_location = $json_array ['S3_files'] ['location'];
					}
					if (isset ( $json_array ['S3_files'] ['download'] )) {
						$s3file_download_path = $json_array ['S3_files'] ['download'];
					}
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$type = "image";
						$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : '';
						$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : '';
						$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : '';
					} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
						$type = "video";
						$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
						$url_hls = isset ( $json_array ['S3_files'] ['$url_hls'] ) ? $json_array ['S3_files'] ['$url_hls'] : ''; // get web url
						$thum_url = isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : ''; // get video thum
						$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : ''; // get video thum
						$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : ''; // get video thum
						$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : ''; // get video thum
					} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] )) {
						continue;
					} else {
						$type = "Type not Mentioned";
					}
					$xml .= "<event_media>";
					$xml .= "<event_media_type>" . $type . "</event_media_type>";
					$xml .= "<event_media_id>" . $row ['media_id'] . "</event_media_id>";
					$xml .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
					$url = $this->url_signer->signArrayOfUrls ( $url );
					$xml .= (! empty ( $url )) ? "<event_media_url><![CDATA[" . $url . "]]></event_media_url>" : '<event_media_url></event_media_url>';
					// web - video specific
					$xml .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
					// hls video specific
					$xml .= (! empty ( $url_hls )) ? "<event_media_url_hls><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_hls, MemreasConstants::CLOUDFRONT_HLSSTREAMING_HOST ) . "]]></event_media_url_hls>" : '<event_media_url_hls></event_media_url_hls>';
					$xml .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum></event_media_video_thum>";
					$xml .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
					$xml .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
					$xml .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
					// download urls
					$xml .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode ( $url ) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
					$xml .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode ( $url_web ) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
					$xml .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode ( $s3file_download_path ) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';
					$xml .= (! empty ( $s3file_location )) ? "<event_media_s3file_location><![CDATA[" . json_encode ( $s3file_location ) . "]]></event_media_s3file_location>" : '';
					
					$xml .= "</event_media>";
				} // end if (isset ( $row ['metadata'] ))
			} // end for each event friend media
		} else {
			// don't send tags if empty
		}
		return $xml;
	} // end generateFriendEventMediaXML($query_event_media_result)
	
	/**
	 * Public event functions
	 */
	private function fetchPublicEvents() {
		/*
		 * - query not working..
		 * $q_public = "select event.event_id,
		 * event.user_id,
		 * event.name,
		 * event.location,
		 * event.date,
		 * event.metadata,
		 * event.viewable_from,
		 * event.viewable_to,
		 * user.username,
		 * user.profile_photo
		 * from Application\Entity\Event event, Application\Entity\User user
		 * where event.public=1
		 * and event.user_id = user.user_id
		 * and (event.viewable_to >=" . $date . " or event.viewable_to ='')
		 * and (event.viewable_from <=" . $date . " or event.viewable_from ='')
		 * and (event.self_destruct >=" . $date . " or event.self_destruct='')
		 * ORDER BY event.create_time DESC";
		 */
		
		//
		// Fetch public events without viewable or ghost
		//
		$q_public = "select  event.event_id,
			event.user_id,
			event.name,
			event.location,
			event.date,
			event.metadata,
			event.viewable_from,
			event.viewable_to,
			event.self_destruct,
			user.username,
			user.profile_photo
			from Application\Entity\Event event, Application\Entity\User user
			where event.public=1
		 	and event.user_id = user.user_id
			ORDER BY event.create_time DESC";
		
		$statement = $this->dbAdapter->createQuery ( $q_public );
		$public_events_array = $statement->getArrayResult ();
		
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$$public_events_array-->', $public_events_array, 'p' );
		return $public_events_array;
	}
	private function fetchOwnerProfilePic($user_id) {
		$q_public_profile = "select  media.media_id,
		media.is_profile_pic,
		media.metadata
		from Application\Entity\Media media
		where media.user_id=?1
		and media.is_profile_pic=1";
		$profile_query = $this->dbAdapter->createQuery ( $q_public_profile );
		$profile_query->setParameter ( 1, $user_id );
		return $profile_query->getResult ();
	}
	private function generatePublicEventMediaXML($result_event_media_public) {
		$xml = '';
		if (count ( $result_event_media_public ) > 0) {
			foreach ( $result_event_media_public as $event_media ) {
				
				$only_audio_in_event = 0;
				$url = '';
				$s3file_basename_prefix = "";
				$url_web = '';
				$url_hls = '';
				$type = "";
				$thum_url = '';
				$url79x80 = '';
				$url448x306 = '';
				$url98x78 = '';
				$media_inappropriate = '';
				$s3file_download_path = '';
				$s3file_location = '';
				if (isset ( $event_media ['metadata'] )) {
					$json_array = json_decode ( $event_media ['metadata'], true );
					$url = $json_array ['S3_files'] ['path'];
					if (isset ( $json_array ['S3_files'] ['s3file_basename_prefix'] )) {
						$s3file_basename_prefix = $json_array ['S3_files'] ['s3file_basename_prefix'];
					}
					if (isset ( $json_array ['S3_files'] ['media_inappropriate'] )) {
						$media_inappropriate = $json_array ['S3_files'] ['media_inappropriate'];
					}
					if (isset ( $json_array ['S3_files'] ['download'] )) {
						$s3file_download_path = $json_array ['S3_files'] ['download'];
					}
					if (isset ( $json_array ['S3_files'] ['location'] )) {
						$s3file_location = $json_array ['S3_files'] ['location'];
					}
					if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
						$type = "image";
						$url79x80 = empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['79x80'];
						$url448x306 = empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['448x306'];
						$url98x78 = empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['98x78'];
					} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
						$type = "video";
						$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
						$url_hls = isset ( $json_array ['S3_files'] ['hls'] ) ? $json_array ['S3_files'] ['hls'] : ''; // get hls url
						$thum_url = isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : ''; // get video thum
						$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : ''; // get video thum
						$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : ''; // get video thum
						$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : ''; // get video thum
					} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] )) {
						$only_audio_in_event = 1;
						continue;
					} else
						$type = "Type not Mentioned";
				}
				$only_audio_in_event = 0;
				$xml .= "<event_media>";
				$xml .= "<event_media_type>" . $type . "</event_media_type>";
				$xml .= "<event_media_id>" . $event_media ['media_id'] . "</event_media_id>";
				$xml .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
				$xml .= (! empty ( $media_inappropriate )) ? "<event_media_inappropriate><![CDATA[" . json_encode ( $media_inappropriate ) . "]]></event_media_inappropriate>" : '';
				$xml .= (! empty ( $url )) ? "<event_media_url><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url ) . "]]></event_media_url>" : "<event_media_url/>";
				// web - video specific
				$xml .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
				// hls video specific
				$xml .= (! empty ( $url_hls )) ? "<event_media_url_hls><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_hls, MemreasConstants::CLOUDFRONT_HLSSTREAMING_HOST ) . "]]></event_media_url_hls>" : '<event_media_url_hls></event_media_url_hls>';
				$xml .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum/>";
				$xml .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
				$xml .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
				$xml .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
				// dowload urls
				$xml .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode ( $url ) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
				$xml .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode ( $url_web ) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
				$xml .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode ( $s3file_download_path ) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';
				$xml .= (! empty ( $s3file_location )) ? "<event_media_s3file_location><![CDATA[" . json_encode ( $s3file_location ) . "]]></event_media_s3file_location>" : '';
				
				$xml .= "</event_media>";
			} // end for event public media
			if ($only_audio_in_event) {
				$xml .= "<event_media>";
				$xml .= "<event_media_id></event_media_id>";
				$xml .= "<event_media_name></event_media_name>";
				$xml .= "<event_media_type></event_media_type>";
				$xml .= "<event_media_url></event_media_url>";
				$xml .= "<event_media_video_thum></event_media_video_thum>";
				$xml .= "<event_media_79x80></event_media_79x80>";
				$xml .= "<event_media_98x78></event_media_98x78>";
				$xml .= "<event_media_448x306></event_media_448x306>";
				$xml .= "</event_media>";
			}
		} else {
			// don't send empty tags
		}
		return $xml;
	} // end generatePublicEventMediaXML($result_event_media_public)
	
	/**
	 * Common queries
	 */
	private function fetchEventMedia($event_id) {
		$q_event_media = "select event_media.event_id,
							media.media_id, media.metadata
							from Application\Entity\Media media,
							Application\Entity\EventMedia event_media
							where event_media.media_id = media.media_id
							and event_media.event_id = ?1
							order by media.create_date desc";
		$event_media_query = $this->dbAdapter->createQuery ( $q_event_media );
		$event_media_query->setParameter ( 1, $event_id );
		return $event_media_query->getResult ();
	}
	private function fetchEventComments($event_id) {
		$cdata = array (
				'listcomments' => array (
						'event_id' => $event_id,
						'limit' => 50,
						'page' => 1 
				) 
		);
		return $this->comments->exec ( $cdata );
	}
	private function fetchEventLikeCount($event_id) {
		$likeCountSql = $this->dbAdapter->createQuery ( 'SELECT COUNT(c.comment_id)
				FROM Application\Entity\Comment c
				Where c.event_id=?1
				AND c.like= 1' );
		$likeCountSql->setParameter ( 1, $event_id );
		// Mlog::addone ( __CLASS__ . __METHOD__ . '::$likeCountSql->getSQL()::', __LINE__.$likeCountSql->getSQL() );
		return $likeCountSql->getSingleScalarResult ();
	}
	private function fetchEventCommentCount($event_id) {
		$commCountQuery = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id)
								FROM Application\Entity\Comment c
								Where c.event_id='$event_id'
								AND (c.type= 'text' or c.type ='audio' or  c.type ='text|audio')" );
		// Mlog::addone(__CLASS__.__METHOD__.'::$commCountSql::',__LINE__.$commCountQuery->getSQL());
		return $commCountQuery->getSingleScalarResult ();
	}
	private function fetchEventFriends($event_id) {
		$q_event_friend = "select friend.friend_id,
		friend.social_username,
		friend.url_image
		from Application\Entity\Friend friend,
		Application\Entity\EventFriend event_friend
		where event_friend.friend_id = friend.friend_id
		and event_friend.user_approve=1
		and event_friend.event_id = ?1
		order by friend.create_date desc";
		$friend_query = $this->dbAdapter->createQuery ( $q_event_friend );
		$friend_query->setParameter ( 1, $event_id );
		return $friend_query->getResult ();
	}
} // end class ViewEvents
?>
