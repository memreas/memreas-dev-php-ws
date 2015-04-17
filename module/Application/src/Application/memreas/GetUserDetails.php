<?php

/*
 * Get user's details service
 * @params: user_id Provide user id to get back detail
 * @Return User information detail
 * @Tran Tuan
 */
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\User;
use Application\Entity\Media;
use Guzzle\Http\Client;

class GetUserDetails {
	protected $message_data;
	protected $memreas_tables;
	protected $service_locator;
	protected $dbAdapter;
	protected $url_signer;
	public function __construct($message_data, $memreas_tables, $service_locator) {
		$this->message_data = $message_data;
		$this->memreas_tables = $memreas_tables;
		$this->service_locator = $service_locator;
		$this->dbAdapter = $service_locator->get ( 'doctrine.entitymanager.orm_default' );
		$this->url_signer = new MemreasSignedURL ();
	}
	
	public function exec($frmweb = false, $output = '') {
		try {
			$error_flag = 0;
			$message = '';
			if (empty ( $frmweb )) {
				$data = simplexml_load_string ( $_POST ['xml'] );
			} else {
				$data = json_decode ( json_encode ( $frmweb ) );
			}
error_log('$data--->'.print_r($data,true).PHP_EOL);			
			$user_id = trim ( $data->getuserdetails->user_id );
			
			$qb = $this->dbAdapter->createQueryBuilder ();
			$qb->select ( 'u' );
			$qb->from ( 'Application\Entity\User', 'u' );
			$qb->where ( "u.user_id=?1" );
			$qb->setParameter ( 1, $user_id );
			$result_user = $qb->getQuery ()->getResult ();
			if (empty ( $result_user )) {
error_log ('empty result ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				$status = "Failure";
				$message = "No data available to this user";
			} else {
error_log ('not empty result ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				$status = 'Success';
				$output .= '<user_id>' . $result_user [0]->user_id . '</user_id>';
				$output .= '<username>' . $result_user [0]->username . '</username>';
				$output .= '<email>' . $result_user [0]->email_address . '</email>';
				$metadata = $result_user [0]->metadata;
				$metadata = json_decode ( $metadata, true );
				if (isset ( $metadata ['alternate_email'] ))
					$output .= '<alternate_email>' . $metadata ['alternate_email'] . '</alternate_email>';
				else
					$output .= '<alternate_email></alternate_email>';
				
error_log ('past alternate email??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				if (isset ( $metadata ['gender'] ))
					$output .= '<gender>' . $metadata ['gender'] . '</gender>';
				else
					$output .= '<gender></gender>';
				
error_log ('past gender??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				if (isset ( $metadata ['dob'] ))
					$output .= '<dob>' . $metadata ['dob'] . '</dob>';
				else
					$output .= '<dob></dob>';
					
error_log ('past dob??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				// For plan
				if (isset ( $metadata ['subscription'] )) {
					$subscription = $metadata ['subscription'];
					$output .= '<subscription><plan>' . $subscription ['plan'] . '</plan><plan_name>' . $subscription ['name'] . '</plan_name></subscription>';
				} else
					$output .= '<subscription><plan>FREE</plan></subscription>';
					
error_log ('past subscription??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				// For account type
				$guzzle = new Client ();
				$request = $guzzle->post ( MemreasConstants::MEMREAS_PAY_URL, null, array (
						'action' => 'checkusertype',
						'username' => $result_user [0]->username 
				) );
				
error_log ('past guzzle pay url??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				$response = $request->send ();
				$data = json_decode ( $response->getBody ( true ), true );
error_log ('stripe json--->'.$response->getBody ( true ). 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				if ($data ['status'] == 'Success') {
error_log ('stripe status success??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$types = $data ['types'];
					$output .= '<account_type>';
					foreach ( $types as $key => $type ) {
						if ($key > 0)
							$output .= ",";
						$output .= $type;
					}
					$output .= '</account_type>';
					$output .= "<buyer_balance>" . $data ['buyer_balance'] . "</buyer_balance>";
					$output .= "<seller_balance>" . $data ['seller_balance'] . "</seller_balance>";
				} else
error_log ('stripe free account??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
					$output .= '<account_type>Free user</account_type>';
					
					// Get user profile
				$profile_query = $this->dbAdapter->createQueryBuilder ();
				$profile_query->select ( 'm' )->from ( 'Application\Entity\Media', 'm' )->where ( "m.user_id = '{$result_user[0]->user_id}' AND m.is_profile_pic = 1" );
				$profile = $profile_query->getQuery ()->getResult ();
error_log ('getting profile pic??? ...'. 'file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				if (empty ( $profile ))
					$output .= '<profile></profile>';
				else {
					$profile_image = json_decode ( $profile [0]->metadata, true );
					if (! empty ( $profile_image ['S3_files'] ['thumbnails'] ['79x80'] )) {
						$profile_image = $this->url_signer->signArrayOfUrls ( $profile_image ['S3_files'] ['thumbnails'] ['79x80'] [0] );
					} else {
						$profile_image = MemreasConstants::ORIGINAL_URL . '/memreas/img/profile-pic.jpg';
					}
					
					$output .= '<profile><![CDATA[' . $profile_image . ']]></profile>';
				}
			}
			
			if ($frmweb) {
error_log ('frmweb output--->'.$output. '  file--->' . __FILE__ . ' method -->' . __METHOD__ . ' line number::' . __LINE__ . PHP_EOL );
				return $output;
			}
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<userdetailsresponse>";
			$xml_output .= "<status>" . $status . "</status>";
			if (isset ( $message ))
				$xml_output .= "<message>{$message}</message>";
			$xml_output .= $output;
			$xml_output .= "</userdetailsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		} catch ( \Exception $e ) {
			$status = 'failure';
			$message .= 'GetUserDetails error ->' . $e->getMessage ();
			header ( "Content-type: text/xml" );
			$xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
			$xml_output .= "<xml>";
			$xml_output .= "<userdetailsresponse>";
			$xml_output .= "<status>$status</status>";
			$xml_output .= "<message>$message</message>";
			$xml_output .= $output;
			$xml_output .= "</userdetailsresponse>";
			$xml_output .= "</xml>";
			echo $xml_output;
		}
		error_log ( '$this->xml_output--->' . $xml_output . PHP_EOL );
	} // end exec()
}

?>
