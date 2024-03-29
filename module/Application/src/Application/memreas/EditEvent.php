<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\memreas;

class EditEvent {
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
	public function exec() {
		$cm = __CLASS__ . __METHOD__;
		$data = simplexml_load_string ( $_POST ['xml'] );
		$message = '';
		
		Mlog::addone ( $cm . __LINE__ . 'data as json-->', json_encode ( $data ) );
		//
		// ws parameters - xml
		//
		Mlog::addone ( $cm . __LINE__ . '$data->editevent->event_from[0]-->', ( string ) $data->editevent->event_from [0] );
		Mlog::addone ( $cm . __LINE__ . '$data->editevent->event_to[0]-->', ( string ) $data->editevent->event_to [0] );
		$event_id = trim ( $data->editevent->event_id );
		$event_name = trim ( $data->editevent->event_name );
		$event_location = trim ( $data->editevent->event_location );
		$event_date = trim ( ( string ) $data->editevent->event_date [0] );
		$is_public = trim ( $data->editevent->is_public );
		$event_from = $duration_from = trim ( ( string ) $data->editevent->event_from [0] );
		$event_to = $duration_to = trim ( ( string ) $data->editevent->event_to [0] );
		$is_friend_can_share = trim ( $data->editevent->is_friend_can_add_friend );
		$is_friend_can_post_media = trim ( $data->editevent->is_friend_can_post_media );
		$event_self_destruct = trim ( ( string ) $data->editevent->event_self_destruct );
		$sell_media = trim ( $data->editevent->sell_media );
		$price = trim ( $data->editevent->price );
		$delete_event = ( int ) $data->editevent->delete_event;
		
		Mlog::addone ( $cm . __LINE__ . '$event_id-->', $event_id );
		Mlog::addone ( $cm . __LINE__ . '$event_name-->', $event_name );
		Mlog::addone ( $cm . __LINE__ . '$event_location-->', $event_location );
		Mlog::addone ( $cm . __LINE__ . '$event_date-->', $event_date );
		Mlog::addone ( $cm . __LINE__ . '$is_public-->', $is_public );
		Mlog::addone ( $cm . __LINE__ . '$event_from-->', $event_from );
		Mlog::addone ( $cm . __LINE__ . '$event_to-->', $event_to );
		Mlog::addone ( $cm . __LINE__ . '$is_friend_can_share-->', $is_friend_can_share );
		Mlog::addone ( $cm . __LINE__ . '$is_friend_can_post_media-->', $is_friend_can_post_media );
		Mlog::addone ( $cm . __LINE__ . '$event_self_destruct-->', $event_self_destruct );
		Mlog::addone ( $cm . __LINE__ . '$sell_media-->', $sell_media );
		Mlog::addone ( $cm . __LINE__ . '$metadata-->', $metadata );
		Mlog::addone ( $cm . __LINE__ . '$delete_event-->', $delete_event );
		
		$media_array = $data->editevent->medias->media;
		$friend_array = $data->editevent->friends->friend;
		
		$type = '';
		if ($delete_event) {
			$type = 'deleted';
			//
			// Delete event
			//
			$query = "update Application\Entity\Event as e set e.delete_flag=$delete_event";
			$query .= " where e.event_id='$event_id' ";
			Mlog::addone ( $cm . __LINE__, "delete query ---> $query" );
		} else {
			//
			// Update event
			//
			$type = 'updated';
			if (! isset ( $event_id ) || empty ( $event_id )) {
				$message = 'event id is empty';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_name ) || empty ( $event_name )) {
				$message = 'event name is empty';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_date )) {
				$message = 'event date is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_location )) {
				$message = 'event location is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $is_public )) {
				$message = 'public field is empty';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_from )) {
				$message = 'event from is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_to )) {
				$message = 'event to date is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $is_friend_can_share )) {
				$message = 'frients can share field is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $is_friend_can_post_media )) {
				$message = 'friend can post field is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else if (! isset ( $event_self_destruct )) {
				$message = 'ghost field is not set';
				$status = 'Failure';
				Mlog::addone ( $cm . __LINE__, "$message" );
			} else {
				
				//
				// check dates for empty
				//
				if (empty ( $event_location )) {
					$event_location = '';
				}
				if (empty ( $event_date )) {
					$event_date = '';
				} else {
					Mlog::addone ( $cm . __LINE__, '$event_date-->' . $event_date );
				}
				if (empty ( $event_from )) {
					$event_from = '';
				} else {
					Mlog::addone ( $cm . __LINE__, '$event_from-->' . $event_from );
				}
				if (empty ( $event_to )) {
					$event_to = '';
				} else {
					Mlog::addone ( $cm . __LINE__, '$event_to-->' . $event_to );
				}
				if (empty ( $event_self_destruct )) {
					$event_self_destruct = '';
				} else {
					Mlog::addone ( $cm . __LINE__, '$event_self_destruct-->' . $event_self_destruct );
				}
				
				$json_meta = json_encode ( $metadata );
				$now = MNow::now ();
				$query = "update Application\Entity\Event as e set                  
				e.name='$event_name',
				e.location='$event_location',
				e.date='$event_date',
				e.public='$is_public',
				e.friends_can_post='$is_friend_can_post_media',
				e.friends_can_share='$is_friend_can_share',
				e.viewable_from='$event_from',
				e.viewable_to='$event_to',
				e.self_destruct='$event_self_destruct',
				e.update_time='$now'";
				
				//
				// Set pricing
				//
				$metadata = array ();
				$metadata ['price'] = $price;
				$metadata ['duration_from'] = $duration_from;
				$metadata ['duration_to'] = $duration_to;
				
				// Always set pricing info...
				$qb = $this->dbAdapter->createQueryBuilder ();
				$qb->select ( 'e' )->from ( 'Application\Entity\Event', 'e' )->where ( 'e.event_id = ?1' )->setParameter ( 1, $event_id );
				$event_detail = $qb->getQuery ()->getResult ();
				$event_detail = $event_detail [0];
				$event_meta = json_decode ( $event_detail->metadata, true );
				$event_meta ['update_time'] = time ();
				$metadata ['archive'] [] = $event_meta;
				$query .= ", e.metadata = '" . json_encode ( $metadata ) . "'";
				$query .= " where e.event_id='$event_id' ";
				Mlog::addone ( $cm . __LINE__, "update query ---> $query" );
			}
		} // end else for update
		  
		//
		  // handle update or delete
		  //
		if (isset ( $query ) && ! empty ( $query )) {
			$statement = $this->dbAdapter->createQuery ( $query );
			$result = $statement->getResult ();
		} else {
			$result = '';
		}
		Mlog::addone ( __CLASS__ . __METHOD__ . __LINE__ . "update result ---> ", $result );
		if (! empty ( $result )) {
			$message .= 'event successfully ' . $type;
			$status = 'Success';
		} else {
			$message .= 'error: update failed';
			$status = 'Failure';
		}
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<editeventresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "<event_id>$event_id</event_id>";
		$xml_output .= "<event_name>$event_name</event_name>";
		$xml_output .= "</editeventresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	} // end exec()
}

?>
