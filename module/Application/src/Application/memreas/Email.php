<?php

namespace Application\memreas;

use Application\Model\MemreasConstants as MC;
use Application\memreas\AWSManagerSender;
use Zend\View\Renderer\PhpRenderer;
use Zend\View\Model\ViewModel;
use \Exception;

class Email {
	const  USER_REGISTRATION = 'user-registration';
	const  EVENT_INVITE = 'event-invite';
	const  EVENT_INVITE_RESPONSE = 'event-invite-response';
	const  FRIEND_REQUEST = 'friend-request';
	const  FRIEND_REQUEST_RESPONSE = 'friend-request-response';
	const  USER_CHANGEPASSWORD = 'user-changepassword';
	const  USER_FORGETPASSWORD = 'user-forgetpassword';
	const  USER_COMMENT = 'user-comment';
	const  REGISTRATION = 'registration';
	const  ADMIN_ERROR_OCCURRED = 'admin-error-occurred';
	
	protected static $service_locator;
	protected static $dbAdapter;
	public static $item = array ();
	protected static $collection = array ();
	public static function collect() {
error_log('item-->'.json_encode(self::$item).PHP_EOL);
		self::$collection [] = self::$item;
		self::$item = array ();
error_log('$collection-->'.json_encode(self::$collection).PHP_EOL);
	}
	public static function ok() {
		return empty ( $item->ok ) ? false : TRUE;
	}
	public static function sendmail($servicemanager) {
		$aws_manager = new AWSManagerSender ( $servicemanager );
		$viewModel = new ViewModel ( array () );
		
		$viewRender = $servicemanager->get ( 'ViewRenderer' );
		foreach ( self::$collection as $value ) {
error_log ( 'email collection value' . json_encode($value) .PHP_EOL );
			if (empty ( $value ['email'] ))
				continue;
			$email = self::getSubject ( $value );
			$viewModel->setTemplate ( $email ['template'] );
			$subject = $email ['subject'];
			$viewModel->clearVariables ();
			$viewModel->setVariables ( $value );
			$html = $viewRender->render ( $viewModel );
			try {
				error_log ( 'sending-email-' . $value ['email'] );
				$aws_manager->sendSeSMail ( array (
						$value ['email'] 
				), $subject, $html );
			} catch ( \Exception $exc ) {
				error_log ( 'exception->sending mail' . $exc->getMessage () );
			}
		}
	}
	public static function sendEmailNotification ($sm, $db, $receiver_uid, $sender_uid, $type, $status='', $comment='') {
error_log('$receiver_uid-->'.$receiver_uid.' ::::file--->'. basename(__FILE__ ). PHP_EOL);
error_log('$sender_uid-->'.$sender_uid.' ::::file--->'. basename(__FILE__ ). PHP_EOL);
error_log('$type-->'.$type.' ::::file--->'. basename(__FILE__ ). PHP_EOL);
error_log('$status-->'.$status.' ::::file--->'. basename(__FILE__ ). PHP_EOL);
error_log('$comment-->'.$comment.' ::::file--->'. basename(__FILE__ ). PHP_EOL);
		
		$ReplyTo = $db->getRepository ( "\Application\Entity\User" )->findOneBy ( array (
				'user_id' => $receiver_uid
		) );
	
		$Sender = $db->getRepository ( "\Application\Entity\User" )->findOneBy ( array (
				'user_id' => $sender_uid
		) );
error_log('$Sender->username'.$Sender->username.PHP_EOL);
error_log('$ReplyTo->username'.$ReplyTo->username.PHP_EOL);
error_log('$ReplyTo->username'.$ReplyTo->email_address.PHP_EOL);

		self::$item ['type'] = $type;
		self::$item ['sender_name'] = $Sender->username;
		self::$item ['receiver_name'] = $ReplyTo->username;
		self::$item ['email'] = $ReplyTo->email_address;
		self::$item ['status'] = $status;
		self::$item ['message'] = $comment;
		self::collect ();
		Email::sendmail ( $sm );
		return true;
	}
	
	public static function getSubject($item) {
		$data = array (
				'user-registration' => array (
						'subject' => "welcome to memreas",
						'message' => '',
						'template' => 'email/register' 
				),
				'event-invite' => array (
						'subject' => "memreas invite",
						'template' => 'email/event-invite' 
				),
				'event-invite-response' => array (
						'subject' => "memreas invite response",
						'template' => 'email/event-invite-response' 
				),
				'friend-request' => array (
						'subject' => "friend request",
						'template' => 'email/friend-request' 
				),
				'friend-request-response' => array (
						'subject' => "friend request response",
						'template' => 'email/friend-request-response' 
				),
				'user-registration' => array (
						'subject' => "memreas registration",
						'template' => '' 
				),
				'user-changepassword' => array (
						'subject' => "password change",
						'template' => '' 
				),
				'user-forgetpassword' => array (
						'subject' => "forgot password",
						'template' => 'email/forgotpassword' 
				),
				'user-comment' => array (
						'subject' => "you received a comment",
						'template' => 'email/user-comment' 
				),
				'registration' => array (
						'subject' => "memreas registration",
						'template' => '' 
				),
				'admin-error-occurred' => array (
						'subject' => "Error has occurred",
						'template' => 'email/admin-error-occurred' 
				) 
		)
		;
		
		return $data [$item ['type']];
	}
}

?>
