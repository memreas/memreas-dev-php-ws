<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Application\memreas\Mlog;
use Application\memreas\AWSMemreasRedisCache;
use Application\memreas\ViewEvents;
use Application\memreas\MemreasTables;

class PublicController extends AbstractActionController {
	protected $redis;
	protected $sm;
	public function __construct($sm) {
		$this->sm = $sm;
	}
	public function indexAction() {
		$cm = __CLASS__ . __METHOD__;
		$this->redis = new AWSMemreasRedisCache ( $this->sm );
		$cache_me = false;
		
		Mlog::addone ( $cm . __LINE__, '...' );
		
		//
		// Start capture so we control what is sent back...
		//
		ob_start ();
		
		//
		// Fetch input xml from guzzle...
		//
		Mlog::addone ( $cm . __LINE__ . '::IndexController $_REQUEST', $_REQUEST );
		// assuming xml if not json
		$data = simplexml_load_string ( $_POST ['xml'] );
		$actionname = isset ( $_REQUEST ["action"] ) ? $_REQUEST ["action"] : '';
		
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
		Mlog::addone ( $cm . __LINE__ . '::Public Controller Starting process for $actionname-->', $actionname );
		Mlog::addone ( $cm . __LINE__ . '::Public Controller Starting process for $data-->', $data );
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
		
		/*
		 * - Cache Approach:
		 * Check cache first if not there then
		 * fetch and cache...
		 */
		if (! empty ( $data->viewevent->is_public_event ) && ! empty ( $data->viewevent->tag )) {
			Mlog::addone ( $cm . __LINE__ . '$data->viewevent->type', $data->viewevent->tag . $data->viewevent->name );
			$cache_id = "public_" . $data->viewevent->tag . $data->viewevent->name;
		} else if (! empty ( $data->viewevent->public_page )) {
			$cache_id = "public";
		}
		Mlog::addone ( $cm . __LINE__ . '$this->redis->getCache ( $actionname_$cache_id )-->', $actionname . '_' . $cache_id );
		$result = $this->redis->getCache ( $actionname . '_' . $cache_id );
		Mlog::addone ( $cm . __LINE__ . '$this->redis->getCache ( $actionname_$cache_id )-->$result', $result );
		
		if (! $result || empty ( $result )) {
			$memreas_tables = new MemreasTables ( $this->sm );
			// Mlog::addone ( $cm . __LINE__, 'COULD NOT FIND REDIS viewevents::$this->redis->getCache ( $actionname . _ . $cache_id ) for ---->' . $actionname . '_' . $cache_id );
			$viewevents = new ViewEvents ( null, $memreas_tables, $this->sm );
			$result = $viewevents->exec ();
			$cache_me = true;
		} else {
			echo $result;
		}
		
		//
		// - fetch buffer and clean
		//
		$output = trim ( ob_get_clean () );
		
		//
		// Cache
		//
		if ($cache_me) {
			$result = $this->redis->setCache ( $actionname . '_' . $cache_id, $output );
		}
		
		//
		// Send back output as xml for guzzle
		//
		if (! empty ( $callback )) {
			$json_arr = array (
					"data" => $output 
			);
			$json = json_encode ( $json_arr );
			
			header ( 'Content-Type: application/json' );
			// callback json
			Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "response for $actionname with callback--->", $callback . "(" . $json . ")" );
			echo $callback . "(" . $json . ")";
		}
		echo $output;
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
		Mlog::addone ( $cm . __LINE__ . '::Public Controller End process for $actionname-->', $actionname );
		Mlog::addone ( $cm . __LINE__, '*********************************************' );
	}
}
