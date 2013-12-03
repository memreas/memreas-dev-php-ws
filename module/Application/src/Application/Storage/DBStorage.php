<?php

/**
 * This file is part of the DBSessionStorage Module (https://github.com/Nitecon/DBSessionStorage.git)
 *
 * Copyright (c) 2013 Will Hattingh (https://github.com/Nitecon/DBSessionStorage.git)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.txt that was distributed with this source code.
 */

namespace Application\Storage;

use Zend\Db\TableGateway\TableGateway ;
use Application\Model\DbTableGatewayOptions;
use Zend\Db\Adapter\Adapter;
use Zend\Session\SessionManager;
use Zend\Session\Container;

class DBStorage
{

    protected $adapter;
    protected $tblGW;
    protected $sessionConfig;
    protected $ipaddress;

    public function __construct(Adapter $adapter, $session_config)
    {
        $this->adapter = $adapter;
        $this->sessionConfig = $session_config;
        $this->tblGW = new TableGateway('user_session', $this->adapter);
    }

    public function setSessionStorage()
    {
        $gwOpts = new DbTableGatewayOptions();
        $gwOpts->setDataColumn('data');
        $gwOpts->setIdColumn('session_id');
        $gwOpts->setLifetimeColumn('lifetime');
        $gwOpts->setModifiedColumn('end_date');
        $gwOpts->setNameColumn('name');
        $gwOpts->ipaddress = $this->ipaddress;
         
         
        $saveHandler = new \Application\Model\DbTableGateway($this->tblGW, $gwOpts);
       
        $sessionManager = new SessionManager(); 
        
        if ($this->sessionConfig) {
            $sessionConfig = new \Zend\Session\Config\SessionConfig();
            $sessionConfig->setOptions($this->sessionConfig);
            $sessionManager->setConfig($sessionConfig);
        }
        $sessionManager->setSaveHandler($saveHandler);
        Container::setDefaultManager($sessionManager);
        if(isset($_POST[session_name()])){
            $sessionManager->setId($_POST[session_name()]);
        }
        
        //$sessionManager->start();
         $container = new Container('user');
         if (!isset($container->init)) { 
            //$sessionManager->regenerateId(true);
            $container->init = 1;
        }
       
        
    }
    
   public function __set($name, $value) {

    $this->$name = $value;
  }
    
}
