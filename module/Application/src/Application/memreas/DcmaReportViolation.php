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
use Application\memreas\AWSManagerSender;
use Application\memreas\MemreasSignedURL;

use Zend\View\Renderer\PhpRenderer;
use Zend\View\Model\ViewModel;
use \Exception;

class DcmaReportViolation {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
                $this->url_signer = new MemreasSignedURL ();
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$stausMessage = $violation_id = '';
		$media_id = trim ( $data->dcmareportviolation->media_id );
		$user_id = trim ( $data->dcmareportviolation->user_id );
		$name = trim ( $data->dcmareportviolation->copyright_owner_name );
		$address = trim ( $data->dcmareportviolation->copyright_owner_address );
                $email = trim ( $data->dcmareportviolation->copyright_owner_email_address);
                $meta = trim ( $data->dcmareportviolation->meta);
                $claim_status = MemreasConstants::DCMA_CLAIM;
                $time = time ();
		$message = '';
                
		if (empty ( $media_id )) {
			$message = 'Media Not Found';
			$status = 'Failure';
                } else {
                    $mediaObj = $this->dbAdapter->getRepository ( "\Application\Entity\Media" )
                            ->findOneBy ( array ('media_id' => $media_id ) );
                        if(empty($mediaObj)){
                            $message = 'Media Not Found';
                            $status = 'Failure';
                        }
                }
                    
                    if (! $this->is_valid_email ( $email )) {
			$message .= 'Please enter valid email address. ';
			$status = 'Failure';
                    }  
            

		if($status != 'Failure') {
			// add Violation
			$violation_id = MUUID::fetchUUID ();
			$tblDcmaViolation = new \Application\Entity\DcmaViolation();
                        $tblDcmaViolation->violation_id = $violation_id;
			$tblDcmaViolation->media_id = $media_id;
			$tblDcmaViolation->user_id = $mediaObj->user_id;
			
			$tblDcmaViolation->copyright_owner_name = $name;
			$tblDcmaViolation->copyright_owner_address = $address;
                        $tblDcmaViolation->copyright_owner_email_address = $email;
			$tblDcmaViolation->meta = $meta;
                        $tblDcmaViolation->status = MemreasConstants::DCMA_CLAIM;
                        $tblDcmaViolation->dmca_violation_report_date = $time;
                       
			$tblDcmaViolation->create_time = $time;
			$this->dbAdapter->persist ( $tblDcmaViolation );
			$this->dbAdapter->flush ();
			$message .= 'Dcma Voilation saved ';
			$status = 'success';
                        /*
                         *  Updates the media table
                         */
                        
                        $mediaObj->report_flag = MemreasConstants::DCMA_CLAIM;
			$this->dbAdapter->persist ( $mediaObj );
			$this->dbAdapter->flush ();
				
                        /*
                         * site user will recive mail
                         */
                        
                        $mediaUser = $this->dbAdapter->getRepository ( "\Application\Entity\User" )
                            ->findOneBy ( array ('user_id' => $mediaObj->user_id ) );
                        
                        $to [0] = $mediaUser->email_address;
                        $viewVar = array (
                                        'email' => $mediaUser->email_address,
                                        'username' => $mediaUser->username,
                                        'media_url' => $this->getMediaUrl($mediaObj)
                        );
                        $viewModel = new ViewModel ( $viewVar );
                        $viewModel->setTemplate ( 'email/dcma-user' );
                        $viewRender = $this->service_locator->get ( 'ViewRenderer' );
                        $html = $viewRender->render ( $viewModel );
                        $subject = 'DMCA violation claim';
                        if (empty ( $aws_manager ))
                            $aws_manager = new AWSManagerSender ( $this->service_locator );
                        if (MemreasConstants::SEND_EMAIL) {
                            $aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
                        }
                        /*
                         * reporter will recive mail
                         */
                        $to [0] = $email;
                        $viewVar = array (
                                        'email' => $email,
                                        'reporter' => $name,
                                        'media_url' => $this->getMediaUrl($mediaObj)
                        );
                        $viewModel = new ViewModel ( $viewVar );
                        $viewModel->setTemplate ( 'email/dcma-reporter' );
                        $viewRender = $this->service_locator->get ( 'ViewRenderer' );
                        $html = $viewRender->render ( $viewModel );
                        $subject = 'DMCA violation claim';
                        if (empty ( $aws_manager ))
                            $aws_manager = new AWSManagerSender ( $this->service_locator );
                        if (MemreasConstants::SEND_EMAIL) {
                            $aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
                        }
		}
		
		if (empty ( $frmweb )) {
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<dcmareportviolationresult>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>" . $message . "</message>";
			$xml_output .= "<voilation_id>$voilation_id</voilation_id>";
                        
			
			$xml_output .= "</dcmareportviolationresult>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
	}
	public function is_valid_email($email) {
		$result = TRUE;
		if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
			$result = FALSE;
		}
		return $result;
	}
        public function getMediaUrl($media) {
            $json_array = json_decode ( $media->metadata, true );
            return $this->url_signer->signArrayOfUrls ( $json_array ['S3_files'] ['path'] );					
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
