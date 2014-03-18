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

class GetUserDetails {
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
        // $this->dbAdapter = $P->get(MemreasConstants::MEMREASDB);
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
                $profile_image = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $profile_image ['S3_files'] ['path'];
                $output .= '<profile>' . $profile_image . '</profile>';
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
