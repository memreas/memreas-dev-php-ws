<?php
/*
* Update media detail
* @params: null
* @Return
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Media;
use \Exception;

class UpdateMedia {
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
        $media_id = trim ( $data->updatemedia->media_id );

        $latitude = trim( $data->updatemedia->location->latitude );
        $longtitude = trim ( $data->updatemedia->location->longtitude );

        $query = $this->dbAdapter->createQueryBuilder();
        $query->select("m")
                ->from("\Application\Entity\Media", "m")
                ->where("m.media_id = '{$media_id}'");
        $media = $query->getQuery()->getResult();

        if (empty ($media)){
            $message = 'Media does not exist';
            $status = 'Failure';
        }
        else{
            $metadata = $media[0]->metadata;
            $metadata = json_decode($metadata);

            //Update media location
            $metadata->S3_files->location = array('longtitude' => $longtitude, 'latitude' => $latitude);
            $metadata = json_encode($metadata);

            $media = $media[0];
            $media->metadata = $metadata;
            $this->dbAdapter->persist ( $media );
            $this->dbAdapter->flush ();

            $message = 'Media updated';
            $status = 'Success';
            $output .= '<media_id>' . $media_id . '</media_id>';
        }

        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<updatemediaresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</updatemediaresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
