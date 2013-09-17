<?php
namespace memreas;
use Zend\Session\Container;

use Application\Model\MemreasConstants;

class UUID {

	protected $service_locator;
	protected $dbAdapter;

	public function __construct($service_locator) {
	   //$this->dbAdapter = $service_locator->get(MEMREASDB);
	   //$this->service_locator = $service_locator;
	   $this->dbAdapter = $this->service_locator->get('doctrine.entitymanager.orm_default');

	}

	public static function getUUID($dbAdapter)
	{
        $conn =  $dbAdapter->getConnection();
        $sql = 'SELECT ' . $conn->getDatabasePlatform()->getGuidExpression();
         $uuid =$conn->query($sql)->fetchColumn(0); 
        error_log("row -----> " . print_r($uuid, true) . PHP_EOL );
        return  $uuid;
		//$sql="SELECT UUID()";
			
	}

}
?>
