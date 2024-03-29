<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A notification.
 *
 * @ORM\Entity
 * @ORM\Table(name="notifications")
 */
class Notification {
	const ADD_FRIEND = 'ADD_FRIEND'; // 1
	const ADD_FRIEND_TO_EVENT = 'ADD_FRIEND_TO_EVENT'; // 2
	const ADD_COMMENT = 'ADD_COMMENT'; // 3
	const ADD_MEDIA = 'ADD_MEDIA'; // 4
	const ADD_EVENT = 'ADD_EVENT'; // 5
	const ADD_FRIEND_RESPONSE = 'ADD_FRIEND_RESPONSE'; // 6
	const ADD_FRIEND_TO_EVENT_RESPONSE = 'ADD_FRIEND_TO_EVENT_RESPONSE'; // 7
	const ERROR = 'ERROR'; // 8
	const EMAIL = 'EMAIL'; // 0
	const MEMREAS = 'MEMREAS'; // 1
	const SMS = 'SMS'; // 2
	
	/**
	 *
	 * @var string @ORM\Column(name="notification_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $notification_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="sender_uid", type="string", length=45, nullable=false)
	 */
	private $sender_uid;
	
	/**
	 *
	 * @var string @ORM\Column(name="receiver_uid", type="string", length=45, nullable=false)
	 */
	private $receiver_uid;
	
	/**
	 *
	 * @var string @ORM\Column(name="notification_type", type="string", length=20, nullable=false)
	 */
	private $notification_type;
	
	/**
	 *
	 * @var string @ORM\Column(name="meta", type="text", nullable=false)
	 */
	private $meta;
	
	/**
	 *
	 * @var string @ORM\Column(name="status", type="string", length=20, nullable=false)
	 */
	private $status;
	
	/**
	 *
	 * @var string @ORM\Column(name="response_status", type="string", length=20, nullable=false)
	 */
	private $response_status;
	
	/**
	 *
	 * @var string @ORM\Column(name="is_read", type="integer", nullable=false)
	 */
	private $is_read = 0;
	
	/**
	 *
	 * @var string @ORM\Column(name="notification_methods", type="string", length=20, nullable=false)
	 */
	private $notification_methods;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_time", type="string", length=255, nullable=false)
	 */
	private $create_time;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_time", type="string", length=255, nullable=false)
	 */
	private $update_time;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}