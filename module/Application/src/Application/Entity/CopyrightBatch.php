<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A CopyrightBatch.
 *
 * @ORM\Entity
 * @ORM\Table(name="copyright_batch")
 */
class CopyrightBatch {
	
	/**
	 *
	 * @var string @ORM\Id
	 *      @ORM\Column(type="string",name="copyright_batch_id");
	 */
	protected $copyright_batch_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	protected $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="metadata", type="text", nullable=false)
	 */
	private $metadata;
	
	/**
	 *
	 * @var boolean @ORM\Column(name="remaining", type="integer", nullable=false)
	 */
	protected $remaining;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_date", type="string", length=255, nullable=false)
	 */
	private $create_date;
	
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