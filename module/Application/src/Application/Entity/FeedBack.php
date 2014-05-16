<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="feedback")
 */
class FeedBack {
	 
	/**
	 *
	 * @var string @ORM\Column(name="device_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $feedback_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	private $user_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="device_token", type="string", length=255, nullable=false)
	 */
	private $name;
	
	/**
	 *
	 * @var string @ORM\Column(name="device_type", type="string", length=255, nullable=false)
	 */
	private $email;
	
	/**
	 *
	 * @var string @ORM\Column(name="create_time", type="string", length=255, nullable=false)
	 */
	private $create_time;
	
	/**
	 *
	 * @var string @ORM\Column(name="update_time", type="string",   nullable=false)
	 */
	private $message;
	public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}