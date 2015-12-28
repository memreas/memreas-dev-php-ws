<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * MediaDevice
 *
 * @ORM\Table(name="media_device")
 * @ORM\Entity
 */
class MediaDevice {
	
	/**
	 *
	 * @var string @ORM\Column(name="media_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $media_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=true)
	 */
	private $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="metadata", type="text", nullable=false)
	 */
	private $metadata;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_date", type="string", length=255, nullable=false)
	 */
	private $create_date;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_date", type="string", length=255, nullable=false)
	 */
	private $update_date;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}
