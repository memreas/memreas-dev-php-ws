<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Device
 *
 * @ORM\Table(name="device")
 * @ORM\Entity
 */
class Device {
	/**
	 *
	 * @var string @ORM\Column(name="device_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 */
	public $device_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	public $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="device_token", type="string", length=255, nullable=false)
	 */
	public $device_token;
	
	/**
	 *
	 * @var string @ORM\Column(name="device_type", type="string", length=10, nullable=false)
	 */
	public $device_type;
	
	/**
	 *
	 * @var string @ORM\Column(name="last_used", type="string", length=1, nullable=false)
	 */
	public $last_used;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_time", type="string", length=255, nullable=false)
	 */
	public $create_time;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_time", type="string", length=255, nullable=false)
	 */
	public $update_time;

	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}
	