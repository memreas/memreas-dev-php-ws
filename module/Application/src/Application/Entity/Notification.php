<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="notifications")
 */
class Notification {
	const ADD_FRIEND = '1';
	const ADD_FRIEND_TO_EVENT = '2';
	const ADD_COMMENT = '3';
	const ADD_MEDIA = '4';
	const ADD_EVENT = '5';
	const ADD_FRIEND_RESPONSE = '6';
	const ADD_FRIEND_TO_EVENT_RESPONSE = '7';

	const EMAIL = '0';
	const MEMREAS = '1';
	const NONMEMREAS = '2';
	
	/**
	 *
	 * @var string @ORM\Column(name="notification_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $notification_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="notification_type", type="string", length=255, nullable=false)
	 */
	private $notification_type;
	
	/**
	 *
	 * @var string @ORM\Column(name="meta", type="string", length=255, nullable=false)
	 */
	private $meta;
	
	/**
	 *
	 * @var string @ORM\Column(name="links", type="string", length=255, nullable=false)
	 */
	private $links;
	
	/**
	 *
	 * @var string @ORM\Column(name="status", type="string", length=255, nullable=false)
	 */
	private $status = 0;
	
	/**
	 *
	 * @var string @ORM\Column(name="is_read", type="string", length=1, nullable=false)
	 */
	private $is_read = 0;
	/**
	 *
	 * @var string @ORM\Column(name="short_code", type="string", length=10, nullable=false)
	 */
	private $short_code ;
	/**
	 *
	 * @var string @ORM\Column(name="notification_method", type="string", length=10, nullable=false)
	 */
	private $notification_method = 0;
	
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