<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * EventGroup
 *
 * @ORM\Table(name="event_group")
 * @ORM\Entity
 */
class EventGroup {
	/**
	 *
	 * @var string @ORM\Column(name="event_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $event_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="group_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $group_id;
}
