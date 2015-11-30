<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Aws\S3\S3Client;
use Aws\S3\BatchDelete;
use Application\Entity\EventMedia;

class DeletePhoto {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $aws;
	protected $s3;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// Fetch aws handle
		$this->aws = MemreasConstants::fetchAWS ();
		// Fetch the S3 class
		$this->s3 = $this->aws->createS3 ();
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		$mediaid = trim ( $data->deletephoto->mediaid );
		
		error_log ( "Deleting ---> " . $mediaid . PHP_EOL );
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<deletephotoresponse>";
		
		if (isset ( $mediaid ) && ! empty ( $mediaid )) {
			$seldata = "select m from Application\Entity\Media m where m.media_id='$mediaid'";
			
			$statement = $this->dbAdapter->createQuery ( $seldata );
			$resseldata = $statement->getResult ();
			$user_id = $resseldata [0]->user_id;
			$copyright_id = $resseldata [0]->copyright_id;
			
			if (count ( $resseldata ) > 0) {
				
				// Check if media related to any event
				$media_event = "SELECT em FROM Application\Entity\EventMedia em WHERE em.media_id = '$mediaid'";
				$statement = $this->dbAdapter->createQuery ( $media_event );
				$result = $statement->getResult ();
				if (count ( $result ) > 0) {
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'fail::count($result)::', count ( $result ) );
					$xml_output .= '<status>failure</status><message>This media is related to a memreas share.</message>';
				} else {
					//
					// Batch Delete - memreasdevsec
					//
					$prefix = $resseldata [0]->user_id . '/' . $mediaid;
					$listObjectsParams = [ 
							'Bucket' => MemreasConstants::S3BUCKET,
							'Prefix' => $prefix 
					];
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'memreasdevsec Prefix => $prefix::', $prefix );
					$delete = BatchDelete::fromListObjects ( $this->s3, $listObjectsParams );
					// Asynchronously delete $promise = $delete->promise();
					// Force synchronous completion $delete->delete();
					$delete->delete ();
					
					//
					// Batch Delete - memreasdevhls - not working - 30-NOV-2015
					//
					// $prefix = $resseldata [0]->user_id . '/' . $mediaid;
					// $listObjectsParams = [
					// 'Bucket' => MemreasConstants::S3HLSBUCKET,
					// 'Prefix' => $prefix
					// ];
					// Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'memreasdevhls Prefix => $prefix::', $prefix );
					// $delete = BatchDelete::fromListObjects ( $this->s3, $listObjectsParams );
					// // Asynchronously delete $promise = $delete->promise();
					// // Force synchronous completion $delete->delete();
					// $delete->delete ();
					
					//
					// memreasdevsec
					//
					$prefix = $resseldata [0]->user_id . '/' . $mediaid;
					Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . '$prefix::', $prefix );
					try {
						
						$iterator = $this->s3->getIterator ( 'ListObjects', array (
								'Bucket' => MemreasConstants::S3BUCKET,
								'Prefix' => $prefix 
						) );
						
						foreach ( $iterator as $object ) {
							$this->s3->deleteObject ( array (
									'Bucket' => MemreasConstants::S3BUCKET,
									'Key' => $object ['Key'] 
							) );
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::S3BUCKET::$object[Key]::deleted::', $object ['Key'] );
						}
						
						$iterator = $this->s3->getIterator ( 'ListObjects', array (
								'Bucket' => MemreasConstants::S3HLSBUCKET,
								'Prefix' => $prefix 
						) );
						
						foreach ( $iterator as $object ) {
							$this->s3->deleteObject ( array (
									'Bucket' => MemreasConstants::S3HLSBUCKET,
									'Key' => $object ['Key'] 
							) );
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'MemreasConstants::S3HLSBUCKET::$object[Key]::deleted::', $object ['Key'] );
						}
					} catch ( \Exception $e ) {
						Mlog::addone ( __CLASS__ . __METHOD__ . LINE__ . 'Caught exception::', $e->getMessage () );
						Mlog::addone ( __CLASS__ . __METHOD__ . LINE__ . 'Error deleting $prefix::', $prefix );
					}
					
					/*
					 * JM: 28-NOV-2014 below commented - won't work given above
					 * if...
					 */
					// Remove event media related to this media also
					// $query_event = "DELETE FROM Application\Entity\EventMedia
					// em WHERE em.media_id='$mediaid'";
					// $event_statement = $this->dbAdapter->createQuery (
					// $query_event );
					// $event_result = $event_statement->getResult ();
					
					try {
						// media
						$delete_media = "DELETE FROM Application\Entity\Media m WHERE m.media_id='{$mediaid}'";
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'query $delete_media::', $delete_media );
						$media_statement = $this->dbAdapter->createQuery ( $delete_media );
						$delete_media_result = $media_statement->getResult ();
						// Media Device
						// Mlog::addone ( "_SESSION", $_SESSION );
						$user_id = $_SESSION ['user_id'];
						// media_device
						$delete_media_device = "DELETE FROM Application\Entity\MediaDevice m WHERE m.media_id='{$mediaid}' and m.user_id='{$user_id}' ";
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'query $delete_media_device::', $delete_media_device );
						$media_statement = $this->dbAdapter->createQuery ( $delete_media );
						$delete_media_result = $media_statement->getResult ();
					} catch ( \Exception $e ) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'Caught exception::', $e->getMessage () );
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'Error deleting from db::', $delete_media );
					}
					
					//
					// if copyright then update to note user deleted media invalidating copyright
					//
					try {
						if (! empty ( $copyright_id )) {
							$now = date ( 'Y-m-d H:i:s' );
							$seldata = "select c from Application\Entity\Copyright c where c.copyright_id='{$copyright_id}'";
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'query $seldata::', $seldata );
							$statement = $this->dbAdapter->createQuery ( $seldata );
							$resseldata = $statement->getResult ();
							$metadata = json_cecode ( $resseldata [0]->metadata, true );
							$metadata ['media_deleted_date'] = $now;
							
							// update copyright to note media was deleted with date.
							$json_metadata = json_encode ( $metadata );
							$update_copyright = "UPDATE Application\Entity\Copyright c SET c.metadata = $json_metadata WHERE c.copyright_id='{$copyright_id}'";
							Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'query $update_copyright::', $update_copyright );
							$statement = $this->dbAdapter->createQuery ( $update_copyright );
							$result_update_copyright = $statement->getResult ();
						}
					} catch ( \Exception $e ) {
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . 'Caught exception::', $e->getMessage () );
					}
					
					// if (count ( $result ) > 0) {
					if (count ( $delete_media_result ) > 0) {
						$xml_output .= "<status>success</status>";
						$xml_output .= "<message>Media removed successfully</message>";
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::db entry deleted!' );
					} else {
						$xml_output .= "<status>failure</status><message>An error occurred</message>";
						Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__, '::db entry delete failed' );
					}
				}
			} else
				$xml_output .= "<status>failure</status><message>Given media id is wrong.</message>";
		} else
			$xml_output .= "<status>failure</status><message>Please check media id specified.</message>";
		
		$xml_output .= "<media_id>{$mediaid}</media_id>";
		$xml_output .= "</deletephotoresponse>";
		$xml_output .= "</xml>\n";
		echo $xml_output;
	}
}
?>
