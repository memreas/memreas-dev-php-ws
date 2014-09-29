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

class SaveUserDetails {
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
        $user_id = trim ( $data->saveuserdetails->user_id );
        $email = trim ( $data->saveuserdetails->email );
        $password = trim ($data->saveuserdetails->password);
        $gender = trim ($data->saveuserdetails->gender);
        $dob = trim ($data->saveuserdetails->dob);
        $profile_picture = trim($data->saveuserdetails->profile_picture);

        //check if exist user's email
        $qb = $this->dbAdapter->createQueryBuilder ();
        $qb->select('u')
            ->from('Application\Entity\User', 'u')
            ->where("u.email_address = '{$email}' AND u.user_id <> '{$user_id}'");
        $user_info = $qb->getQuery ()->getResult ();

        if (empty($user_info)){
            $qb = $this->dbAdapter->createQueryBuilder ();
            $qb->select('u')
                ->from('Application\Entity\User', 'u')
                ->where("u.user_id = '{$user_id}'");
            $user_detail = $qb->getQuery ()->getResult ();

            if (!empty($user_detail)){
                $metadata = $user_detail[0]->metadata;
                $metadata = json_decode($metadata, true);
                $metadata['alternate_email'] = $email;
                $metadata['gender'] = $gender;
                $metadata['dob'] = $dob;
                $metadata = json_encode($metadata);
                $query = "UPDATE Application\Entity\User u SET u.metadata = '{$metadata}'";
                if (!empty($password))
                    $query .= ", u.password = '" . md5($password) . "'";

                if (!empty($profile_picture))
                    $query .= ", u.profile_photo = 1";

                $query .= " WHERE u.user_id = '{$user_id}'";
                $qb = $this->dbAdapter->createQuery($query);
                $result = $qb->getResult();
                if ($result){
                    /*
                     * Update profile picture
                     * */
                    if (!empty($profile_picture)){
                        $qb = $this->dbAdapter->createQueryBuilder ();
                        $qb->select('m')
                            ->from('Application\Entity\Media', 'm')
                            ->where("m.user_id = '{$user_id}' AND m.is_profile_pic = 1");
                        $userProfile = $qb->getQuery()->getResult();

                        //If user has record related to profile
                        if ($userProfile){
                            $metadata = json_decode($userProfile[0]->metadata, true);
                            $metadata['S3_files'] ['path'] = str_replace(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST, "", $profile_picture);
                            $metadata['S3_files'] ['full'] = str_replace(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST, "", $profile_picture);
                            $metadata = json_encode($metadata);
                            $media = $userProfile[0];
                            $media->metadata = $metadata;
                            $this->dbAdapter->persist ( $media );
                            $this->dbAdapter->flush ();
                        }
                        else{

                            $json_array = array ();
                            $s3file = str_replace(MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST, "", $profile_picture);

                            //$s3file = $s3url;
                            $json_array ['S3_files'] ['path'] = $s3file;
                            $json_array ['S3_files'] ['full'] = $s3file;
                            $json_array ['S3_files'] ['location'] = '';
                            $json_array ['S3_files'] ['local_filenames'] ['device'] ['unique_device_identifier1'] = $user_id . '_';
                            $json_array ['S3_files'] ['file_type'] = 'image';
                            $json_array ['S3_files'] ['content_type'] = 'image';
                            $json_array ['S3_files'] ['type'] ['image'] ['format'] = 'image';

                            $now = date ( 'Y-m-d H:i:s' );
                            $tblMedia = new \Application\Entity\Media ();
                            $media_id = MUUID::fetchUUID ();
                            $tblMedia->media_id = $media_id;
                            $tblMedia->user_id = $user_id;
                            $tblMedia->is_profile_pic = 1;
                            $tblMedia->metadata = json_encode($json_array);
                            $tblMedia->create_date = $now;
                            $tblMedia->update_date = $now;
                            $this->dbAdapter->persist ( $tblMedia );
                            $this->dbAdapter->flush ();
                        }
                    }

                    $status = 'Success';
                    $message = 'User details updated';
                }
                else{
                    $status = 'Failure';
                    $message = 'Update user details failed';
                }
            }
            else{
                $status = 'Failure';
                $message = 'User does not exist';
            }
        }
        else{
            $status = 'Failure';
            $message = 'This email is already in use by another user';
        }

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<saveuserdetailsresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</saveuserdetailsresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
