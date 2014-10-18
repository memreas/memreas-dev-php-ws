<?php

/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */
namespace Application;

use Zend\Mvc\ModuleRouteListener;
use Zend\Mvc\MvcEvent;
use Zend\ModuleManager\Feature\AutoloaderProviderInterface;
use Zend\Session\Container;
use Zend\Session\Config\SessionConfig;
use Zend\Session\SessionManager;
use Zend\Authentication\Storage;
use Zend\Authentication\AuthenticationService;
use Zend\Authentication\Adapter\DbTable as DbTableAuthAdapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Application\Model\User;
use Application\Model\UserTable;
use Application\Model;
use Application\Model\MemreasConstants;

class Module {
	public function onBootstrap(MvcEvent $e) {
		$e->getApplication ()->getServiceManager ()->get ( 'translator' );
		$eventManager = $e->getApplication ()->getEventManager ();
		$serviceManager = $e->getApplication ()->getServiceManager ();
		$moduleRouteListener = new ModuleRouteListener ();
		$moduleRouteListener->attach ( $eventManager );
		$this->bootstrapSession ( $e );
		 
	}
	public function bootstrapSession($e) {
		ini_set ( 'session.use_cookies', '0' );
		
		//$storage = $e->getApplication ()->getServiceManager ()->get ( 'Application\Storage\DBStorage' );
		//$storage->setSessionStorage ();
	}
	public function getConfig() {
		return include __DIR__ . '/config/module.config.php';
	}
	public function getAutoloaderConfig() {
		return array (
				// 'Zend\Loader\ClassMapAutoloader' => array(
				// __DIR__ . '/autoload_classmap.php',
				// ),
				'Zend\Loader\StandardAutoloader' => array (
						'namespaces' => array (
								__NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
						) 
				) 
		);
	}
	public function getServiceConfig() {
		return array (
				'factories' => array (
						// ZF2 Session Setup...
						'Zend\Session\SessionManager' => function ($sm) {
							$config = $sm->get ( 'config' );
							if (isset ( $config ['session'] )) {
								$session = $config ['session'];
								
								$sessionConfig = null;
								if (isset ( $session ['config'] )) {
									$class = isset ( $session ['config'] ['class'] ) ? $session ['config'] ['class'] : 'Zend\Session\Config\SessionConfig';
									$options = isset ( $session ['config'] ['options'] ) ? $session ['config'] ['options'] : array ();
									$sessionConfig = new $class ();
									$sessionConfig->setOptions ( $options );
								}
								
								$sessionStorage = null;
								if (isset ( $session ['storage'] )) {
									$class = $session ['storage'];
									$sessionStorage = new $class ();
								}
								
								$sessionSaveHandler = null;
								if (isset ( $session ['save_handler'] )) {
									// class should be fetched from service manager since it will require constructor arguments
									$sessionSaveHandler = $sm->get ( $session ['save_handler'] );
								}
								
								$sessionManager = new SessionManager ( $sessionConfig, $sessionStorage, $sessionSaveHandler );
								if (isset ( $session ['validator'] )) {
									$chain = $sessionManager->getValidatorChain ();
									foreach ( $session ['validator'] as $validator ) {
										$validator = new $validator ();
										$chain->attach ( 'session.validate', array (
												$validator,
												'isValid' 
										) );
									}
								}
							} else {
								$sessionManager = new SessionManager ();
							}
							Container::setDefaultManager ( $sessionManager );
							return $sessionManager;
						},
						
						'Application\Model\MyAuthStorage' => function ($sm) {
							return new \Application\Model\MyAuthStorage ( 'memreas' );
						},
						'AuthService' => function ($sm) {
							// My assumption, you've alredy set dbAdapter
							// and has users table with columns : user_name and pass_word
							// that password hashed with md5
							$dbAdapter = $sm->get ( MemreasConstants::MEMREASDB );
							$dbTableAuthAdapter = new DbTableAuthAdapter ( $dbAdapter, 'user', 'username', 'password', 'MD5(?)' );
							
							$authService = new AuthenticationService ();
							$authService->setAdapter ( $dbTableAuthAdapter );
							$authService->setStorage ( $sm->get ( 'Application\Model\MyAuthStorage' ) );
							
							return $authService;
						},
						
						// Tables
						'Application\Model\UserTable' => function ($sm) {
							$tableGateway = $sm->get ( 'UserTableGateway' );
							$table = new UserTable ( $tableGateway );
							return $table;
						},
						'UserTableGateway' => function ($sm) {
							$dbAdapter = $sm->get ( MemreasConstants::MEMREASDB );
							$resultSetPrototype = new \Zend\Db\ResultSet\ResultSet ();
							$resultSetPrototype->setArrayObjectPrototype ( new User () );
							return new TableGateway ( 'user', $dbAdapter, null, $resultSetPrototype );
						} 
				)
				 
		);
	}
}
