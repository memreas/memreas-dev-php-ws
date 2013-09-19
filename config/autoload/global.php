<?php

/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */
return array(
    'db' => array(
        'adapters' => array(
            'memreasdevdb' => array(
                'driver' => 'Pdo',
                'driver_options' => array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
                ),
            ),
            'memreasbackenddb' => array(
                'driver' => 'Pdo',
                'driver_options' => array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
                ),
            ),
        )
    ),
    'service_manager' => array(
//        'factories' => array(
//            'Zend\Db\Adapter\Adapter'
//                    => 'Zend\Db\Adapter\AdapterServiceFactory',
//        ),
        'abstract_factories' => array(
            'Zend\Db\Adapter\AdapterAbstractServiceFactory',
        ),
    ),
    'doctrine' => array(
        'connection' => array(
            'orm_default' => array(
                'doctrine_type_mappings' => array(
                    'enum' => 'string',
                    'bit' => 'string'
                ),
                'driverClass' => 'Doctrine\DBAL\Driver\PDOMySql\Driver',
                'params' => array(
                    'host' => 'aa15nf7gzm5gbt3.co0fw2snbu92.us-east-1.rds.amazonaws.com',
                    'port' => '3306',
                    'dbname' => 'memreasintdb',
                    'user'     => 'memreasdbuser',
					'password' => 'memreas2013',
                )
            )
        )
    ),
);
