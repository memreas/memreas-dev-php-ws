<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Friend
 *
 * @ORM\Table(name="friend")
 * @ORM\Entity
 */
class Friend
{
    /**
     * @var string
     *
     * @ORM\Column(name="friend_id", type="string", length=255, nullable=false)
     * @ORM\Id
     
     */
    private $friendId;

    /**
     * @var string
     *
     * @ORM\Column(name="network", type="string", length=255, nullable=false)
     */
    private $network;

    /**
     * @var string
     *
     * @ORM\Column(name="social_username", type="string", length=255, nullable=false)
     */
    private $socialUsername;

    /**
     * @var string
     *
     * @ORM\Column(name="url_image", type="string", length=255, nullable=false)
     */
    private $urlImage;

    /**
     * @var string
     *
     * @ORM\Column(name="create_date", type="string", length=255, nullable=false)
     */
    private $createDate;

    /**
     * @var string
     *
     * @ORM\Column(name="update_date", type="string", length=255, nullable=false)
     */
    private $updateDate;

	  public function __set($name, $value) {

    $this->$name = $value;
  }

  public function __get($name) {
    
    return $this->$name;
  }


}
