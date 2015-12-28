<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A Copyright.
 *
 * @ORM\Entity
 * @ORM\Table(name="copyright")
 */
class Copyright {
	
	/**
	 *
	 * @var string @ORM\Id
	 *      @ORM\Column(type="string",name="copyright_id");
	 */
	protected $copyright_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="copyright_batch_id", type="string", length=255, nullable=false)
	 */
	protected $copyright_batch_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	protected $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="media_id", type="string", length=255, nullable=false)
	 */
	protected $media_id;
	
	/**
	 *
	 * @var boolean @ORM\Column(name="validated", type="boolean", nullable=false)
	 */
	protected $validated = false;
	
	/**
	 *
	 * @var string @ORM\Column(name="metadata", type="text", nullable=false)
	 */
	protected $metadata;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_date", type="string", length=255, nullable=false)
	 */
	protected $create_date;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_time", type="string", length=255, nullable=false)
	 */
	protected $update_time;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}