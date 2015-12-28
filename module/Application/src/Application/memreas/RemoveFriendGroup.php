<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
/*
 * Remove friend from group
 * @params:
 * @Return
 * @Tran Tuan
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Group;
use Application\Entity\FriendGroup;

class RemoveFriendGroup {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		
		$group_id = $data->removefriendgroup->group_id;
		$friends = $data->removefriendgroup->friends->friend;
		// Check if group exist or not
		$group_query = $this->dbAdapter->createQueryBuilder ();
		$group_query->select ( 'g' )->from ( 'Application\Entity\Group', 'g' )->where ( 'g.group_id = ?1' )->setParameter ( 1, $group_id );
		$result = $group_query->getQuery ()->getResult ();
		if (empty ( $result )) {
			$status = 'Failure';
			$message = 'This group does not exist';
		} else {
			foreach ( $friends as $key => $friend ) {
				$friend_id = $friend->friend_id;
				$query_str = "DELETE FROM Application\Entity\FriendGroup fg WHERE fg.group_id = '$group_id' AND fg.friend_id = '$friend_id'";
				$query = $this->dbAdapter->createQuery ( $query_str );
				$result = $query->getResult ();
				if (count ( $result ) > 0) {
					$status = 'Success';
					$message = 'Friends removed to the group';
				} else {
					$status = 'Failure';
					$message = 'Failed to remove friend from group.';
				}
			}
		}
		
		if ($frmweb) {
			return $output;
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<removefriendgroupresponse>";
		$xml_output .= "<status>{$status}</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= "</removefriendgroupresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
