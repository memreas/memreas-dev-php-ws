<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
/*
 * Check if media exist or not
 * @params: user_id, media_id
 * @Return Falure if media existed and Success if has no
 */
namespace Application\memreas;

use Application\Entity\Media;

class CheckExistMedia {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	
	/*
	 *
	 */
	public function exec($frmweb = false, $output = '') {
		$error_flag = 0;
		$message = '';
		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {
			$data = json_decode ( json_encode ( $frmweb ) );
		}
		$user_id = $data->checkexistmedia->user_id;
		$media_name = $data->checkexistmedia->filename;
		
		$query = $this->dbAdapter->createQueryBuilder ();
		$query->select ( 'm.metadata' )->from ( 'Application\Entity\Media', 'm' )->where ( 'm.user_id = ?1' )->andWhere ( 'm.transcode_status = ?2' )->andWhere ( 'm.delete_flag != ?3' )->setParameter ( 1, $user_id )->setParameter ( 2, 'success' )->setParameter ( 3, '1' );
		$result = $query->getQuery ()->getResult ();
		//Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '::$result-->', $query->getQuery ()->getSql () );
		if (! empty ( $result )) {
			$pass = true;
			foreach ( $result as $media ) {
				$metadata = json_decode ( $media ['metadata'], true );
				$serverFileName = explode ( "/", $metadata ['S3_files'] ['path'] );
				$serverFileName = $serverFileName [count ( $serverFileName ) - 1];
				if ($serverFileName == $media_name) {
					$pass = false;
					break;
				}
			}
			if ($pass)
				$status = 'Success';
			else
				$status = 'Failure';
		} else
			$status = 'Success';
		
		if ($frmweb) {
			return $output;
		}
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<checkexistmediaresponse>";
		$xml_output .= "<status>" . $status . "</status>";
		if (isset ( $message ))
			$xml_output .= "<message>{$message}</message>";
		$xml_output .= $output;
		$xml_output .= "</checkexistmediaresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
