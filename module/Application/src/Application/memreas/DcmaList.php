<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use Application\memreas\MemreasSignedURL;
use Application\memreas\AWSManagerSender;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Model\ViewModel;
use \Exception;

class DcmaList {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($service_locator) {
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        $this->url_signer = new MemreasSignedURL();
    }

    public function exec($frmweb = '') {
        if (empty($frmweb)) {
            $data = simplexml_load_string($_POST ['xml']);
        } else {
            $data = json_decode(json_encode($frmweb));
        }
         $status =$stausMessage = $violation_id = '';
        $user_id = trim($data->dcmalist->user_id);

        $time = time();
        $message = '';


        if (empty($user_id)) {
            $message = 'User Id required';
            $status = 'Failure';
        }


        if ($status != 'Failure') {
             
             
            $qb = $this->dbAdapter->createQueryBuilder();
            $qb->select('dcma');
            $qb->from('Application\Entity\DcmaViolation', 'dcma');
            
            $qb->join('Application\Entity\User', 'u', 'WITH', 'u.user_id = dcma.user_id');
            $qb->where('dcma.user_id = ?1');
            $qb->orderBy('dcma.create_time', 'DESC');
            $qb->setParameter(1, $user_id);
             
            $result = $qb->getQuery()->getArrayResult();
            $dcmalist='';
            if(empty($result )){
                $status = 'Failure';
                $message='No record found';
            }else{
               
              $status = 'Success';
                 foreach ($result as $rec) {
                     error_log('dcma-->'.print_r($rec,true));
                     $media_url = $this->getMediaUrl($rec['media_id']);
                     $dcmalist .= "<media>";
                    $dcmalist .= "<violation_id>{$rec['violation_id']}<violation_id>";
                     
                    $dcmalist .= "<user_id>{$rec['user_id']}</user_id>";
                    $dcmalist .= "<media_id>{$rec['media_id']}</media_id>";
                     $dcmalist .= "<media_url>{$media_url}</media_url>";
                     $dcmalist .= "<copyright_owner_name>{$rec['copyright_owner_name']}</copyright_owner_name>";
                    $dcmalist .= "<copyright_owner_address>{$rec['copyright_owner_address']}</copyright_owner_address>";
                    $dcmalist .= "<copyright_owner_email_address>{$rec['copyright_owner_email_address']}</copyright_owner_email_address>";
                    $dcmalist .= "<dmca_violation_report_date>{$rec['dmca_violation_report_date']}</dmca_violation_report_date>";
                    $dcmalist .= "<meta>{$rec['meta']}</meta>";
                    $dcmalist .= "<status>{$rec['status']}</status>";
                    $dcmalist .= "<counter_claim_url>{$rec['counter_claim_url']}</counter_claim_url>";
                    $dcmalist .= "<counter_claim_name>{$rec['counter_claim_name']}</counter_claim_name>";
                    $dcmalist .= "<counter_claim_address>{$rec['counter_claim_address']}</counter_claim_address>";
                    $dcmalist .= "<counter_claim_email_address>{$rec['counter_claim_email_address']}</counter_claim_email_address>";
                    $dcmalist .= "<counter_claim_report_date>{$rec['counter_claim_report_date']}</counter_claim_report_date>";

                   
                   
                   $dcmalist .= "<media>";
                }
                
            }
            if (empty($frmweb)) {
                header("Content-type: text/xml");
                $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
                $xml_output .= "<xml>";
                $xml_output .= "<dcmalistresult>";
                $xml_output .= "<status>$status</status>";
                $xml_output .= "<message>" . $message . "</message>";
                $xml_output .= $dcmalist;
                $xml_output .= "</dcmalistresult>";
                $xml_output .= "</xml>";
                echo $xml_output;
            }
        }
    }

    public function is_valid_email($email) {
        $result = TRUE;
        if (!preg_match('/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email)) {
            $result = FALSE;
        }
        return $result;
    }

    public function getMediaUrl($media_id) {
        $mediaObj = $this->dbAdapter->getRepository ( "\Application\Entity\Media" )->findOneBy ( array (
					'media_id' => $media_id 
			) );
        $json_array = json_decode($mediaObj->metadata, true);
        return $this->url_signer->signArrayOfUrls($json_array ['S3_files'] ['path']);
    }

}

/*
 * 
  <dcmareportviolation>
  <violation_id><violation_id>
  <user_id></user_id>
  <media_id></media_id>
  <copyright_owner_name></copyright_owner_name>
  <copyright_owner_address></copyright_owner_address>
  <copyright_owner_email_address></copyright_owner_email_address>
  <copyright_owner_agree_provide_email></copyright_owner_agree_provide_email>
  <dmca_violation_report_date></dmca_violation_report_date>
  <meta></meta>
  <status></status>
  <counter_claim_url></counter_claim_url>
  <counter_claim_name></counter_claim_name>
  <counter_claim_address></counter_claim_address>
  <counter_claim_email_address></counter_claim_email_address>
  <counter_claim_report_date></counter_claim_report_date>
  </dcmareportviolation>
 */
?>
