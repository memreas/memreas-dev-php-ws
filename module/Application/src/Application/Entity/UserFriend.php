<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UserFriend
 *
 * @ORM\Table(name="user_friend")
 * @ORM\Entity
 */
class UserFriend
{
    /**
     * @var string
     *
     * @ORM\Column(name="user_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $userId;

    /**
     * @var string
     *
     * @ORM\Column(name="friend_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $friendId;

	  public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }

}
