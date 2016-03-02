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

class DcmaCounterClaim {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($service_locator) {
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
                $this->url_signer = new MemreasSignedURL();
	}
	public function exec($frmweb = '') {
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$stausMessage = $violation_id = '';
                $violation_id = trim ( $data->dcmacounterclaim->violation_id );
		$media_id = trim ( $data->dcmacounterclaim->media_id );
		$counter_url = trim ( $data->dcmacounterclaim->counter_claim_url );
		$counter_address = trim ( $data->dcmacounterclaim->counter_claim_address );
		$counter_email = trim ( $data->dcmacounterclaim->counter_claim_email_address );
                $agreedTerm = trim ( $data->dcmareportviolation->counter_claim_agreed_to_terms);
                $counter_phone = trim ( $data->dcmareportviolation->counter_claim_phone_number);

                $time = time ();
		$message = '';
                 $status = 'Success';
                
                if(empty ( $agreedTerm )){
                    $message = 'Pleae aceept terms ';
			$status = 'Failure';
                }elseif(empty ( $violation_id )){
                    $message = 'Violation record not found';
		    $status = 'Failure';
                } else {
                    $violationObj = $this->dbAdapter->getRepository ( "\Application\Entity\DcmaViolation" )
                                                ->findOneBy ( array ('violation_id' => $violation_id ) );
                    $mediaObj = $this->dbAdapter->getRepository ( "\Application\Entity\Media" )
                                                ->findOneBy ( array ('media_id' => $violationObj->media_id ) );
                        if(empty($mediaObj)){
                            $message = 'Media Not Found';
                            $status = 'Failure';
                        }
                }
                    
                    if (! $this->is_valid_email ( $counter_address )) {
			$message .= 'Please enter valid email address. ';
			$status = 'Failure';
                    }  
            

		if($status != 'Failure') {
			// add Violation
   			$violationObj->counter_claim_url = $counter_url;
                        $violationObj->counter_claim_address = $counter_address;
			$violationObj->counter_claim_email_address = $counter_email;
			$violationObj->counter_claim_report_date = $time;			 
                        $violationObj->counter_claim_phone_number = $counter_phone;
                        $violationObj->status = MemreasConstants::DCMA_COUNTER_CLAIM;
                        $violationObj->update_date = $time;
			$this->dbAdapter->persist ( $violationObj );
			$this->dbAdapter->flush ();
			$message .= 'Dcma counter Claim ';
			$status = 'success';
                        /*
                         *  Updates the media table
                         */
                        
                        $mediaObj->report_flag = MemreasConstants::DCMA_COUNTER_CLAIM;
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
                                        'media_url' => $this->getMediaUrl($mediaObj),
                                        'claimant_email_address' => $violationObj->copyright_owner_email_address
                                    );
                        $viewModel = new ViewModel ( $viewVar );
                        $viewModel->setTemplate ( 'email/dcma-counter-user' );
                        $viewRender = $this->service_locator->get ( 'ViewRenderer' );
                        $html = $viewRender->render ( $viewModel );
                        $subject = ' Dcma counter Claim';
                        if (empty ( $aws_manager ))
                            $aws_manager = new AWSManagerSender ( $this->service_locator );
                        if (MemreasConstants::SEND_EMAIL) {
                            $aws_manager->sendSeSMail ( $to, $subject, $html ); // Active this line when app go live
                        }
                        
                        /*
                         * reporter will recive mail$violationObj->copyright_owner_email_address
                         */
                        $to [0] = $violationObj->copyright_owner_email_address;
                        $viewVar = array (
                                        'reporter' => $violationObj->copyright_owner_name,
                                        'media_url' => $this->getMediaUrl($mediaObj),
                                        'user_email_address' => $counter_address
                        );
                        $viewModel = new ViewModel ( $viewVar );
                        $viewModel->setTemplate ( 'email/dcma-counter-reporter' );
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
