<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;

class RegisterDevice {
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
	public function exec($isInternalJSON = false, $internaJSON = '') {
		try {
			error_log ( 'registerdevice.exec()' . PHP_EOL );
			if ($isInternalJSON) {
				error_log ( 'registerdevice.exec()-> internaJSON' . $internaJSON . PHP_EOL );
				$data = json_decode ( $internaJSON );
				error_log ( 'registerdevice.exec()-> data' . print_r ( $data, true ) . PHP_EOL );
			} else {
				error_log ( 'registerdevice.exec()-> _POST [xml]' . $_POST ['xml'] . PHP_EOL );
				$data = simplexml_load_string ( $_POST ['xml'] );
			}
			
			$user_id = trim ( $data->registerdevice->user_id );
			$device_id = trim ( $data->registerdevice->device_id );
			$device_token = trim ( $data->registerdevice->device_token );
			$device_type = trim ( $data->registerdevice->device_type );
			error_log ( 'registerdevice.exec()->user_id' . $user_id . PHP_EOL );
			error_log ( 'registerdevice.exec()->device_id' . $device_id . PHP_EOL );
			error_log ( 'registerdevice.exec()->device_token' . $device_token . PHP_EOL );
			error_log ( 'registerdevice.exec()->device_type' . $device_type . PHP_EOL );
			
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registerdeviceresponse>";
			$time = time ();
			if (! empty ( $user_id ) && ! empty ( $device_token ) && ! empty ( $device_type )) {

				/*
				 * Lookup device and insert/update based on last login...
				 */
				$device_sql = "SELECT d FROM Application\Entity\Device d" . "  where  d.device_id = '$device_id'" . "  and d.device_type = '$device_type'";
				$device_query = $this->dbAdapter->createQuery ( $device_sql );
				$device_exists = $device_query->getOneOrNullResult ();
				
				if (! $device_exists) {
					error_log ( 'registerdevice.exec()->inside update' . PHP_EOL );
					$tblDevice = new \Application\Entity\Device ();
					$tblDevice->device_id = $device_id;
					$tblDevice->device_token = $device_token;
					$tblDevice->user_id = $user_id;
					$tblDevice->device_type = $device_type;
					$tblDevice->create_time = $time;
					$tblDevice->update_time = $time;
					$this->dbAdapter->persist ( $tblDevice );
					$this->dbAdapter->flush ();
					error_log ( 'registerdevice.exec()->executed updated' . PHP_EOL );
				} else {
					error_log ( 'registerdevice.exec()->inside update' . PHP_EOL );
					// device exists so update data based on last login/registration...
					$qb = $this->dbAdapter->createQueryBuilder ();
					$q = $qb->update ( '\Application\Entity\Device', 'd' )->set ( 'd.user_id', $qb->expr ()->literal ( $user_id ) )->set ( 'd.device_token', $qb->expr ()->literal ( $device_token ) )->set ( 'd.update_time', $qb->expr ()->literal ( $time ) )->where ( 'd.device_id = ?1 AND d.device_type = ?2' )->setParameter ( 1, $device_id )->setParameter ( 2, $device_type )->getQuery ();
					$p = $q->execute ();
					error_log ( 'registerdevice.exec()->executed updated' . PHP_EOL );
				}
				
				$status = 'success';
				$message = "device token saved";
			} else {
				$status = 'failure';
				$message = "";
			}
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= "</registerdeviceresponse>";
			$xml_output .= "</xml>";
		} catch ( \Exception $e ) {
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<registerdeviceresponse>";
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>" . $e->getMessage () . "</message>";
			$xml_output .= "</registerdeviceresponse>";
			$xml_output .= "</xml>";
		}
		
		echo $xml_output;
		error_log ( "getsession ---> xml_output ----> " . $xml_output . PHP_EOL );
	}
}
?>
