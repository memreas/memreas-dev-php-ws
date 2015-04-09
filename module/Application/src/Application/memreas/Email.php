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
	const  FRIEND_REQUEST = 'friend-request';
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
		self::$collection [] = self::$item;
		self::$item = array ();
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
	public static function getSubject($item) {
		$data = array (
				'user-registration' => array (
						'subject' => "Welcome to event app",
						'message' => '',
						'template' => 'email/register' 
				),
				'event-invite' => array (
						'subject' => "Event Invitation",
						'template' => 'email/event-invite' 
				),
				'friend-request' => array (
						'subject' => "Friend Request",
						'template' => 'email/friend-request' 
				),
				'user-registration' => array (
						'subject' => "",
						'template' => '' 
				),
				'user-changepassword' => array (
						'subject' => "",
						'template' => '' 
				),
				'user-forgetpassword' => array (
						'subject' => "",
						'template' => 'email/forgotpassword' 
				),
				'user-comment' => array (
						'subject' => "New Comment",
						'template' => 'email/user-comment' 
				),
				'registration' => array (
						'subject' => "",
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
