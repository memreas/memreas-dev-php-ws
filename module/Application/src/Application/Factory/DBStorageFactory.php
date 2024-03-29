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
 * Copyright (c) 2013, Will Hattingh <w.hattingh@nitecon.com>
 All rights reserved.
 
 Redistribution and use in source and binary forms, with or without
 modification, are permitted provided that the following conditions are met:
 * Redistributions of source code must retain the above copyright
 notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
 notice, this list of conditions and the following disclaimer in the
 documentation and/or other materials provided with the distribution.
 * Neither the name of the Zf2LdapAuth nor the
 names of its contributors may be used to endorse or promote products
 derived from this software without specific prior written permission.
 
 THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 
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
