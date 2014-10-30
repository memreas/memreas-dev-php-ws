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
    	$this->url_signer = new MemreasSignedURL();
    }

    /*
     *
     */
    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty ( $frmweb )) {
            $data = simplexml_load_string ( $_POST ['xml'] );
        } else {

            $data = json_decode ( json_encode ( $frmweb ) );
        }
        $user_id = trim ( $data->getuserdetails->user_id );

        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select ( 'u' );
        $qb->from ( 'Application\Entity\User', 'u' );
        $qb->where ( "u.user_id=?1" );
        $qb->setParameter(1, $user_id);
        $result_user = $qb->getQuery ()->getResult ();
        if (empty($result_user)) {
            $status = "Failure";
            $message = "No data available to this user";
        } else {
            $status = 'Success';
            $output .= '<user_id>' . $result_user[0]->user_id . '</user_id>';
            $output .= '<username>' . $result_user[0]->username . '</username>';
            $output .= '<email>' . $result_user[0]->email_address . '</email>';

            $metadata = $result_user[0]->metadata;
            $metadata = json_decode($metadata, true);

            if (isset($metadata['alternate_email']))
                $output .= '<alternate_email>' . $metadata['alternate_email'] . '</alternate_email>';
            else $output .= '<alternate_email></alternate_email>';

            if (isset($metadata['gender']))
                $output .= '<gender>' . $metadata['gender'] . '</gender>';
            else $output .= '<gender></gender>';

            if (isset($metadata['dob']))
                $output .= '<dob>' . $metadata['dob'] . '</dob>';
            else $output .= '<dob></dob>';

            //For plan
            if (isset($metadata['subscription'])){
                $subscription = $metadata['subscription'];
                $output .= '<subscription><plan>' . $subscription['plan'] . '</plan><plan_name>' . $subscription['name'] . '</plan_name></subscription>';
            }
            else $output .= '<subscription><plan>FREE</plan></subscription>';

            //For account type
            $guzzle = new Client();
            $request = $guzzle->post(
                MemreasConstants::MEMREAS_PAY_URL,
                null,
                array(
                    'action' => 'checkusertype',
                    'username' => $result_user[0]->username
                )
            );

            $response = $request->send();
            $data = json_decode($response->getBody(true), true);
            if ($data['status'] == 'Success'){
                $types = $data['types'];
                $output .= '<account_type>';
                foreach ($types as $key => $type) {
                    if ($key > 0)
                        $output .= ",";
                    $output .= $type;
                }
                $output .= '</account_type>';
                $output .= "<buyer_balance>" . $data['buyer_balance'] . "</buyer_balance>";
                $output .= "<seller_balance>" . $data['buyer_balance'] . "</seller_balance>";
            }
            else $output .= '<account_type>Free user</account_type>';

            //Get user profile
            $profile_query = $this->dbAdapter->createQueryBuilder();
            $profile_query->select('m')
                            ->from('Application\Entity\Media', 'm')
                            ->where("m.user_id = '{$result_user[0]->user_id}' AND m.is_profile_pic = 1");
            $profile = $profile_query->getQuery()->getResult();
            if (empty($profile))
                $output .= '<profile></profile>';
            else{
                $profile_image = json_decode($profile[0]->metadata, true);
                $profile_image = $this->url_signer->fetchSignedURL(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $profile_image ['S3_files'] ['path']);
                $output .= '<profile><![CDATA[' . $profile_image . ']]></profile>';
            }
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<userdetailsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</userdetailsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
