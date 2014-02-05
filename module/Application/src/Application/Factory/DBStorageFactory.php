<?php

/**
 * Copyright (c) 2013 Will Hattingh (https://github.com/Nitecon
 *
 * For the full copyright and license information, please view
 * the file LICENSE.txt that was distributed with this source code.
 * 
 * @author Will Hattingh <w.hattingh@nitecon.com>
 * @author https://github.com/acnb
 * 
 */
namespace Application\Factory;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Application\Storage\DBStorage;
use Application\Model\MemreasConstants;

/*
 * Contributed storage factory by community user https://github.com/acnb
 */
class DBStorageFactory implements FactoryInterface {
	public function createService(ServiceLocatorInterface $serviceLocator) {
		$conf = $serviceLocator->get ( 'Config' );
		$config = null;
		if (isset ( $conf ['zf2-db-session'] ) && isset ( $conf ['zf2-db-session'] ['sessionConfig'] )) {
			$config = $conf ['zf2-db-session'] ['sessionConfig'];
		}
		// $dbAdapter = $serviceLocator->get('\Zend\Db\Adapter\Adapter');
		$dbAdapter = $serviceLocator->get ( MemreasConstants::MEMREASDB );
		$x = new DBStorage ( $dbAdapter, $config );
		$x->ipaddress = $serviceLocator->get ( 'Request' )->getServer ( 'REMOTE_ADDR' );
		return $x;
	}
}
