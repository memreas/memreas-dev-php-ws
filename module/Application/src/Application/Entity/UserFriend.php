<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserFriend
 *
 * @ORM\Table(name="user_friend")
 * @ORM\Entity
 */
class UserFriend {
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="friend_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $friend_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_approve", type="string", length=255, nullable=false)
	 */
	protected $user_approve = 0;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}
