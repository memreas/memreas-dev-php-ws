<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityRepository;
use Application\Model\MemreasConstants;
use Application\memreas\MemreasSignedURL;
use Application\memreas\Utility;
use Application\memreas\Mlog;

class EventRepository extends EntityRepository {
	public function __construct($em, \Doctrine\ORM\Mapping\ClassMetadata $class) {
		parent::__construct ( $em, $class );
		$this->url_signer = new MemreasSignedURL ();
	}
	public function getLikeCount($event_id) {
		$likeCountSql = $this->_em->createQuery ( 'SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like= 1' );
		$likeCountSql->setParameter ( 1, $event_id );
		$likeCount = $likeCountSql->getSingleScalarResult ();
		
		return $likeCount;
	}
	public function getCommentCount($event_id) {
		$commCountSql = $this->_em->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.type= 'text'" );
		$commCountSql->setParameter ( 1, $event_id );
		$commCount = $commCountSql->getSingleScalarResult ();
		
		return $commCount;
	}
	public function getEventNames($date) {
		$query_event = "select e.name, e.event_id
        	from Application\Entity\Event e
            where ((e.viewable_to >=" . $date . " or e.viewable_to ='')
            and  (e.viewable_from <=" . $date . " or e.viewable_from =''))
            or  (e.self_destruct >=" . $date . " or e.self_destruct='')
			ORDER BY e.create_time DESC";
		// $statement->setMaxResults ( $limit );
		// $statement->setFirstResult ( $from );
		
		$statement = $this->_em->createQuery ( $query_event );
		
		return $statement->getResult ();
		
		// example where in
		// $query_builder->andWhere('r.winner IN (:ids)')
		// ->setParameter('ids', $ids);
	}
	public function createEventCache($type) {
		$date = strtotime ( date ( 'd-m-Y' ) );
		if ($type == 'public') {
			// Mlog::addone ( "createEventCache::", '$this->getPublicEvents ( $date )' );
			$result = $this->getPublicEvents ( $date );
		} else if ($type == 'friends') {
			// Mlog::addone ( "createEventCache::", '$this->getFriendEvents ( $date )' );
			$result = $this->getFriendEvents ( $date );
		} else if ($type == 'my') {
			$result = $this->getMyEvents ( $date );
		}
		/**
		 * attempt to make one call to db - changed query in getPublic events to leftjoin event, event_media, and media
		 */
		$search_result = array ();
		// //Mlog::addone("createEventCache::","eventIndex for loop start" );
		foreach ( $result as $row ) {
			$eventIndex = array ();
			/**
			 * metadata is now in result
			 */
			// Mlog::addone ( 'createEventCache()::$row [event_id],$row [metadata]', $row ['event_id'] . '---->' . $row [metadata] );
			// Mlog::addone ( 'createEventCache()::$row ---->', $row, 'p' );
			$event_id = $row ['event_id'];
			/**
			 * event media
			 */
			$result = $this->getEventMedia ( $event_id );
			if ($result) {
				$eventIndex ['event_media'] = $result;
				$event_media_url = (! empty ( $row ['metadata'] )) ? $this->getEventMediaUrl ( $row ['metadata'] ) : $this->getEventMediaUrl ( '' );
				$event_media_url = json_decode ( $event_media_url );
				$eventIndex ['event_photo'] = $event_media_url [0];
			} else {
				$eventIndex ['event_media'] = [];
				$eventIndex ['event_photo'] = [];
			}
			$eventIndex ['event_id'] = $row ['event_id'];
			$eventIndex ['user_id'] = $row ['user_id'];
			$eventIndex ['name'] = '!' . $row ['name'];
			$eventIndex ['event_creator_name'] = '@' . $row ['username'];
			$event_creator_pic = (! empty ( $row ['media_metadata'] )) ? $this->getEventMediaUrl ( $row ['media_metadata'] ) : $this->getEventMediaUrl ( '' );
			$event_creator_pic = json_decode ( $event_creator_pic );
			$eventIndex ['event_creator_pic'] = $event_creator_pic [0];
			/**
			 * comment_count
			 */
			$result = $this->getCommentCount ( $event_id );
			if ($result) {
				$eventIndex ['comment_count'] = $result;
			} else {
				$eventIndex ['comment_count'] = 0;
			}
			/**
			 * like_count
			 */
			$result = $this->getLikeCount ( $event_id );
			if ($result) {
				$eventIndex ['like_count'] = $result;
			} else {
				$eventIndex ['like_count'] = 0;
			}
			$result = $this->getEventFriends ( $event_id );
			if ($result) {
				$eventIndex ['friends'] = $result;
			} else {
				$eventIndex ['friends'] = [];
			}
						
			
			/*
			 * $eventIndex ['id'] = $event_id;
			 * $eventIndex [$row ['event_id']] = $row;
			 * $event_media_url = $this->getEventMediaUrl ( $row ['metadata'] );
			 * $event_media_url = json_decode($event_media_url);
			 * $event_media_url = $event_media_url[0];
			 *
			 * if ($result) {
			 * $event_media_url = $result;
			 * } else {
			 * $event_media_url = null;
			 * }
			 * $eventIndex [$row ['event_id']] ['event_media_url'] = $event_media_url;
			 * $eventIndex ['name'] = '!' . $row ['name'];
			 * $result = $this->getEventMedia ( $event_id );
			 * if ($result) {
			 * $eventIndex ['event_media'] = $result;
			 * } else {
			 * $eventIndex ['event_media'] = null;
			 * }
			 * $result = $this->getCommentCount ( $event_id );
			 * if ($result) {
			 * $eventIndex ['comment_count'] = $result;
			 * } else {
			 * $eventIndex ['comment_count'] = 0;
			 * }
			 * $result = $this->getLikeCount ( $event_id );
			 * if ($result) {
			 * $eventIndex ['like_count'] = $result;
			 * } else {
			 * $eventIndex ['like_count'] = 0;
			 * }
			 * $result = $this->getEventFriends ( $event_id );
			 * if ($result) {
			 * $eventIndex ['friends'] = $result;
			 * } else {
			 * $eventIndex ['friends'] = null;
			 * }
			 * $eventIndex ['created_on'] = Utility::formatDateDiff ( $row ['create_time'] );
			 * $event_creator = $this->getUser ( $row ['user_id'], 'row' );
			 * $eventIndex ['event_creator_name'] = '@' . $event_creator ['username'];
			 * $eventIndex ['event_creator_pic'] = $event_creator ['profile_photo'];
			 */
			
			$search_result [] = $eventIndex;
		}
		//Mlog::addone('createEventCache::',$search_result, 'p' );
		return $search_result;
	}
	public function getPublicEvents($date) {
		try {
			/**
			 * - This filter only returns public events and public events with valid from / to / self_destruct(ghost) dates
			 */
			$query = "SELECT 
						e.event_id, 
						e.user_id, 
						e.name, 
						e.location, 
						e.date, 
						e.friends_can_post, 
						e.friends_can_share, 
						e.public, 
						e.viewable_from, 
						e.viewable_to, 
						e.self_destruct, 
						e.metadata as event_metadata, 
						e.create_time, 
						e.update_time,
						u.username,
						m.media_id, 
						m.metadata as media_metadata
						FROM Application\Entity\Event e
						LEFT JOIN Application\Entity\User u WITH (e.user_id = u.user_id) 
						LEFT JOIN Application\Entity\Media m WITH (m.user_id = u.user_id AND m.is_profile_pic = 1) 
						WHERE e.public = 1";
			// //Mlog::addone ( 'public $query--->', $query );
			$statement = $this->_em->createQuery ( $query );
			$result = $statement->getResult ();
			// //Mlog::addone ( 'getPublicEvents()::$result--->', $result, 'p' );
			
			return $result;
		} catch ( Doctrine_Connection_Exception $e ) {
			// Mlog::addone ( 'getPublicEvents()::Code : ', $e->getPortableCode () );
			// Mlog::addone ( 'getPublicEvents()::Message : ', $e->getPortableMessage () );
		}
		return null;
	}
	public function getFriendEvents($date) {
		try {
			/**
			 * - This filter only returns events where user is a friend events and with valid from / to / self_destruct(ghost) dates
			 */
			$user_id = $_SESSION ['user_id'];
			$query = "SELECT 
						e.event_id, 
						e.user_id, 
						e.name, 
						e.location, 
						e.date, 
						e.friends_can_post, 
						e.friends_can_share, 
						e.public, 
						e.viewable_from, 
						e.viewable_to, 
						e.self_destruct, 
						e.metadata as event_metadata, 
						e.create_time, 
						e.update_time,
						u.username,
						m.media_id, 
						m.metadata as media_metadata
						FROM Application\Entity\Event e 
						LEFT JOIN Application\Entity\User u WITH (u.user_id = e.user_id)
						LEFT JOIN Application\Entity\Media m WITH (m.user_id = u.user_id AND m.is_profile_pic = 1)
						INNER JOIN Application\Entity\EventFriend ef WITH (e.event_id = ef.event_id)
						WHERE e.public <> 1
						AND e.event_id = ef.event_id
						AND ef.user_approve = 1
						and ef.friend_id = '$user_id'";
			// //Mlog::addone ( 'Friend Events $query--->', $query );
			$statement = $this->_em->createQuery ( $query );
			$result = $statement->getResult ();
			// //Mlog::addone ( 'getFriendEvents()::$result--->', $result, 'p' );
			
			return $result;
		} catch ( Doctrine_Connection_Exception $e ) {
			// Mlog::addone ( 'getFriendEvents()::Code : ', $e->getPortableCode () );
			// Mlog::addone ( 'getFriendEvents()::Message : ', $e->getPortableMessage () );
		}
		return null;
	}
	public function getMyEvents($date) {
		try {
			/**
			 * - This filter only returns events where user is a friend events and with valid from / to / self_destruct(ghost) dates
			 */
			$user_id = $_SESSION ['user_id'];
			$query = "SELECT SELECT 
						e.event_id, 
						e.user_id, 
						e.name, 
						e.location, 
						e.date, 
						e.friends_can_post, 
						e.friends_can_share, 
						e.public, 
						e.viewable_from, 
						e.viewable_to, 
						e.self_destruct, 
						e.metadata as event_metadata, 
						e.create_time, 
						e.update_time,
						u.username,
						m.media_id, 
						m.metadata as media_metadata
					FROM Application\Entity\Event e
					LEFT JOIN Application\Entity\User u WITH (e.user_id = u.user_id)
					LEFT JOIN Application\Entity\Media m WITH (m.user_id = u.user_id AND m.is_profile_pic = 1)
					WHERE e.user_id = '$user_id'";
			// //Mlog::addone ( 'My Events $query--->', $query );
			$statement = $this->_em->createQuery ( $query );
			$result = $statement->getResult ();
			// //Mlog::addone ( 'getMyEvents()::$result--->', $result, 'p' );
			
			return $result;
		} catch ( Doctrine_Connection_Exception $e ) {
			// Mlog::addone ( 'getMyEvents()::Code : ', $e->getPortableCode () );
			// Mlog::addone ( 'getMyEvents()::Message : ', $e->getPortableMessage () );
		}
		return null;
	}
	public function getEventFriends($event_id, $rawData = false) {
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'u.username', 'm.metadata' );
		$qb->from ( 'Application\Entity\User', 'u' );
		$qb->leftjoin ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = u.user_id' );
		$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
		$qb->where ( 'ef.event_id=?1 ' );
		$qb->setParameter ( 1, $event_id );
		$rows = $qb->getQuery ()->getResult ();
		
