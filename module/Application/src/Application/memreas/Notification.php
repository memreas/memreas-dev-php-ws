<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

use \Exception;

class Notification {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $receiverIds;
	protected $friends;
	protected $message;
	protected $gcm;
	protected $apns;
	protected $type;
	protected $event_id;
	protected $event_name;
	protected $media_id;
	protected $fb;
	protected $session;
	protected $twitter;
	protected $device_id;
	public function __construct($service_locator) {
		$config = $service_locator->get ( 'Config' );
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		if (! $this->gcm) {
			$this->gcm = new gcm ( $service_locator );
		}
		if (! $this->apns) {
			$this->apns = new apns ( $service_locator );
		}
	}
	public function add($receiver) {
		Mlog::addone(__CLASS__.__METHOD__.__LINE__,"Notification add receiver-->" . $receiver );
		$this->receiverIds [] = $receiver;
		return true;
	}
	public function send() {
		// error_log('file--->'. __FILE__ . ' method -->'. __METHOD__ . ' line number::' . __LINE__ . PHP_EOL);
		try {
			Mlog::addone(__CLASS__.__METHOD__.__LINE__,"Notification::Inside send()" );
			// mobile notification.
			if (count ( $this->receiverIds ) > 0) {
				Mlog::addone(__CLASS__.__METHOD__.__LINE__,"Notification::Inside send() count ( this->receiverIds ) " . count ( $this->receiverIds ) );
				
				/*
				 * Find the device tokens for receivers by user_id
				 */
				$qb = $this->dbAdapter->createQueryBuilder ();
				$qb->select ( 'd' );
				$qb->from ( 'Application\Entity\Device', 'd' );
				$qb->andWhere ( 'd.user_id IN (:x)' )->setParameter ( 'x', $this->receiverIds );
				$qb->andWhere ( 'd.last_used = 1' );
				$devices = $qb->getQuery ()->getArrayResult ();
				Mlog::addone(__CLASS__.__METHOD__.__LINE__,'$qb--->' . $qb );
				
				foreach ( $devices as $device ) {
					Mlog::addone(__CLASS__.__METHOD__.__LINE__,"device_id->" . $device ['device_id'] . "::user_id->" . $device ['user_id'] . "::device_token->" . $device ['device_token'] . "::device_type->" . $device ['device_type'] );
					if ($device ['device_type'] == \Application\Entity\Device::ANDROID) {
						//
						// Android GCM
						//
						Mlog::addone(__CLASS__.__METHOD__.__LINE__,"Notification::Inside send()->adding to Android list" );
						$this->gcm->addDevice ( $device ['device_token'] );
					} else if ($device ['device_type'] == \Application\Entity\Device::IOS) {
						//
						// Apple APNS
						//
						Mlog::addone(__CLASS__.__METHOD__.__LINE__,"Notification::Inside send()->adding to Apple list" );
						$this->apns->addDevice ( $device ['device_token'] );
					}
					$gcm_push_notification_result = '';
					if ($this->gcm->getDeviceCount () > 0) {
						$push_notification_result = $this->gcm->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
						Mlog::addone(__CLASS__.__METHOD__.__LINE__,'SENDING-ANROID' . print_r ( $gcm_push_notification_result, true ) );
					}
					$ios_push_notification_result = '';
					if ($this->apns->getDeviceCount () > 0) {
						$ios_push_notification_result = $this->apns->sendpush ( $this->message, $this->type, $this->event_id, $this->media_id );
						Mlog::addone(__CLASS__.__METHOD__.__LINE__,'SENDING-Apple' . print_r ( $ios_push_notification_result, true ) );
					}
				}
			}
		} catch ( \Exception $exc ) {
			Mlog::addone(__CLASS__.__METHOD__.__LINE__,'exp-notifcation class' . $exc->getMessage () );
		}
		return true;
	}
	// public function setMessage($notification_type, $data = '') {
	// switch ($notification_type) {
	// case \Application\Entity\Notification::ADD_FRIEND :
	// $this->message = "friend request from ".$data;
	// break;
	// case \Application\Entity\Notification::ADD_FRIEND_TO_EVENT :
	// $this->message = "add friend to ".$data;
	// break;
	// case \Application\Entity\Notification::ADD_MEDIA :
	// $this->message = "add media to ".$data;
	// break;
	// case \Application\Entity\Notification::ADD_COMMENT :
	// $this->message = "add comment to ".$data;
	// break;
	// case \Application\Entity\Notification::ADD_EVENT :
	// $this->message = "update ".$data;
	// break;
	// }
	// }
	public function setMessage($message) {
		$this->message = $message;
	}
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}

?>
