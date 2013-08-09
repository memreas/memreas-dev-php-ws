<?php
namespace memreas;

use Application\Model\MemreasConstants;

class UUID {

	protected $service_locator;
	protected $dbAdapter;

	public function __construct($service_locator) {
	   //$this->dbAdapter = $service_locator->get(MEMREASDB);
	   //$this->service_locator = $service_locator;
	   //$this->dbAdapter = $this->service_locator->get('memreasdevdb');

	}

	public static function getUUID($dbAdapter)
	{

		$sql="SELECT UUID()";
		$statement = $dbAdapter->createStatement($sql);
		$result = $statement->execute();
		$row = $result->current();

error_log("row -----> " . print_r($row, true) . PHP_EOL );

//$row['user_id']

//if (!empty($row)) {


		$U="SELECT UUID()";
		$r=mysql_query($U);
		$guid=mysql_fetch_array($r);
		return $guid[0];
	}

}
?>