		if ($rawData) {
			return $rows;
		}
		$out = array ();
		foreach ( $rows as &$row ) {
			$o ['profile_photo'] = $this->getProfileUrl ( $row ['metadata'] );
			$o ['username'] = $row ['username'];
			$out [] = $o;
		}
		
		return $out;
	}
	public function getEventMedia($event_id, $limit = false) {
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'event_media.event_id', 'event_media.media_id', 'media.metadata' );
		$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
		$qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
		$qb->where ( 'event_media.event_id=?1' );
		// $qb->where ( 'event_media.event_id IN (:ids)' );
		$qb->orderBy ( 'media.create_date', 'DESC' );
		$qb->setParameter ( 1, $event_id );
		
		// //Mlog::addone ( 'getEventMedia $qb->getQuery ()->getSQL()', $qb->getQuery ()->getSQL () );
		
		if ($limit)
			$qb->setMaxResults ( $limit );
		$eventMedia = $qb->getQuery ()->getResult ();
		$eventMediaArr = array ();
		foreach ( $eventMedia as $row ) {
			//Mlog::addone ( 'getEventMedia for loop row --->', $row, 'p' );
			$eventMediaArrRow = array ();
			$eventMediaArrRow ['event_id'] = $row ['event_id'];
			$eventMediaArrRow ['media_id'] = $row ['media_id'];
			$eventMediaArrRow ['metadata'] = json_decode ( $row ['metadata'] );
			$eventMediaArr [] = $eventMediaArrRow;
		}
		return $eventMediaArr;
	}
	public function getProfileUrl($metadata = '') {
		$json_array = json_decode ( $metadata, true );
		// $url = MemreasConstants::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
		/*
		 * -
		 * signArrayofUrls always returns an array so we get [0]
		 */
		$url = "";
		if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] )) {
			$url = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] );
		} else {
			$url = $this->url_signer->signArrayOfUrls ( null );
		}
		return $url;
	}
	public function getEventMediaUrl($metadata = '', $size = '') {
		/*
		 * -
		 * signArrayofUrls always returns an array so we get [0]
		 */
		// Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$metadata', $metadata );
		if (! empty ( $metadata )) {
			if (is_array($metadata) || is_object($metadata)) {
				$json_array = json_decode(json_encode($metadata), true);
			} else {
				
				$json_array = json_decode ( $metadata, true );
			}
			$url = "";
			if (($json_array ['S3_files'] ['file_type'] != 'audio') && isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] )) {
				$url = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] );
			}
		} else {
			$url = $this->url_signer->signArrayOfUrls ( null );
		}
		// //Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$url', $url);
		return $url;
	}
	public function getHashTags() {
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 't.tag, t.tag_id, t.meta' );
		$qb->from ( 'Application\Entity\Tag', 't' );
		$qb->where ( 't.tag_type LIKE ?1' );
		$qb->setParameter ( 1, '#' );
		// error_log ( "query---->" . $qb->getQuery ()->getSQL () . PHP_EOL );
		$result = $qb->getQuery ()->getResult ();
		
		return $result;
	}
	public function filterPublicHashTags($event_ids) {
		// error_log("Inside Redis warmer filterPublicHashTags...@".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'e.event_id' );
		$qb->from ( 'Application\Entity\Event', 'e' );
		$qb->where ( 'e.public = 1' );
		$qb->andwhere ( 'e.event_id IN (:ids)' );
		$qb->setParameter ( 'ids', $event_ids );
		// error_log ( "filterPublicHashTags query---->" . $qb->getDql () . PHP_EOL );
		// error_log ( "filterPublicHashTags event_ids---->" . json_encode ( $event_ids ) . PHP_EOL );
		$result = $qb->getQuery ()->getResult ();
		// error_log("Leaving Redis warmer filterPublicHashTags...@".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
		return $result;
	}
	public function filterFriendHashTags($event_ids, $user_id) {
		// error_log("Inside Redis warmer filterFriendHashTags...@".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'ef.event_id' );
		$qb->from ( 'Application\Entity\EventFriend', 'ef' );
		$qb->where ( 'ef.friend_id = ?1' ); // get all hashtags
		$qb->andwhere ( 'ef.user_approve = 1' ); // get all hashtags
		$qb->andwhere ( 'ef.event_id IN (:eids)' );
		$qb->setParameter ( 1, $user_id );
		$qb->setParameter ( 'eids', $event_ids );
		// error_log("filterPublicHashTags query---->".$qb->getDql().PHP_EOL);
		$result = $qb->getQuery ()->getResult ();
		// error_log("Leaving Redis warmer filterFriendHashTags...@".date( 'Y-m-d H:i:s.u' ).PHP_EOL);
		return $result;
	}
	public function createDiscoverCache($tag, $event_ids = null) {
		$Index = array ();
		$date = strtotime ( date ( 'd-m-Y' ) );
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 't.tag,t.tag_id,t.meta' );
		$qb->from ( 'Application\Entity\Tag', 't' );
		$qb->where ( 't.tag LIKE ?1' );
		$qb->setParameter ( 1, "$tag%" );
		$result = $qb->getQuery ()->getResult ();
		// error_log("createDiscoverCache SQL--->".$qb->getQuery()->getSql().PHP_EOL);
		foreach ( $result as $row ) {
			$temp = array ();
			$json_array = json_decode ( $row ['meta'], true );
			// error_log ( 'tag meta->' . $row ['meta'] . PHP_EOL );
			if (empty ( $json_array ['comment'] [0] )) {
				continue;
			}
			// error_log ( 'comment[] as json->' . json_encode ( $json_array ['comment'] [0] ) . PHP_EOL );
			
			foreach ( $json_array ['comment'] as $k => $comm ) {
				$temp ['tag_name'] = $row ['tag'];
				$event = $this->_em->find ( 'Application\Entity\Event', $json_array ['event'] [$k] );
				$temp ['event_name'] = $event->name;
				$temp ['event_id'] = $event->event_id;
				$media = $this->_em->find ( 'Application\Entity\Media', $json_array ['media'] [$k] );
				if (! empty ( $media->metadata )) {
					$temp ['event_media_url'] [] = $this->getEventMediaUrl ( $event_media->metadata, 'thumb' );
				}
				$comment = $this->_em->find ( 'Application\Entity\Comment', $json_array ['comment'] [$k] );
				$temp ['comment'] = $comment->text;
				$temp ['update_time'] = $comment->update_time;
				$commenter = $this->getUser ( $comment->user_id, 'row' );
				$temp ['commenter_photo'] = $commenter ['profile_photo'];
				$temp ['commenter_name'] = $commenter ['username'];
				if (! empty ( $temp ['event_id'] )) {
					$event_ids [] = $temp ['event_id'];
				}
				if (! empty ( $event_ids )) {
					// For Redis lookup
					// if (in_array ( $temp ['event_id'], $event_ids )) {
					foreach ( $event_ids as $event_id ) {
						// Fetch event media
						error_log ( "event id -->" . $event_id . PHP_EOL );
						$event_media = $this->getEventMedia ( $event_id );
						$i = 0;
						foreach ( $event_media as $mediaRow ) {
							$event_media_url = (!empty ($this->getEventMediaUrl ( $mediaRow ['metadata'] ))) ? $this->getEventMediaUrl ( $mediaRow ['metadata'] ) : $this->getEventMediaUrl ( '' );
							//$this->getEventMediaUrl ( $mediaRow ['metadata'] ) 
							$event_media_url = json_decode ( $event_media_url );
							$event_media_url = $event_media_url [0];
							
							if (! empty ( $event_media_url )) {
								$temp ['event_media_url'] ["$i"] = $event_media_url;
								$i ++;
							}
						}
					}
					$Index [] = $temp;
				} else {
					// For db lookup
					// error_log ( "Inside Redis warmer createDiscoverCache tag:" . $temp ['tag_name'] . "event_id:" . $temp ['event_id'] . " in event_ids...@" . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
					$Index [] = $temp;
				}
			}
		}
		// error_log ( "Leaving Redis warmer createDiscoverCache...@" . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
		return $Index;
	}
	function checkFriendLevelRule($eventId, $eventOwnerId, $userId, $friendId) {
		$allowAddFriends = 1;
		/**
		 * Fetch event owner by event_id
		 */
		if ($eventOwnerId != $userId) {
			// user is a friend (level 2) so friend_id is level 3
			$allowAddFriends = 0;
		}
		
		return $allowAddFriends;
	}
	function getUser($user_id, $allRow = '') {
		// check in catch
		// if found return
		// else get from db
		$o = null;
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'u.email_address', 'u.user_id', 'u.username', 'm.metadata' );
		$qb->from ( 'Application\Entity\User', 'u' );
		$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
		$qb->where ( 'u.user_id=?1 ' );
		$qb->setParameter ( 1, $user_id );
		$rows = $qb->getQuery ()->getResult ();
		// error_log("getUser SQL --->".$qb->getQuery()->getSql());
		
		$row = $rows [0];
		$row ['profile_photo'] = $this->getProfileUrl ( $row ['metadata'] );
		
		if ($allRow) {
			return $row;
		}
		$o [$row ['user_id']] = $row;
		
		return $o;
	}
}