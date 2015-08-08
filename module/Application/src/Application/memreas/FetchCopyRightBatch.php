<?php

namespace Application\memreas;

use Application\memreas\MUUID;
use Application\memreas\Mlog;

class FetchCopyRightBatch {
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
	}
	public function exec() {
		$copyright_id = '';
		$copyright_id_md5 = '';
		$copyright_batch_json = '';
		$copyright_batch_send_json = '';
		try {
			$data = simplexml_load_string ( $_POST ['xml'] );
			
			$status = 'failure';
			Mlog::addone ( '$_SESSION', $_SESSION );
			$user_id = $_SESSION ['user_id'];
			Mlog::addone ( '$user_id', $user_id );
			//
			// Check if batch is available
			//
			$query_event = "select c
			     from Application\Entity\CopyrightBatch c
			     where c.user_id='" . $user_id . "'
			     and c.remaining > 0
			     ORDER BY c.create_date DESC";
			$statement = $this->dbAdapter->createQuery ( $query_event );
			$result = $statement->getResult ( (\Doctrine\ORM\Query::HYDRATE_ARRAY) );
			if (! empty ( $result )) {
				Mlog::addone ( '!empty($result)', $result );
				$copyright_batch_send_json = $result [0] ['metadata'];
				$status = 'success';
			} else {
				Mlog::addone ( 'else', '...' );
				$copyright_batch = [ ];
				$copyright_batch_send = [ ];
				$id_count = 25;
				$copyright_batch_id = MUUID::fetchUUID ();
				for($i = 0; $i < $id_count; $i ++) {
					$media_id = MUUID::fetchUUID ();
					$copyright_id = MUUID::fetchUUID ();
					$copyright_id_md5 = md5 ( $copyright_id );
					$copyright_id_sha1 = sha1 ( $copyright_id );
					
					// For db
					$copyright_batch [$i] ['media_id'] = $media_id;
					$copyright_batch [$i] ['copyright_id'] = $copyright_id;
					$copyright_batch [$i] ['copyright_id_md5'] = $copyright_id_md5;
					$copyright_batch [$i] ['copyright_id_sha1'] = $copyright_id_sha1;
					$copyright_batch [$i] ['used'] = '0';
					
					// for user
					$copyright_batch_send [$i] ['copyright_batch_id'] = $copyright_batch_id;
					$copyright_batch_send [$i] ['media_id'] = $media_id;
					$copyright_batch_send [$i] ['copyright_id_md5'] = $copyright_id_md5;
					$copyright_batch_send [$i] ['copyright_id_sha1'] = $copyright_id_sha1;
					$copyright_batch_send [$i] ['used'] = 0;
				}
				$copyright_batch_json = json_encode ( $copyright_batch );
				$copyright_batch_send_json = json_encode ( $copyright_batch_send );
				
				$now = date ( 'Y-m-d H:i:s' );
				$tblCopyrightBatch = new \Application\Entity\CopyrightBatch ();
				$tblCopyrightBatch->copyright_batch_id = $copyright_batch_id;
				$tblCopyrightBatch->user_id = $user_id;
				$tblCopyrightBatch->metadata = $copyright_batch_json;
				$tblCopyrightBatch->remaining = $id_count;
				$tblCopyrightBatch->create_date = $now;
				$tblCopyrightBatch->update_time = $now;
				$this->dbAdapter->persist ( $tblCopyrightBatch );
				$this->dbAdapter->flush ();
				
				$status = 'success';
			}
		} catch ( Exception $e ) {
			Mlog::addone ( 'Caught exception: ', $e->getMessage () );
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<fetchcopyrightbatchresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<copyright_batch>" . '<![CDATA[' . $copyright_batch_send_json . ']]>' . "</copyright_batch>";
		$xml_output .= "</fetchcopyrightbatchresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
		// error_log ( "fetchcopyrightbatchresponse ---> " . $xml_output . PHP_EOL );
	}
}
?>
