<?php

namespace Application\memreas;

use Zend\Session\Container;
use Zend\View\Model\ViewModel;

use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class ForgotPassword {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		error_log ( "Inside__construct..." );
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		// $this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
	}
	public function exec() {
		function is_valid_email($email) {
			$result = TRUE;
			if (! preg_match ( '/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/', $email )) {
				$result = FALSE;
			}
			return $result;
		}
		
		$data = simplexml_load_string ( $_POST ['xml'] );
		$email = trim ( $data->forgotpassword->email );
		header ( "Content-type: text/xml" );
		$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
		$xml_output .= "<xml>";
		$xml_output .= "<forgotpasswordresponse>";
		if (isset ( $email ) && ! empty ( $email )) {
			$checkvalidemail = is_valid_email ( $email );
			if ($checkvalidemail == TRUE) {
				$query = "SELECT u FROM Application\Entity\User u where u.email_address='" . $email . "' and u.role = 2 and u.disable_account = 0";
				// $result = mysql_query($query);
				// $statement = $this->dbAdapter->createStatement($query);
				// $result = $statement->execute();
				// $row = $result->current();
				
				$statement = $this->dbAdapter->createQuery ( $query );
				$result = $statement->getResult ();

 				if (count ( $result ) > 0) {
					$data = $result [0];
					$username = $email;
					$to[] = $email;
					$pass = mt_rand ( 10000, 999999 );
					$password = md5 ( $pass );
					$token = uniqid ();
					
				 	$updatequr = "UPDATE Application\Entity\User u  set u.forgot_token ='" . $token . "' where u.user_id='" . $data->user_id . "'";
					// $resofupd = mysql_query($updatequr);
					// $statement1 = $this->dbAdapter->createStatement($updatequr);
					// $resofupd = $statement1->execute();
					// $row = $result->current();
					$statement = $this->dbAdapter->createQuery ( $updatequr );
					$resofupd = $statement->getResult ();

					if ($resofupd) {
						$subject = "Welcome to memreas!";
						
						$headers = "MIME-Version: 1.0" . "\r\n";
						$headers .= "Content-type:text/html;charset=iso-8859-1" . "\r\n";
						$headers .= 'From: <admin@memreas.com>' . "\r\n";

						///

				        $viewVar = array (
						        'email'    => $email,
						        'username' => $data->username ,
						        'token'  => $token
				        );
				         
				        $viewModel = new ViewModel ( $viewVar );
				        $viewModel->setTemplate ( 'email/forgotpassword' );
				        $viewRender = $this->service_locator->get ( 'ViewRenderer' );
				        $html = $viewRender->render ( $viewModel );
				        //echo $html ;exit;
				        $subject = 'Welcome to memreas!';
				        if (empty ( $aws_manager ))
					        $aws_manager = new AWSManagerSender ( $this->service_locator );
				        $aws_manager->sendSeSMail ( $to, $subject, $html ); //Active this line when app go live
				        $this->status = $status = 'Success';
				        $message = $html;
				        $xml_output .= "<status>success</status>";
						$xml_output .= "<message>Your password is send to your email address successfully.</message>";
						// error_log ( "Finished..." . PHP_EOL );
						
					} else {
						$xml_output .= "<status>failure</status>";
						$xml_output .= "<message>Error occur in password updation. Please try again.</message>";
					}
				} else {
					$xml_output .= "<status>failure</status>";
					$xml_output .= "<message>Incorrect email address or your account not active.</message>";
				}
			} else {
				$xml_output .= "<status>failure</status>";
				$xml_output .= "<message>Please enter valid email address.</message>";
			}
		} else {
			$xml_output .= "<status>failure</status>";
			$xml_output .= "<message>Please check that email address is given.</message>";
		}
		$xml_output .= "</forgotpasswordresponse>";
		$xml_output .= "</xml>";
		echo $xml_output;
	}
}

?>
