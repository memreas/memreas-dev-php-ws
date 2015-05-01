<?php

namespace Application\memreas;

use Application\memreas\AWSManagerSender;
use Zend\View\Model\ViewModel;
use \Exception;

class ChangePassword {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		$data = simplexml_load_string ( $_POST ['xml'] );
		$sid = trim ( $data->sid );
		$token = trim ( $data->changepassword->token );
		$new = trim ( $data->changepassword->new );
		$retype = trim ( $data->changepassword->retype );
		$username = trim ( $data->changepassword->username );
		$password = trim ( $data->changepassword->password );
		$status = '';
		$message = '';
		
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<changepassword>";
		if ($new != $retype) {
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>New Password and Verify password did not match </message>";
		}
		
		if (! empty ( $token )) {
			$query = "SELECT u FROM Application\Entity\User u where u.forgot_token='" . $token . "' and u.role = 2 and u.disable_account = 0";
			$statement = $this->dbAdapter->createQuery ( $query );
			$result = $statement->getOneOrNullResult ();
			
			if (count ( $result ) > 0) {
				$pass = md5 ( $new );
				$updatequr = "UPDATE Application\Entity\User u  set u.forgot_token = '', u.password ='" . $pass . "' where u.user_id='" . $result->user_id . "'";
				$statement = $this->dbAdapter->createQuery ( $updatequr );
				$resofupd = $statement->getResult ();
				$status = 'success';
				$message = 'password changed successfully ';
			} else {
				$status = 'failure';
				$message = 'Incorrect Activation code.';
			}
		} else if (! empty ( $username ) && ! empty ( $password ) && ! empty ( $sid )) {
			$sql = "SELECT u FROM Application\Entity\User as u where u.username = '" . $username . "' and u.password = '" . md5 ( $password ) . "' and u.role = 2 and u.disable_account = 0";
			$statement = $this->dbAdapter->createQuery ( $sql );
			$result = $statement->getOneOrNullResult ();
			if (count ( $result ) > 0) {
				$pass = md5 ( $new );
				$updatequr = "UPDATE Application\Entity\User u  set u.forgot_token = '', u.password ='" . $pass . "' where u.user_id='" . $result->user_id . "'";
				$statement = $this->dbAdapter->createQuery ( $updatequr );
				$resofupd = $statement->getResult ();
				$status = 'success';
				$message = 'password changed successfully ';
			} else {
				$status = 'failure';
				$message = 'invalid username or password';
			}
		}
		
		if (! empty ( $resofupd )) {
			$to[] = $result->email_address;
			$viewVar = array (
					'email' => $to,
					'username' => $result->username,
			);
			$viewModel = new ViewModel ( $viewVar );
			$viewModel->setTemplate ( 'email/changedpassword' );
			$viewRender = $this->service_locator->get ( 'ViewRenderer' );
			$html = $viewRender->render ( $viewModel );
			$subject = 'memreas - forgot password';
			$aws_manager = new AWSManagerSender ( $this->service_locator );
			try {
				$aws_manager->sendSeSMail ( $to, $subject, $html );
			} catch ( \Exception $exc ) {
				$message = 'Unable to send email';
			}
		}
		
		$xml_output .= "<status>$status</status>";
		$xml_output .= "<message>$message</message>";
		$xml_output .= "</changepassword>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
