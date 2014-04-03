<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityRepository;
use Application\Model\MemreasConstants;



class EventRepository extends EntityRepository
{
    public function getLikeCount($event_id)
    {
    	$likeCountSql = $this->_em->createQuery ( 'SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like= 1' );
          $likeCountSql->setParameter ( 1, $event_id );
          $likeCount = $likeCountSql->getSingleScalarResult ();
           
        return $likeCount; 
    }


    public function getEvents($date)
    { 	$query_event = "select e.name, e.event_id ,e.location
                from Application\Entity\Event e  
                where (e.viewable_to >=" . $date . " or e.viewable_to ='')
                    and  (e.viewable_from <=" . $date . " or e.viewable_from ='') 
                    and  (e.self_destruct >=" . $date . " or e.self_destruct='') 
                ORDER BY e.create_time DESC";
                // $statement->setMaxResults ( $limit );
     // $statement->setFirstResult ( $from );

      	$statement = $this->_em->createQuery ( $query_event );
            
        return $statement->getResult (); 
    }

	public function getEventFriends($event_id, $rawData=false)
	{
		$qb = $this->_em->createQueryBuilder ();
	            $qb->select ( 'u.username', 'm.metadata' );
	            $qb->from ( 'Application\Entity\User', 'u' );
	            $qb->leftjoin ( 'Application\Entity\EventFriend', 'ef', 'WITH', 'ef.friend_id = u.user_id' );
	            $qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
	            $qb->where ( 'ef.event_id=?1 ' );
	            $qb->setParameter ( 1, $event_id );
                $rows = $qb->getQuery ()->getResult ();
                if($rawData){
                    return $rows;
                }
                $out = array();
                foreach ($rows as &$row) {
                    $o['profile_photo'] = $this->getProfileUrl($row['metadata']);
                    $o['username'] = $row['username'];
                    $out[] =$o;
                }

                return  $out;

	}

	public function getEventMedia($event_id)
	{
		$qb = $this->_em->createQueryBuilder ();
        	$qb->select ( 'media.metadata' );
        	$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
            $qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
            $qb->where ( 'event_media.event_id=?1' );
            $qb->orderBy ( 'media.create_date', 'DESC' ) ;
            $qb->setParameter ( 1, $event_id );
        return  $qb->getQuery ()->getResult ();
	}
	

	public function getProfileUrl($metadata='')
	{
		$json_array = json_decode ( $metadata, true );
        $url = '/memreas/img/profile-pic.jpg';
        if (! empty ( $json_array ['S3_files'] ['path'] )){
            $url = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path'];
        }

              return $url;
	}
	public function getEventMediaUrl($metadata='')
	{
		$json_array = json_decode ( $metadata, true );
        $url = '/memreas/img/small-pic-3.jpg';
        if (! empty ( $json_array ['S3_files'] ['path'] )){
            $url = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path'];
        }

              return $url;
	}
     function createEventCache(){
    $date = strtotime ( date ( 'd-m-Y' ) );
    $result = $this->getEvents($date);
    $eventIndex = array();
    foreach ($result as $row) {
        $eventIndex[$row['event_id']] = $row;     
        $mediaRows = $this->getEventMedia($row['event_id']);   
        $eventIndex[$row['event_id']]['event_photo'] =    $this->getEventMediaUrl();
        foreach ($mediaRows as $mediaRow) {
            
             $eventIndex[$row['event_id']]['event_media_url'] = $this->getEventMediaUrl($mediaRow['metadata']);

             break;
        } 
    }

    return $eventIndex;
  
  }


}