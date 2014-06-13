<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\memreas\MUUID;
use Application\memreas\gcm;
use Zend\View\Model\ViewModel;

class AddFriendtoevent {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $notification;
	protected $AddNotification;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Enter AddFriendtoevent.__construct()" . PHP_EOL );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		if (! $this->AddNotification) {
			$this->AddNotification = new AddNotification ( $message_data, $memreas_tables, $service_locator );
		}
		if (! $this->notification) {
			$this->notification = new Notification ( $service_locator );
		}
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
		error_log ( "Exit AddFriendtoevent.__construct()" . PHP_EOL );
	}
	public function exec($frmweb = '') {
		error_log ( "Enter AddFriendtoevent.exec()" . PHP_EOL );
		error_log ( "Enter AddFriendtoevent.exec() xml ----> " . $_POST ['xml'] . PHP_EOL );

		if (empty ( $frmweb )) {
			$data = simplexml_load_string ( $_POST ['xml'] );
		} else {

			$data =  simplexml_load_string( $frmweb );
		}

		$friend_array = $data->addfriendtoevent->friends->friend;
		$user_id = (trim ( $data->addfriendtoevent->user_id ));
		$event_id = (trim ( $data->addfriendtoevent->event_id ));
		$group_array = (trim ( $data->addfriendtoevent->groups ));
		$email_array = $data->addfriendtoevent->emails->email;


		$status = "";
		$message = "";
		$message1 = "";
		$time = time ();
		$error = 0;

		$userOBj = $this->dbAdapter->find ( 'Application\Entity\User', $user_id );
		$eventOBj = $this->dbAdapter->find ( 'Application\Entity\Event', $event_id );



		// add group to event_group
		if (! empty ( $group_array )) {
			error_log ( "Enter AddFriendtoevent.exec() - !empty(group_array)" . PHP_EOL );
			foreach ( $group_array as $key => $value ) {
				$group_id = $value->group->group_id;
				if ($group_id != 'null') {
					$check = "SELECT e  FROM  Application\Entity\EventGroup e  where e.event_id='" . $event_id . "' and e.group_id='" . $group_id . "'";
					$statement = $this->dbAdapter->createQuery ( $check );
					$result_check = $statement->getResult ();

					if ($result_check->count () > 0) {
						$message1 = 'event group already exist.';
						$status = 'Failure';
					} else {
						// insert
						$tblEventGroup = new \Application\Entity\EventGroup ();
						$tblEventGroup->group_id = $group_id;
						$tblEventGroup->event_id = $event_id;

						$this->dbAdapter->persist ( $tblEventGroup );
						$this->dbAdapter->flush ();

						$message1 = 'event group Successfully added ';
						$status = 'Success';
					}
				} // end if ($group_id != 'null')
			} // end foreach
		} // end if (!empty($group_array))

		// add friends to event loop
		if (! empty ( $friend_array )) {
			foreach ( $friend_array as $key => $value ) {
				$network_name = addslashes ( trim ( $value->network_name ) );
				$friend_name = addslashes ( trim ( $value->friend_name ) );
				$friend_id =  trim ( $value->friend_id );
				$profile_pic_url = stripslashes ( trim ( $value->profile_pic_url ) );
				$friend_query = "select f.friend_id ,f.network from Application\Entity\Friend f where f.network='$network_name' and f.friend_id='$friend_id'";
				$statement = $this->dbAdapter->createQuery ( $friend_query );
				$result_friend = $statement->getOneOrNullResult ();
				// add to friend
				if ($result_friend) {
					$friend_id = $result_friend ['friend_id'];
					$network_name = $result_friend ['network'];
				} else {
					error_log ( "Enter AddFriendtoevent.exec() - insdide if (result_friend) else " . PHP_EOL );
					// //

					if ($network_name == 'memreas') {
error_log ( "networkname == memreas" . PHP_EOL );
						$r = $this->dbAdapter->getRepository ( 'Application\Entity\User' )->findOneBy ( array (
								'username' => $friend_name,
								'disable_account' => 0
						) );
						$friend_id = $r->user_id;
error_log ( "found friend_id in user table---> $friend_id" . PHP_EOL );

						 //check record exist in friend
						$fr = $this->dbAdapter->getRepository ( 'Application\Entity\Friend' )->findOneBy ( array (
									'friend_id' => $friend_id
							) );
error_log ( "found friend_id in user table---> $friend_id" . PHP_EOL );
					}

					/*
					 * If friend exists as user add to friend table if not there
					 */
 					if(empty($fr)&&!empty($friend_id)){
 						$tblFriend = new \Application\Entity\Friend ();
						$tblFriend->friend_id = $friend_id;
						$tblFriend->network = $network_name;
						$tblFriend->social_username = $friend_name;
						$tblFriend->url_image = $profile_pic_url;
						$tblFriend->create_date = $time;
						$tblFriend->update_date = $time;

						try {
							$this->dbAdapter->persist ( $tblFriend );
							$this->dbAdapter->flush ();
							error_log ( "Enter AddFriendtoevent.exec() - succeeded to insert tblFriend" . PHP_EOL );
						} catch ( \Exception $exc ) {

							error_log ( "Enter AddFriendtoevent.exec() - failure to insert tblFriend" . PHP_EOL );
							$status = 'failure';
							$error = 1;
						}
error_log ( "Inserted friend table ---> $friend_id" . PHP_EOL );
 					}
				} // end if ($result_friend) else

				/*
				 * add to user_friend
				 */
				if (isset ( $friend_id ) && ! empty ( $friend_id )) {
					$check_user_frind = "SELECT u  FROM  Application\Entity\UserFriend u where u.user_id='$user_id' and u.friend_id='$friend_id'";

					$statement = $this->dbAdapter->createQuery ( $check_user_frind );
					$r = $statement->getResult ();

					if (count ( $r ) > 0) {
						$status = "success";
						$message .= $friend_name . " is already in your friend list. ";
					} else {

						$tblUserFriend = new \Application\Entity\UserFriend ();
						$tblUserFriend->friend_id = $friend_id;
						$tblUserFriend->user_id = $user_id;

						try {
							$this->dbAdapter->persist ( $tblUserFriend );
							$this->dbAdapter->flush ();

							$message .= 'Event Friend Successfully added';
							$status = 'Success';
						} catch ( \Exception $exc ) {
							$status = 'Failure';
							$error = 1;
						}
					}
error_log ( "Inserted user_friend table ---> $friend_id" . PHP_EOL );


				}
				// adding friend to event
				if (! empty ( $event_id ) && $error == 0) {
					$check_event_frind = "SELECT e FROM Application\Entity\EventFriend e  where e.event_id='$event_id' and e.friend_id='$friend_id'";
					$statement = $this->dbAdapter->createQuery ( $check_event_frind );
					$r = $statement->getResult ();

					if (count ( $r ) > 0) {
						$status = "Success";
						$message .= "$friend_name is already in your Event Friend list.";
						error_log ( "$friend_name is in event friend list ---> $friend_id" . PHP_EOL );
					} else {
						// insert EventFriend
						$tblEventFriend = new \Application\Entity\EventFriend ();
						$tblEventFriend->friend_id = $friend_id;
						$tblEventFriend->event_id = $event_id;

						try {
							$this->dbAdapter->persist ( $tblEventFriend );
							$this->dbAdapter->flush ();
							$message .= 'Event Friend Successfully added';
							$status = 'Success';
						} catch ( \Exception $exc ) {
							$message .= '';
							$status = 'failure';
						}
error_log ( "$friend_name is in event friend list ---> $friend_id" . PHP_EOL );
					} // end if (count($r) > 0) else
					$nmessage = $userOBj->username . ' want to add you to ' . $eventOBj->name . ' event';
					// save nofication intable
					$ndata = array (
								'addNotification' => array (
									'network_name' => $network_name,
										'user_id' => $friend_id,
										'meta' => $nmessage,
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT,
										'links' => json_encode ( array (
												'event_id' => $event_id,
												'from_id' => $user_id
										) )
								)
						);
					if ($network_name == 'memreas') {
						// send push message add user id
 						$this->notification->add ( $friend_id );
					} else {
						$nmessage = $userOBj->username . ' invite you to !' . $eventOBj->name . ' event';
						$ndata ['addNotification'] ['meta'] = $nmessage;
error_log("message ---> $nmessage".PHP_EOL);
						//add non memeras
 						$this->notification->addFriend ( $friend_id );
					}


				} else{

					$nmessage = $userOBj->username . ' has send friend request ';
					// save nofication intable
					$ndata = array (
								'addNotification' => array (
									'network_name' => $network_name,
										'user_id' => $friend_id,
										'meta' => $nmessage,
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND,
										'links' => json_encode ( array (
										'from_id' => $user_id
										) )
								)
						);

					if ($network_name == 'memreas') {
						// send push message add user id
 						$this->notification->add ( $friend_id );
					} else {
						$ndata ['addNotification'] ['meta'] = $nmessage;
						error_log("message ---> $nmessage".PHP_EOL);
						//add non memeras
 						$this->notification->addFriend ( $friend_id );
					}

				}// endif (!empty($event_id) && $error == 0)
				//add notification in  db.
					$this->AddNotification->exec ( $ndata );
			} // end foreach
		} // end if (!empty($friend_array))

		//email notifaction start
		if (!empty($email_array)) {

						$viewVar = array();
						$viewModel = new ViewModel ();
 				        $aws_manager = new AWSManagerSender ( $this->service_locator );
				        $viewModel->setTemplate ( 'email/event-invite' );
				        $viewRender = $this->service_locator->get ( 'ViewRenderer' );

				        //convert to array
 						$json = json_encode($email_array);
						$to = json_decode($json,TRUE);

				        $viewVar['email'] = $email;
				        $viewVar['message'] = $nmessage;
				        $viewModel->setVariables($viewVar);
				        $html = $viewRender->render ( $viewModel );
				        $subject = 'Event Invitation';

				        //$aws_manager->sendSeSMail ( $to, $subject, $html ); //Active this line when app go live
 				        $this->status = $status = 'Success';
				        $message = "Welcome to .";
				        // save nofication intable
					$endata = array (
								'addNotification' => array (
									'network_name' => 'email',
										'user_id' => $user_id,
										'meta' => $nmessage,
										'notification_type' => \Application\Entity\Notification::ADD_FRIEND_TO_EVENT,
										'links' => json_encode ( array (
												'event_id' => $event_id,
												'from_id' => $user_id,
												'emails' => $json
										) )
								)
						);
					//add notification in  db.
					$this->AddNotification->exec ( $endata );
				        //
 						try {
							$aws_manager->sendSeSMail ( $to, $subject, $html );


						} catch ( \Exception $exc ) {
							error_log('exception->sendig mail'.$exc->getMessage());
							$message = 'Unable to send email';
						}
		}
		//email notication end






		if (! empty ( $ndata ['addNotification'] ['meta'] )) {
			// set nofication data and call send method
			$this->notification->setMessage ( $ndata ['addNotification'] ['meta'] );
			$this->notification->type = \Application\Entity\Notification::ADD_FRIEND_TO_EVENT;
			$this->notification->event_id = $event_id;
			$this->notification->send ();
		} // end if (!empty($data['addNotification']['meta']))




		// add friends to event loop end
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<addfriendtoeventresponse>";
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>" . $message;
        if (!empty($message1)) $xml_output .= " And " . $message1;
        $xml_output .= "</message>";
		$xml_output .= "<event_id>$event_id</event_id>";
		$xml_output .= "</addfriendtoeventresponse>";
		$xml_output .= "</xml>";

		if($frmweb == ''){
			echo $xml_output;
		}


	} // end exec
} // end class
?>
