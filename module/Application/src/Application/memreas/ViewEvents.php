<?php

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
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		error_log ( "View Events.xml_input ---->  " . $_POST ['xml'] . PHP_EOL );
		ini_set ( 'max_execution_time', 120 );
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
			
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml><viewevents>";
			
			$query_event = "select e
                from Application\Entity\Event e
                where e.user_id='" . $user_id . "'
                ORDER BY e.create_time DESC";
			$statement = $this->dbAdapter->createQuery ( $query_event );
			// $statement->setMaxResults ( $limit );
			// $statement->setFirstResult ( $from );
			$result_event = $statement->getResult ();
			
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
						// get like count
						$likeCountSql = $this->dbAdapter->createQuery ( 'SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like= 1' );
						$likeCountSql->setParameter ( 1, $row->event_id );
						$likeCount = $likeCountSql->getSingleScalarResult ();
						$xml_output .= "<like_count>" . $likeCount . "</like_count>";
						// get comment count for event
						$commCountSql = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND (c.type= 'text' or c.type ='audio')" );
						$commCountSql->setParameter ( 1, $row->event_id );
						$commCount = $commCountSql->getSingleScalarResult ();
						$xml_output .= "<comment_count>" . $commCount . "</comment_count>";
						
						/*
						 * Fetch event friends...
						 */
						$q_event_friend = "select friend.friend_id, friend.social_username, friend.url_image" . " from Application\Entity\Friend friend," . " Application\Entity\EventFriend event_friend" . " where event_friend.friend_id = friend.friend_id" . " and event_friend.user_approve=1" . " and event_friend.event_id = ?1 " . " order by friend.create_date desc";
						
						$friend_query = $this->dbAdapter->createQuery ( $q_event_friend );
						$friend_query->setParameter ( 1, $row->event_id );
						
						$friends = $friend_query->getResult ();
						
						$xml_output .= "<event_friends>";
						foreach ( $friends as $friend ) {
							$xml_output .= "<event_friend>";
							
							if (isset ( $friend ['friend_id'] )) {
								$xml_output .= "<event_friend_id>" . $friend ['friend_id'] . "</event_friend_id>";
							} else {
								$xml_output .= "<event_friend_id/>";
							}
							
							if (isset ( $friend ['social_username'] )) {
								$xml_output .= "<event_friend_social_username>" . $friend ['social_username'] . "</event_friend_social_username>";
							} else {
								$xml_output .= "<event_friend_social_username/>";
							}
							
							if (isset ( $friend ['url_image'] )) {
								$url = "<event_friend_url_image><![CDATA[" . $this->url_signer->signArrayOfUrls ( $friend ['url_image'] ) . "]]></event_friend_url_image>";
								$xml_output .= $url;
							} else {
								$xml_output .= "<event_friend_url_image/>";
							}
							$xml_output .= "</event_friend>";
						}
						$xml_output .= "</event_friends>";
						
						/*
						 * get comments
						 */
						$cdata = array (
								'listcomments' => array (
										'event_id' => $row->event_id,
										'limit' => 50,
										'page' => 1 
								) 
						);
						$xml_output .= $this->comments->exec ( $cdata );
						
						/*
						 * get media
						 */
						$qb = $this->dbAdapter->createQueryBuilder ();
						$qb->select ( 'event.event_id', 'event.name', 'media.media_id', 'media.metadata' );
						$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
						$qb->join ( 'Application\Entity\Event', 'event', 'WITH', 'event.event_id = event_media.event_id' );
						$qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
						$qb->where ( 'event.user_id = ?1 and event.event_id=?2' );
						$qb->orderBy ( 'media.create_date', 'DESC' );
						$qb->setParameter ( 1, $user_id );
						$qb->setParameter ( 2, $row->event_id );
						$query_event_media_result = $qb->getQuery ()->getResult ();
						
						if (count ( $query_event_media_result ) > 0) {
							
							foreach ( $query_event_media_result as $row1 ) {
								
								$url = "";
								$s3file_basename_prefix = "";
								$url_web = "";
								$url_1080p = "";
								$type = "";
								$thum_url = '';
								$url79x80 = '';
								$url448x306 = '';
								$url98x78 = '';
								$s3file_download_path = '';
								
								if (isset ( $row1 ['metadata'] )) {
									$json_array = json_decode ( $row1 ['metadata'], true );
									$url = $json_array ['S3_files'] ['path'];
									if (isset ( $json_array ['S3_files'] ['s3file_basename_prefix'] )) {
										$s3file_basename_prefix = $json_array ['S3_files'] ['s3file_basename_prefix'];
									}
									if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
										$type = "image";
										$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : '';
										$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : '';
										$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : '';
									} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
										$type = "video";
										$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
										$url_1080p = isset ( $json_array ['S3_files'] ['1080p'] ) ? $json_array ['S3_files'] ['1080p'] : ''; // get web url
										$thum_url = isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : ''; // get video thum
										$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : ''; // get video thum
										$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : ''; // get video thum
										$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : ''; // get video thum
									} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] ))
										continue;
								} else {
									$type = "Type not Mentioned";
								} // end if (isset ( $row1 ['metadata'] ))
								if (isset ( $json_array ['S3_files'] ['download'] )) {
									$s3file_download_path = $json_array ['S3_files'] ['download'];
								}
								
								
								try {
									$xml_output .= "<event_media>";
									$xml_output .= "<event_media_type>" . $type . "</event_media_type>";
									$xml_output .= "<event_media_id>" . $row1 ['media_id'] . "</event_media_id>";
									$xml_output .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
									$xml_output .= (! empty ( $url )) ? "<event_media_url><![CDATA[" .  $this->url_signer->signArrayOfUrls ( $url ) . "]]></event_media_url>" : '<event_media_url></event_media_url>';
									// web - video specific
									$xml_output .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
									// 1080p video specific
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_url_1080p><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_1080p ) . "]]></event_media_url_1080p>" : '<event_media_url_1080p></event_media_url_1080p>';
									$xml_output .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum></event_media_video_thum>";
									$xml_output .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
									$xml_output .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
									$xml_output .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
									//download urls
									$xml_output .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode($url) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
									$xml_output .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode($url_web) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_s3_url_1080p_path><![CDATA[" . json_encode($url_1080p) . "]]></event_media_s3_url_1080p_path>" : '<event_media_s3_url_1080p_path></event_media_s3_url_1080p_path>';
									$xml_output .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode($s3file_download_path) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';
										
									$xml_output .= "</event_media>";
								} catch ( Exception $e ) {
									$xml_output .= "<event_media>";
									$xml_output .= "<error>" . $e->getMessage () . "</error>";
									$xml_output .= "</event_media>";
								}
							}
						} else {
							// $xml_output .= "<event_media>";
							// $xml_output .= "<event_media_id></event_media_id>";
							// $xml_output .= "<event_media_name></event_media_name>";
							// $xml_output .= "<event_media_type></event_media_type>";
							// $xml_output .= "<event_media_url></event_media_url>";
							// $xml_output .= "<event_media_video_thum></event_media_video_thum>";
							// $xml_output .= "<event_media_79x80></event_media_79x80>";
							// $xml_output .= "<event_media_98x78></event_media_98x78>";
							// $xml_output .= "<event_media_448x306></event_media_448x306>";
							// $xml_output .= "</event_media>";
						}
						
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
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml><viewevents>";
			// get friend ids
			
			$q_friendsevent = "SELECT event.user_id," . " event.event_id," . " event.name," . " event.friends_can_share," . " event.friends_can_post," . " event.metadata" . " from Application\Entity\EventFriend event_friend," . " Application\Entity\Event event" . " WHERE event.event_id=event_friend.event_id" . " AND event_friend.user_approve=1" . " AND event_friend.friend_id='" . $user_id . "' ORDER BY event.create_time DESC ";
			$statement = $this->dbAdapter->createQuery ( $q_friendsevent );
			$result_friendevent = $statement->getArrayResult ();
			if (empty ( $result_friendevent )) {
				$error_flag = 2;
				$message = "No Record Found";
			} else {
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
						$url1 = MemreasConstants::ORIGINAL_URL . 'memreas/img/profile-pic.jpg';
						$pic_79x80 = '';
						$pic_448x306 = '';
						$pic_98x78 = '';
					}
					$xml_output .= "<friend>";
					// echo '<pre>';print_r($row_getfriendid);exit;
					$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $k );
					$xml_output .= "<event_creator>" . $userOBj->username . "</event_creator>";
					
					$xml_output .= "<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";
					$xml_output .= "<profile_pic_79x80><![CDATA[" . $pic_79x80 . "]]></profile_pic_79x80>";
					$xml_output .= "<profile_pic_448x306><![CDATA[" . $pic_448x306 . "]]></profile_pic_448x306>";
					$xml_output .= "<profile_pic_98x78><![CDATA[" . $pic_98x78 . "]]></profile_pic_98x78>";
					
					$xml_output .= "<event_creator_user_id>" . $k . "</event_creator_user_id>";
					$xml_output .= "<events>";
					foreach ( $row_events as $key => $row_friendsevent ) {
						// echo '<pre>';print_r($row_friendsevent);exit;
						// foreach ( $value as $row_friendsevent ) {
						// print_r($row_friendsevent);
						$url = '';
						
						$xml_output .= "<event>";
						$xml_output .= "<event_id>" . $row_friendsevent ['event_id'] . "</event_id>";
						$xml_output .= "<event_name>" . $row_friendsevent ['name'] . "</event_name>";
						$xml_output .= "<event_metadata>" . $row_friendsevent ['metadata'] . "</event_metadata>";
						$xml_output .= "<friend_can_post>" . $row_friendsevent ['friends_can_post'] . "</friend_can_post>";
						$xml_output .= "<friend_can_share>" . $row_friendsevent ['friends_can_share'] . "</friend_can_share>";
						
						/*
						 * Fetch event friends...
						 */
						$q_event_friend = "select friend.friend_id, friend.social_username, friend.url_image" . " from Application\Entity\Friend friend," . " Application\Entity\EventFriend event_friend" . " where event_friend.friend_id = friend.friend_id" . " and event_friend.user_approve=1" . " and event_friend.event_id = ?1 " . " order by friend.create_date desc";
						
						$friend_query = $this->dbAdapter->createQuery ( $q_event_friend );
						$friend_query->setParameter ( 1, $row_friendsevent ['event_id'] );
						$friends = $friend_query->getResult ();
						
						$xml_output .= "<event_friends>";
						foreach ( $friends as $friend ) {
							$xml_output .= "<event_friend>";
							
							if (isset ( $friend ['friend_id'] )) {
								$xml_output .= "<event_friend_id>" . $friend ['friend_id'] . "</event_friend_id>";
							} else {
								$xml_output .= "<event_friend_id/>";
							}
							
							if (isset ( $friend ['social_username'] )) {
								$xml_output .= "<event_friend_social_username>" . $friend ['social_username'] . "</event_friend_social_username>";
							} else {
								$xml_output .= "<event_friend_social_username/>";
							}
							
							if (isset ( $friend ['url_image'] )) {
								$url = "<event_friend_url_image><![CDATA[" . $this->url_signer->signArrayOfUrls ( $friend ['url_image'] ) . "]]></event_friend_url_image>";
								$xml_output .= $url;
							} else {
								$xml_output .= "<event_friend_url_image/>";
							}
							$xml_output .= "</event_friend>";
						}
						$xml_output .= "</event_friends>";
						
						/*
						 * Event Comments
						 */
						// get comments
						$cdata = array (
								'listcomments' => array (
										'event_id' => $row_friendsevent ['event_id'],
										'limit' => 50,
										'page' => 1 
								) 
						);
						$xml_output .= $this->comments->exec ( $cdata );
						
						/*
						 * Event Media
						 */
						$qb = $this->dbAdapter->createQueryBuilder ();
						$qb->select ( 'event_media.event_id', 'media.media_id', 'media.metadata' );
						$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
						$qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
						$qb->where ( 'event_media.event_id = ?1 ' );
						
						$qb->orderBy ( 'media.create_date', 'DESC' );
						$qb->setParameter ( 1, $row_friendsevent ['event_id'] );
						
						$query_event_media_result = $qb->getQuery ()->getResult ();
						
						if (count ( $query_event_media_result ) > 0) {
							foreach ( $query_event_media_result as $row ) {
								$url = '';
								$s3file_basename_prefix = "";
								$url_web = '';
								$url_1080p = '';
								$type = "";
								$thum_url = '';
								$url79x80 = '';
								$url448x306 = '';
								$url98x78 = '';
								$s3file_download_path = '';
								if (isset ( $row ['metadata'] )) {
									$json_array = json_decode ( $row ['metadata'], true );
									
									$url = $json_array ['S3_files'] ['path'];
									if (isset ( $json_array ['S3_files'] ['s3file_basename_prefix'] )) {
										$s3file_basename_prefix = $json_array ['S3_files'] ['s3file_basename_prefix'];
									}
									if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
										$type = "image";
										$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : '';
										$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : '';
										$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : '';
									} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
										$type = "video";
										$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
										$url_1080p = isset ( $json_array ['S3_files'] ['1080p'] ) ? $json_array ['S3_files'] ['1080p'] : ''; // get web url
										$thum_url = isset ( $json_array ['S3_files'] ['thumbnails'] ['fullsize'] ) ? $json_array ['S3_files'] ['thumbnails'] ['fullsize'] : ''; // get video thum
										$url79x80 = isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? $json_array ['S3_files'] ['thumbnails'] ['79x80'] : ''; // get video thum
										$url448x306 = isset ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? $json_array ['S3_files'] ['thumbnails'] ['448x306'] : ''; // get video thum
										$url98x78 = isset ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? $json_array ['S3_files'] ['thumbnails'] ['98x78'] : ''; // get video thum
									} else if (isset ( $json_array ['S3_files'] ['type'] ['audio'] ) && is_array ( $json_array ['S3_files'] ['type'] ['audio'] )) {
										continue;
									} else {
										$type = "Type not Mentioned";
									}
									if (isset ( $json_array ['S3_files'] ['download'] )) {
										$s3file_download_path = $json_array ['S3_files'] ['download'];
									}
									$xml_output .= "<event_media>";
									$xml_output .= "<event_media_type>" . $type . "</event_media_type>";
									$xml_output .= "<event_media_id>" . $row ['media_id'] . "</event_media_id>";
									$xml_output .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
									$url = $this->url_signer->signArrayOfUrls ( $url );
									$xml_output .= (! empty ( $url )) ? "<event_media_url><![CDATA[" . $url . "]]></event_media_url>" : '<event_media_url></event_media_url>';
									// web - video specific
									$xml_output .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
									// 1080p video specific
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_url_1080p><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_1080p ) . "]]></event_media_url_1080p>" : '<event_media_url_1080p></event_media_url_1080p>';
									$xml_output .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum></event_media_video_thum>";
									$xml_output .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
									$xml_output .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
									$xml_output .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
									//download urls
									$xml_output .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode($url) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
									$xml_output .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode($url_web) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_s3_url_1080p_path><![CDATA[" . json_encode($url_1080p) . "]]></event_media_s3_url_1080p_path>" : '<event_media_s3_url_1080p_path></event_media_s3_url_1080p_path>';
									$xml_output .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode($s3file_download_path) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';
										
									$xml_output .= "</event_media>";
								} // end if (isset ( $row ['metadata'] ))
							}
						} else {
							// $xml_output .= "<event_media>";
							// $xml_output .= "<event_media_id></event_media_id>";
							// $xml_output .= "<event_media_name></event_media_name>";
							// $xml_output .= "<event_media_type></event_media_type>";
							// $xml_output .= "<event_media_url><![CDATA[]]></event_media_url>";
							// $xml_output .= "<event_media_video_thum></event_media_video_thum>";
							// $xml_output .= "<event_media_79x80></event_media_79x80>";
							// $xml_output .= "<event_media_98x78></event_media_98x78>";
							// $xml_output .= "<event_media_448x306></event_media_448x306>";
							// $xml_output .= "</event_media>";
						}
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
			$q_public = "select  event.event_id," . "event.user_id," . " event.name," . " event.location," . " event.date," . " event.metadata," . " event.viewable_from," . " event.viewable_to," . " user.username," . " user.profile_photo" . " from Application\Entity\Event event, Application\Entity\User user" . " where event.public=1" . " and event.user_id = user.user_id" . " ORDER BY event.update_time DESC ";
			// LIMIT $from , $limit";
			$statement = $this->dbAdapter->createQuery ( $q_public );
			$result_pub = $statement->getArrayResult ();
			
			if (count ( $result_pub ) == 0) {
				error_log ( "fail - no records found..." . PHP_EOL );
				$xml_output .= "<status>Failure</status>";
				$xml_output .= "<message>No record found</message>";
			} else {
				error_log ( "succes - records found..." . count ( $result_pub ) . PHP_EOL );
				$xml_output .= "<status>Success</status>";
				$xml_output .= "<message>Public Event List</message>";
				$xml_output .= "<page>$page</page>";
				$xml_output .= "<events>";
				
				foreach ( $result_pub as $public_event_row ) {
					
					if (! MemreasConstants::ALLOW_SELL_MEDIA_IN_PUBLIC) {
						error_log ( "Inside if MemreasConstants::ALLOW_SELL_MEDIA_IN_PUBLIC..." . PHP_EOL );
						$event_json_array = json_decode ( $public_event_row ['metadata'], true );
						// skip this event hving
						if (! empty ( $event_json_array ['price'] ))
							continue;
					}
					
					/*
					 * Skip if not within viewable from / to
					 */
					// $viewable_from = $public_event_row ['viewable_from'];
					// $viewable_to = $public_event_row ['viewable_to'];
					// if ((isset ( $viewable_from ) && ! empty ( $viewable_from )) && (isset ( $viewable_to ) && ! empty ( $viewable_to ))) {
					// if (($viewable_from >= $date) && ($viewable_to <= $date)) {
					// // date is outside of viewable from/to
					// error_log ( "public event date is outside of from / to..." . PHP_EOL );
					// continue;
					// }
					// }
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
					$q_public_profile = "select  media.media_id," . " media.is_profile_pic," . " media.metadata" . " from Application\Entity\Media media" . " where media.user_id=?1" . " and media.is_profile_pic=1";
					
					$profile_query = $this->dbAdapter->createQuery ( $q_public_profile );
					$profile_query->setParameter ( 1, $public_event_row ['user_id'] );
					$profile = $profile_query->getResult ();
					
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
							
							/*
							 * Fetch event friends...
							 */
							$q_event_friend = "select friend.friend_id, friend.social_username, friend.url_image" . " from Application\Entity\Friend friend," . " Application\Entity\EventFriend event_friend" . " where event_friend.friend_id = friend.friend_id" . " and event_friend.user_approve=1" . " and event_friend.event_id = ?1 " . " order by friend.create_date desc";
							
							$friend_query = $this->dbAdapter->createQuery ( $q_event_friend );
							$friend_query->setParameter ( 1, $public_event_row ['event_id'] );
							$friends = $friend_query->getResult ();
							
							$xml_output .= "<event_friends>";
							foreach ( $friends as $friend ) {
								$xml_output .= "<event_friend>";
								
								if (isset ( $friend ['friend_id'] )) {
									$xml_output .= "<event_friend_id>" . $friend ['friend_id'] . "</event_friend_id>";
								} else {
									$xml_output .= "<event_friend_id/>";
								}
								
								if (isset ( $friend ['social_username'] )) {
									$xml_output .= "<event_friend_social_username>" . $friend ['social_username'] . "</event_friend_social_username>";
								} else {
									$xml_output .= "<event_friend_social_username/>";
								}
								
								if (isset ( $friend ['url_image'] )) {
									$url = "<event_friend_url_image><![CDATA[" . $this->url_signer->signArrayOfUrls ( $friend ['url_image'] ) . "]]></event_friend_url_image>";
									$xml_output .= $url;
								} else {
									$xml_output .= "<event_friend_url_image/>";
								}
								$xml_output .= "</event_friend>";
							}
							$xml_output .= "</event_friends>";
							
							/*
							 * Fetch comment like and count totals...
							 */
							
							// get like count
							$likeCountSql = $this->dbAdapter->createQuery ( 'SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like= 1' );
							$likeCountSql->setParameter ( 1, $public_event_row ['event_id'] );
							$likeCount = $likeCountSql->getSingleScalarResult ();
							$xml_output .= "<event_like_total>" . $likeCount . "</event_like_total>";
							// get comment count for event
							$commCountSql = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND (c.type= 'text' or c.type ='audio')" );
							$commCountSql->setParameter ( 1, $public_event_row ['event_id'] );
							$commCount = $commCountSql->getSingleScalarResult ();
							$xml_output .= "<event_comment_total>" . $commCount . "</event_comment_total>";
							
							// get comments
							$cdata = array (
									'listcomments' => array (
											'event_id' => $public_event_row ['event_id'],
											'limit' => 50,
											'page' => 1 
									) 
							);
							$xml_output .= $this->comments->exec ( $cdata );
							
							/*
							 * Fetch event photo thumbs...
							 */
							$q_event_media = "select event_media.event_id, media.media_id, media.metadata" . " from Application\Entity\Media media, Application\Entity\EventMedia event_media" . " where event_media.media_id = media.media_id" . " and event_media.event_id = ?1 " . " order by media.create_date desc";
							
							$event_media_query = $this->dbAdapter->createQuery ( $q_event_media );
							$event_media_query->setParameter ( 1, $public_event_row ['event_id'] );
							$result_event_media_public = $event_media_query->getResult ();
							
							if (count ( $result_event_media_public ) > 0) {
								foreach ( $result_event_media_public as $event_media ) {
									
									$only_audio_in_event = 0;
									$url = '';
									$s3file_basename_prefix = "";
									$url_web = '';
									$url_1080p = '';
									$type = "";
									$thum_url = '';
									$url79x80 = '';
									$url448x306 = '';
									$url98x78 = '';
									$media_inappropriate = '';
									$s3file_download_path = '';
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
										if (isset ( $json_array ['S3_files'] ['type'] ['image'] ) && is_array ( $json_array ['S3_files'] ['type'] ['image'] )) {
											$type = "image";
											$url79x80 = empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['79x80'];
											$url448x306 = empty ( $json_array ['S3_files'] ['thumbnails'] ['448x306'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['448x306'];
											$url98x78 = empty ( $json_array ['S3_files'] ['thumbnails'] ['98x78'] ) ? '' : $json_array ['S3_files'] ['thumbnails'] ['98x78'];
										} else if (isset ( $json_array ['S3_files'] ['type'] ['video'] ) && is_array ( $json_array ['S3_files'] ['type'] ['video'] )) {
											$type = "video";
											$url_web = isset ( $json_array ['S3_files'] ['web'] ) ? $json_array ['S3_files'] ['web'] : ''; // get web url
											$url_1080p = isset ( $json_array ['S3_files'] ['1080p'] ) ? $json_array ['S3_files'] ['1080p'] : ''; // get web url
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
									$xml_output .= "<event_media>";
									$xml_output .= "<event_media_type>" . $type . "</event_media_type>";
									$xml_output .= "<event_media_id>" . $event_media ['media_id'] . "</event_media_id>";
									$xml_output .= (! empty ( $s3file_basename_prefix )) ? "<event_media_name><![CDATA[" . $s3file_basename_prefix . "]]></event_media_name>" : '<event_media_name></event_media_name>';
									$xml_output .= (! empty ( $media_inappropriate )) ? "<event_media_inappropriate><![CDATA[" . json_encode ( $media_inappropriate ) . "]]></event_media_inappropriate>" : '';
									$xml_output .= (! empty ( $url )) ? "<event_media_url><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url ) . "]]></event_media_url>" : "<event_media_url/>";
									// web - video specific
									$xml_output .= (! empty ( $url_web )) ? "<event_media_url_web><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_web ) . "]]></event_media_url_web>" : '<event_media_url_web></event_media_url_web>';
									// 1080p video specific
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_url_1080p><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url_1080p ) . "]]></event_media_url_1080p>" : '<event_media_url_1080p></event_media_url_1080p>';
									$xml_output .= (! empty ( $thum_url )) ? "<event_media_video_thum><![CDATA[" . $this->url_signer->signArrayOfUrls ( $thum_url ) . "]]></event_media_video_thum>" : "<event_media_video_thum/>";
									$xml_output .= (! empty ( $url79x80 )) ? "<event_media_79x80><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url79x80 ) . "]]></event_media_79x80>" : "<event_media_79x80/>";
									$xml_output .= (! empty ( $url98x78 )) ? "<event_media_98x78><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url98x78 ) . "]]></event_media_98x78>" : "<event_media_98x78/>";
									$xml_output .= (! empty ( $url448x306 )) ? "<event_media_448x306><![CDATA[" . $this->url_signer->signArrayOfUrls ( $url448x306 ) . "]]></event_media_448x306>" : "<event_media_448x306/>";
									//dowload urls 
									$xml_output .= (! empty ( $url )) ? "<event_media_s3_url_path><![CDATA[" . json_encode($url) . "]]></event_media_s3_url_path>" : '<event_media_s3_url_path></event_media_s3_url_path>';
									$xml_output .= (! empty ( $url_web )) ? "<event_media_s3_url_web_path><![CDATA[" . json_encode($url_web) . "]]></event_media_s3_url_web_path>" : '<event_media_s3_url_web_path></event_media_s3_url_web_path>';
									$xml_output .= (! empty ( $url_1080p )) ? "<event_media_s3_url_1080p_path><![CDATA[" . json_encode($url_1080p) . "]]></event_media_s3_url_1080p_path>" : '<event_media_s3_url_1080p_path></event_media_s3_url_1080p_path>';
									$xml_output .= (! empty ( $s3file_download_path )) ? "<event_media_s3file_download_path><![CDATA[" . json_encode($s3file_download_path) . "]]></event_media_s3file_download_path>" : '<event_media_s3file_download_path></event_media_s3file_download_path>';

									$xml_output .= "</event_media>";
								}
								if ($only_audio_in_event) {
									$xml_output .= "<event_media>";
									$xml_output .= "<event_media_id></event_media_id>";
									$xml_output .= "<event_media_name></event_media_name>";
									$xml_output .= "<event_media_type></event_media_type>";
									$xml_output .= "<event_media_url></event_media_url>";
									$xml_output .= "<event_media_video_thum></event_media_video_thum>";
									$xml_output .= "<event_media_79x80></event_media_79x80>";
									$xml_output .= "<event_media_98x78></event_media_98x78>";
									$xml_output .= "<event_media_448x306></event_media_448x306>";
									$xml_output .= "</event_media>";
								}
							} else {
								// $xml_output .= "<event_media>";
								// $xml_output .= "<event_media_id></event_media_id>";
								// $xml_output .= "<event_media_name></event_media_name>";
								// $xml_output .= "<event_media_type></event_media_type>";
								// $xml_output .= "<event_media_url></event_media_url>";
								// $xml_output .= "<event_media_video_thum></event_media_video_thum>";
								// $xml_output .= "<event_media_79x80></event_media_79x80>";
								// $xml_output .= "<event_media_98x78></event_media_98x78>";
								// $xml_output .= "<event_media_448x306></event_media_448x306>";
								// $xml_output .= "</event_media>";
							}
						}
					}
					$xml_output .= " </event>";
				} // end for loop public events
				$xml_output .= "</events>";
			}
		} // end if ($is_public_event)
		$xml_output .= '</viewevents>';
		$xml_output .= '</xml>';
		// error_log ( "View Events.xml_output ----> $xml_output" . PHP_EOL );
		echo $xml_output;
	}
}
?>
