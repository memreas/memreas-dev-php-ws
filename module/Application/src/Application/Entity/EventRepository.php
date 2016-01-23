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
	public function getPublicEvents($date) {
		/**
		 * - This filter only returns public events with valid from / to / self_destruct(ghost) dates
		 */
		/**
		SELECT O.OrderNumber, CONVERT(date,O.OrderDate) AS Date,
		P.ProductName, I.Quantity, I.UnitPrice
		FROM [Order] O
		JOIN OrderItem I ON O.Id = I.OrderId
		JOIN Product P ON P.Id = I.ProductId
		ORDER BY O.OrderNumber
		*/
		
		$query_event = "select 	e.name, 
								e.event_id ,
								e.location,
								e.user_id,
								e.update_time,
								e.create_time,
								m.metadata
        					from Application\Entity\Event e,
						join Application\Entity\EventMedia em on e.event_id = em.event_id,
						join Application\Entity\Media m on em.media_id = m.media_id
            				where (e.public = 1) 
						and ((e.viewable_to >=" . $date . " or e.viewable_to ='')
            				and  (e.viewable_from <=" . $date . " or e.viewable_from =''))
            				or  (e.self_destruct >=" . $date . " or e.self_destruct='')
            				ORDER BY e.create_time DESC";
		// $statement->setMaxResults ( $limit );
		// $statement->setFirstResult ( $from );
		Mlog::addone('getPublicEvents()'.$query_event);
		$statement = $this->_em->createQuery ( $query_event );
		
		//return $statement->getResult ();
		return $statement->getArrayResult ();
	}
	public function getEventFriends($event_id, $rawData = false) {
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 'u.username', 'm.metadata' );
		$qb->from ( 'Application\Entity\User', 'u' );
		$qb->leftjoin ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = u.user_id' );
		$qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id' );
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
		$qb->select ( 'event_media.event_id','event_media.media_id','media.metadata' );
		$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
		$qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
		$qb->where ( 'event_media.event_id=?1' );
		$qb->where ( 'event_media.event_id IN (:ids)' );
		$qb->orderBy ( 'media.create_date', 'DESC' );
		$qb->setParameter ( 1, $event_id );
		
		if ($limit)
			$qb->setMaxResults ( $limit );
		return $qb->getQuery ()->getResult ();
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
		$json_array = json_decode ( $metadata, true );
		$url = "";
		if (($json_array ['S3_files'] ['file_type'] != 'audio') && isset ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] )) {
			$url = $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['thumbnails'] ['79x80'] [0] );
		} else {
			$url = $this->url_signer->signArrayOfUrls ( null );
		}
		// Mlog::addone ( __CLASS__ . '::' . __METHOD__ . '::$url', $url);
		return $url;
	}
	public function createEventCache() {
		$date = strtotime ( date ( 'd-m-Y' ) );
		$result = $this->getPublicEvents ( $date );
		Mlog::addone("getPublicEvents Array ", $result, 'p');
		$eventIndex = array ();
		
		
		/** this loop causes a sql call to the db for each event - won't scale... added left join event, event_media, media to get public events 
		foreach ( $result as $row ) {
			$eventIndex [$row ['event_id']] = $row;
			$mediaRows = $this->getEventMedia ( $row ['event_id'] );
			foreach ( $mediaRows as $mediaRow ) {
				$event_media_url = $this->getEventMediaUrl ( $mediaRow ['metadata'] );
				if (! empty ( $event_media_url )) {
					$eventIndex [$row ['event_id']] ['event_media_url'] = $event_media_url;
				}
			}
		}
		*/
		/** attempt to make one call to db - changed query in getPublic events to leftjoin event, event_media, and media*/
		foreach ( $result as $row ) {
			/** metadata is now in result */
			$eventIndex [$row ['event_id']] = $row;
			$event_media_url = $this->getEventMediaUrl ( $mediaRow ['metadata'] );
			$eventIndex [$row ['event_id']] ['event_media_url'] = $event_media_url;
		}
		/** call to fetch media all events */
		
		return $eventIndex;
	}
	public function getHashTags() {
		$qb = $this->_em->createQueryBuilder ();
		$qb->select ( 't.tag, t.tag_id, t.meta' );
		$qb->from ( 'Application\Entity\Tag', 't' );
		$qb->where ( 't.tag_type LIKE ?1' );
		$qb->setParameter ( 1, '#' );
		// error_log("query---->".$qb->getDql().PHP_EOL);
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
		error_log ( "filterPublicHashTags query---->" . $qb->getDql () . PHP_EOL );
		error_log ( "filterPublicHashTags event_ids---->" . json_encode ( $event_ids ) . PHP_EOL );
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
			error_log ( 'tag meta->' . $row ['meta'] . PHP_EOL );
			if (empty ( $json_array ['comment'] [0] )) {
				continue;
			}
			error_log ( 'comment[] as json->' . json_encode ( $json_array ['comment'] [0] ) . PHP_EOL );
			
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
							$event_media_url = $this->getEventMediaUrl ( $mediaRow ['metadata'] );
							if (! empty ( $event_media_url )) {
								$temp ['event_media_url'] ["$i"] = $event_media_url;
								$i ++;
							}
						}
					}
					$Index [] = $temp;
				} else {
					// For db lookup
					error_log ( "Inside Redis warmer createDiscoverCache tag:" . $temp ['tag_name'] . "event_id:" . $temp ['event_id'] . " in event_ids...@" . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
					$Index [] = $temp;
				}
			}
		}
		error_log ( "Leaving Redis warmer createDiscoverCache...@" . date ( 'Y-m-d H:i:s.u' ) . PHP_EOL );
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