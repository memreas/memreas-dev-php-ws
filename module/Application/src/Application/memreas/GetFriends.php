<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
/*
 * Get user's friends
 * @params: Provide user id to get back detail
 * @Return Friend list
 * @Tran Tuan
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\UserFriend;
use Application\Entity\Friend;

class GetFriends {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $url_signer;
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
		$user_id = trim ( $data->getfriends->user_id );
		
		$qb = $this->dbAdapter->createQueryBuilder ();
		$qb->select ( 'f' );
		$qb->from ( 'Application\Entity\UserFriend', 'uf' );
		$qb->join ( 'Application\Entity\Friend', 'f', 'WITH', 'uf.friend_id = f.friend_id' );
		$qb->where ( "uf.user_id=?1 AND uf.user_approve = 1" );
		$qb->setParameter ( 1, $user_id );
		$result_friends = $qb->getQuery ()->getResult ();
		Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$result_friends', $result_friends);
		if (empty ( $result_friends )) {
			$status = "Failure";
			$message = "You have no friend at this time.";
		} else {
			$status = 'Success';
			$output .= '<friends>';
			foreach ( $result_friends as $friend ) {
				$output .= '<friend>';
				$output .= '<friend_id>' . $friend->friend_id . '</friend_id>';
				$output .= '<friend_name>' . $friend->social_username . '</friend_name>';
				
				//check redis for profile image to get latest
				$redis = AWSMemreasRedisCache::getHandle ();
				$friend_profile = json_decode($redis->cache->hget ( '@person_meta_hash', $friend->social_username ), true);
				Mlog::addone(__CLASS__.__METHOD__.__LINE__.'::$friend_profile', $friend_profile);
				if ($friend_profile) {
					$profile_image = json_encode ( $friend_profile ['profile_photo_79x80'] );
				} else {
					$profile_image = $this->url_signer->fetchSignedURL ( null );
				}
				$output .= '<photo><![CDATA[' . $profile_image . ']]></photo>';
				$output .= '</friend>';
			}
			$output .= '</friends>';
		}
		
		if ($frmweb) {
			return $output;
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<getfriendsresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</getfriendsresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
