<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class PublicController extends AbstractActionController {
	public function indexAction() {
		$cm = __CLASS__ . __METHOD__;
		// Start capture so we control what is sent back...
		ob_start ();
		
		$callback = isset ( $_REQUEST ['callback'] ) ? $_REQUEST ['callback'] : '';
		Mlog::addone ( $cm . __LINE__ . '::IndexController $_REQUEST', $_REQUEST );
		if (isset ( $_REQUEST ['json'] )) {
			// Handle JSon
			$reqArr = json_decode ( $_REQUEST ['json'], true );
			$actionname = $_REQUEST ['action'] != 'ws_tester' ? $_REQUEST ['action'] : $reqArr ['action'];
			$type = $reqArr ['type'];
			$data = $message_data = $reqArr ['json'];
				
			if (isset ( $message_data ['xml'] )) {
				$_POST ['xml'] = $message_data ['xml'];
				$data = simplexml_load_string ( $message_data ['xml'] );
			} else {
				$data = ( object ) $data;
			}
		} else {
			// assuming xml if not json
			$data = simplexml_load_string ( $_POST ['xml'] );
			$actionname = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
			// dont remove just to be safe relying on $_POST data
			$message_data ['xml'] = '';
		}
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
		Mlog::addone ( $cm . __LINE__ . '::Public Controller Starting process for $actionname-->', $actionname );
		Mlog::addone ( $cm . __LINE__ . '::Public Controller Starting process for $data-->', $data );
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
		
		/*
		 * - Cache Approach:
		 * Check cache first if not there then
		 * fetch and cache...
		 */
		if (! empty ( $data->viewevent->public_page )) {
			$cache_id = "public";
		} else if (! empty ( $data->viewevent->public_page ) && ! empty ( $data->viewevent->public_person ) ) {
			$user_id =  $data->viewevent->user_id;
			$cache_id = "public_" . $user_id;
		} else if (! empty ( $data->viewevent->public_page ) && ! empty ( $data->viewevent->public_memreas ) ) {
			$event_id =  $data->viewevent->memreas;
			$cache_id = "public_" . $event_id;
		}
		Mlog::addone ( $cm . __LINE__ . '$this->redis->getCache ( $actionname_$cache_id )-->', $actionname . '_' . $cache_id );
		$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
		
		if (! $result || empty ( $result )) {
			// Mlog::addone ( $cm . __LINE__, 'COULD NOT FIND REDIS viewevents::$this->redis->getCache ( $actionname . _ . $cache_id ) for ---->' . $actionname . '_' . $cache_id );
			$viewevents = new ViewEvents ( $message_data, $memreas_tables, $this->sm );
			$result = $viewevents->exec ();
			$cache_me = true;
			Mlog::addone ( $cm . __LINE__ . '::' . $actionname . '_$cache_me', 'true' );
		} else {
			echo $result;
		}
		
		
		
		
		
	}
}
