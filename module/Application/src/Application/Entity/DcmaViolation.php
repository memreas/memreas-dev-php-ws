<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * A music album.
 *
 * @ORM\Entity
 * @ORM\Table(name="dmca_violation")
 */
class DcmaViolation {
	
	/**
	 *
	 * @var string @ORM\Column(name="violation_id", type="string", length=255, nullable=false)
	 *      @ORM\Id
	 *      @ORM\GeneratedValue(strategy="NONE")
	 */
	private $violation_id;
	
	/**
	 *
	 * @var string @ORM\Column(name="user_id", type="string", length=255, nullable=false)
	 */
	private $user_id;
	
        /**
	 *
	 * @var string @ORM\Column(name="media_id", type="string", length=255, nullable=false)
	 */
	private $media_id;
	/**
	 *
	 * @var string @ORM\Column(name="copyright_owner_name", type="string", length=255, nullable=false)
	 */
	private $copyright_owner_name;
	
	/**
	 *
	 * @var string @ORM\Column(name="copyright_owner_address", type="string", length=255, nullable=false)
	 */
	private $copyright_owner_address;
        /**
	 *
	 * @var string @ORM\Column(name="copyright_owner_email_address", type="string", length=255, nullable=false)
	 */
	private $copyright_owner_email_address;
         /**
	 *
	 * @var string @ORM\Column(name="status", type="string", length=255, nullable=false)
	 */
	private $status;
	
        
	
	/**
	 *
	 * @var string @ORM\Column(name="dmca_violation_report_date", type="string", length=255, nullable=false)
	 */
	private $dmca_violation_report_date;
        /**
	 *
	 * @var string @ORM\Column(name="meta", type="string",  nullable=false)
	 */
	private $meta;
	
	/**
	 *
	 * @var string @ORM\Column(name="counter_claim_url", type="string", nullable=false)
	 */
	private $counter_claim_url='';
        
        /**
	 *
	 * @var string @ORM\Column(name="counter_claim_name", type="string", nullable=false)
	 */
	private $counter_claim_name;
        /**
	 *
	 * @var string @ORM\Column(name="counter_claim_address", type="string", nullable=false)
	 */
	private $counter_claim_address;
        /**
	 *
	 * @var string @ORM\Column(name="counter_claim_email_address", type="string", nullable=false)
	 */
	private $counter_claim_email_address;
        /**
	 *
	 * @var string @ORM\Column(name="counter_claim_report_date ", type="string", nullable=false)
	 */
	private $counter_claim_report_date;
        
         /**
	 *
	 * @var string @ORM\Column(name="copyright_owner_agreed_to_terms", type="string", length=255, nullable=false)
	 */
	private $copyright_owner_agreed_to_terms;
        
        
         /**
	 *
	 * @var string @ORM\Column(name="counter_claim_agreed_to_terms", type="string", length=255, nullable=false)
	 */
	private $counter_claim_agreed_to_terms;
        
        /**
	 *
	 * @var string @ORM\Column(name="create_time", type="string", nullable=false)
	 */
	private $create_time;
        /**
	 *
	 * @var string @ORM\Column(name="update_time", type="string", nullable=false)
	 */
	private $update_time='';
        
        public function __set($name, $value) {
		$this->$name = $value;
	}
	public function __get($name) {
		return $this->$name;
	}
}