<?php
/*
* Get Media Like
* @params: media_id
* @Return Media Like total
* @Tran Tuan
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;
use Application\Entity\Comment;

class  GetMediaLike{
    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;
    public function __construct($message_data, $memreas_tables, $service_locator) {
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
        $media_id = trim ( $data->getmedialike->media_id );

        $likeCountSql = $this->dbAdapter->createQuery ( "SELECT COUNT(c.comment_id) FROM Application\Entity\Comment c Where c.media_id=?1 AND c.like=1" );
        $likeCountSql->setParameter ( 1, $media_id );
        $likeCount = $likeCountSql->getSingleScalarResult ();

        $status = 'Success';
        $output .= '<likes>' . $likeCount . '</likes>';

        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<getmedialikeresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</getmedialikeresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
    }
}

?>
