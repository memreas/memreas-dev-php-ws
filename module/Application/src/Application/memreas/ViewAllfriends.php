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

class ViewAllfriends {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $url_signer;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside ViewAllfriends.__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		error_log ( "Inside ViewAllfriends.exec()..." . PHP_EOL );
		error_log ( "Inside ViewAllfriends.exec().xml ---> " . $_POST ['xml'] . PHP_EOL );
		
		$data = simplexml_load_string ( $_POST ['xml'] );
		$user_id = $data->viewallfriends->user_id;
		$error_flag = 0;
		$count = 0;
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml><friends>";
		if (! isset ( $user_id ) || empty ( $user_id )) {
			$error_flag = 1;
			$message = 'User id is empty';
		} else {
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'f', 'u' );
			$qb->from ( 'Application\Entity\Friend', 'f' );
			$qb->join ( 'Application\Entity\UserFriend', 'uf', 'WITH', 'uf.friend_id = f.friend_id' )->andwhere ( "uf.user_approve = '1'" );
			$qb->join ( 'Application\Entity\User', 'u', 'WITH', 'uf.friend_id = u.user_id' )->andwhere ( "u.user_id = :userid" )->setParameter ( 'userid', $user_id );
			$qb->orderBy ( 'u.username', 'ASC' );
			// error_log("dql ---> ".$qb->getQuery()->getSql().PHP_EOL);
			$result = $qb->getQuery ()->getResult ();
			
			if (! $result) {
				$error_flag = 1;
				$message = mysql_error ();
			} else {
				if (count ( $result ) > 0) {
					$xml_output .= "<status>Success</status><message>Friends list</message>";
					foreach ( $result as $row1 ) {
						$count ++;
						$view_all_friend [$count] ['id'] = $row1->friend_id;
						$view_all_friend [$count] ['network'] = $row1->network;
						$view_all_friend [$count] ['social_username'] = $row1->username;
						$view_all_friend [$count] ['url_image'] = $this->url_signer->signArrayOfUrls ( $row1->url_image );
					}
				} else {
					$error_flag = 2;
					$message = "No Record Found";
				}
			}
		}
		if ($error_flag) {
			$xml_output .= "<status>Success</status><message>$message</message>";
		} else {
			foreach ( $view_all_friend as $friend ) {
				$xml_output .= "<friend>";
				$xml_output .= "<friend_id>" . $friend ['id'] . "</friend_id>";
				$xml_output .= "<network>" . $friend ['network'] . "</network>";
				$xml_output .= "<social_username>" . $friend ['social_username'] . "</social_username>";
				$xml_output .= "<url><![CDATA[" . $friend ['url_image'] . "]]></url>";
				$xml_output .= "<url_79x80><![CDATA[" . empty ( $friend ['url_image_79x80'] ) ? '' : $this->url_signer->signArrayOfUrls ( $friend ['url_image_79x80'] ) . "]]></url_79x80>";
				$xml_output .= "<url_448x306><![CDATA[" . empty ( $friend ['url_image_448x306'] ) ? '' : $this->url_signer->signArrayOfUrls ( $friend ['url_image_448x306'] ) . "]]></url_448x306>";
				$xml_output .= "<url_98x78><![CDATA[" . empty ( $friend ['url_image_98x78'] ) ? '' : $this->url_signer->signArrayOfUrls ( $friend ['url_image_98x78'] ) . "]]></url_98x78>";
				$xml_output .= "</friend>";
			}
		}
		$xml_output .= "</friends>";
		$group = "SELECT g  FROM Application\Entity\Group g  where g.user_id = '" . $user_id . "'";
		$statement = $this->dbAdapter->createQuery ( $group );
		$res = $statement->getResult ();
		$xml_output .= "<groups>";
		if (count ( $res ) <= 0) {
			/*
			 * $xml_output .= "<group>";
			 * $xml_output .= "<group_id></group_id>";
			 * $xml_output .= "<group_name></group_name>";
			 * $xml_output .= "</group>";
			 */
		} else
			foreach ( $res as $row ) {
				$xml_output .= "<group>";
				$xml_output .= "<group_id>" . $row->group_id . "</group_id>";
				$xml_output .= "<group_name>" . $row->group_name . "</group_name>";
				$xml_output .= "</group>";
			}
		$xml_output .= "</groups>";
		$xml_output .= "</xml>";
		error_log ( "Exiting ViewAllfriends.exec().xml ---> " . $xml_output . PHP_EOL );
		echo $xml_output;
	}
}

?>
