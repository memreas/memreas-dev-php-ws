<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityRepository;
use Application\Model\MemreasConstants;
use Application\memreas\MemreasSignedURL;

class EventRepository extends EntityRepository
{
    public function __construct($em, \Doctrine\ORM\Mapping\ClassMetadata $class) {
        parent::__construct($em, $class);
        $this->url_signer = new MemreasSignedURL();
    }
    public function getLikeCount($event_id)
    {
    	$likeCountSql = $this->_em->createQuery ( 'SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.like= 1' );
        $likeCountSql->setParameter ( 1, $event_id );
        $likeCount = $likeCountSql->getSingleScalarResult ();

        return $likeCount;
    }

    public function getCommentCount($event_id){
        $commCountSql = $this->_em->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.event_id=?1 AND c.type= 'text'" );
        $commCountSql->setParameter ( 1, $event_id );
        $commCount = $commCountSql->getSingleScalarResult ();

        return $commCount;
    }


    public function getEvents($date)
    { 	$query_event = "select e.name, e.event_id ,e.location,e.user_id,e.update_time,e.create_time
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

	public function getEventMedia($event_id,$limit =false)
	{
		$qb = $this->_em->createQueryBuilder ();
        	$qb->select ( 'media.metadata' );
        	$qb->from ( 'Application\Entity\EventMedia', 'event_media' );
            $qb->join ( 'Application\Entity\Media', 'media', 'WITH', 'event_media.media_id = media.media_id' );
            $qb->where ( 'event_media.event_id=?1' );
            $qb->orderBy ( 'media.create_date', 'DESC' ) ;
            $qb->setParameter ( 1, $event_id );
            if($limit) $qb->setMaxResults ( $limit );
        return  $qb->getQuery ()->getResult ();
	}


	public function getProfileUrl($metadata='')
	{
		$json_array = json_decode ( $metadata, true );
        $url = MemreasConstants::ORIGINAL_URL. '/memreas/img/profile-pic.jpg';
        if (! empty ( $json_array ['S3_files'] ['path'] )){
            
            $url = $this->url_signer->signArrayOfUrls(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path']);
        }

              return $url;
	}
	public function getEventMediaUrl($metadata='',$size='')
	{
	$json_array = json_decode ( $metadata, true );
        $url = MemreasConstants::ORIGINAL_URL.'/memreas/img/small-pic-3.jpg'; 
        if (empty($size)) {
           
            if (! empty ( $json_array ['S3_files'] ['path'] )){
                $url =$this->url_signer->signArrayOfUrls(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array ['S3_files'] ['path']);
error_log("path url -----> ".$url.PHP_EOL);            	
            }
        }  else {
            if (! empty ( $json_array ['S3_files'] ['thumbnails'] ['79x80']) ){
            	
                $url =$this->url_signer->signArrayOfUrls($json_array ['S3_files'] ['thumbnails'] ['79x80']);
error_log("path 79x80 -----> ".$url.PHP_EOL);            	
            }
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
        foreach ($mediaRows as $mediaRow) {

            $eventIndex[$row['event_id']]['event_media_url'] = $this->getEventMediaUrl($mediaRow['metadata']);
            $eventIndex[$row['event_id']]['event_photo'] =    $this->getEventMediaUrl($mediaRow['metadata'],'thumb');

             break;
        }
    }

    return $eventIndex;

  }

  function createDiscoverCache($tag){
    $date = strtotime ( date ( 'd-m-Y' ) );
    $qb = $this->_em->createQueryBuilder ();
        $qb->select('t.meta,t.tag');
        $qb->from('Application\Entity\Tag',  't');
        $qb->where('t.tag LIKE ?1');
         $qb->setParameter ( 1, "$tag%");
        $result = $qb->getQuery ()->getResult ();

    $Index = array();


    foreach ($result as $row) {
        $temp =array() ;
        $json_array = json_decode ( $row['meta'], true );
        error_log ('comment'. print_r($json_array['comment']))
        foreach($json_array['comment'] as $k => $comm){
            $temp['name'] = $row['tag'];
            $event = $this->_em->find ( 'Application\Entity\Event', $json_array['event'][$k] );
            $temp['event_name'] = $event->name;
            $temp['event_id'] = $event->event_id;
            $event_media     = $this->_em->find ( 'Application\Entity\Media', $json_array['media'][$k] );
            $temp['event_photo'] = $this->getEventMediaUrl($event_media->metadata,'thumb');
            $comment = $this->_em->find ( 'Application\Entity\Comment', $json_array['comment'][$k] );
            $temp['comment'] = $comment->text;
            $temp['update_time'] = $comment->update_time;
            $commenter = $this->getUser($comment->user_id,'row');
            $temp['commenter_photo'] = $commenter['profile_photo'];
            $temp['commenter_name'] = '@'.$commenter['username'];

           

             $Index[] =$temp;
        }


    }
     return $Index;

  }
   function chkEventFriendRule($eventId,$friendId)
    { 

        $status = 'no';
         $qb = $this->_em->createQueryBuilder ();
            $qb->select ( 'uf.friend_id' );
            $qb->from ( 'Application\Entity\EventFriend', 'ef' );
            $qb->join ( 'Application\Entity\UserFriend', 'uf', 'WITH', 'uf.user_id = ef.friend_id' );
            $qb->andWhere ( 'uf.friend_id=?2 AND ef.event_id=?1' );
            $qb->setParameter ( 1, $eventId );
            $qb->setParameter ( 2, $friendId );

 
        return $qb->getQuery ()->getResult ();
    }
function getUser($user_id,$allRow=''){
  //check in catch

  //if found return
//else get from db
    $o=null;
            $qb = $this->_em->createQueryBuilder ();
               $qb->select ( 'u.email_address','u.user_id','u.username', 'm.metadata' );
               $qb->from ( 'Application\Entity\User', 'u' );
               $qb->leftjoin ( 'Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1' );
               $qb->where ( 'u.user_id=?1 ' );
               $qb->setParameter ( 1, $user_id );
               $rows = $qb->getQuery()->getResult();
               
               $row =$rows[0] ;
               $row['profile_photo'] = $this->getProfileUrl($row['metadata']);
                if($allRow){
                 return $row;  
               }
                $o[$row['user_id']]=$row;

    return $o;


}
   


}