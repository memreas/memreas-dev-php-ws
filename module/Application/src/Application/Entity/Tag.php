<?php

namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="tag")
 * 
 */
class Tag  
{
    
    const EVENT='!';
    const PERSON='@';
    const TAG='#';

    /**
     * @var string
     *
     * @ORM\Column(name="tag_id", type="string", length=255, nullable=false)
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $tag_id;

     /**
     * @var string
     * @ORM\Id
     * @ORM\Column(name="tag", type="string", length=255, nullable=false)
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $tag;
    
      /**
     * @var string
     *
     * @ORM\Column(name="tag_type", type="string", length=1, nullable=false)
     * @ORM\GeneratedValue(strategy="NONE")
     */
    private $tag_type;

   
    /**
     * @var string
     *
     * @ORM\Column(name="meta", type="string", length=255, nullable=false)
     */
    private $meta;
    
   
    /**
     * @var string
     *
     * @ORM\Column(name="create_time", type="string", length=255, nullable=false)
     */
    private $create_time;

    /**
     * @var string
     *
     * @ORM\Column(name="update_time", type="string", length=255, nullable=false)
     */
    private $update_time;
    

    public function __set($name, $value) {
        $this->$name = $value;
    }

    public function __get($name) {
        return $this->$name;
    }


    
}