<?php

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
	 * @var string
	 *
	 * @ORM\Id
	 * @ORM\Column(type="string",name="copyright_id");
	 */
	protected $copyright_id;
	
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
	protected $validated = 0;
	
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