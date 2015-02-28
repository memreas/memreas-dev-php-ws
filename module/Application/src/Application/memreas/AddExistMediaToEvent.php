<?php
/*
* @This service will add exist media list to event at share tab
* @params: evennt id, media id list
* @return: true or false
*/
namespace Application\memreas;

use Zend\Session\Container;
use Application\Entity\Event;
use Application\Entity\Media;
use Application\Entity\EventMedia;

class AddExistMediaToEvent {
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
    public function exec($frmweb = false, $output = '') {
        $error_flag = 0;
        $message = '';
        if (empty ( $frmweb )) {
error_log("input::".$_POST['xml'].PHP_EOL);
        	$data = simplexml_load_string ( $_POST ['xml'] );
        } else {
error_log("input::".$frmweb.PHP_EOL);
        	$data = json_decode ( json_encode ( $frmweb ) );
        }
        $event_id = $data->addexistmediatoevent->event_id;
        $medias = $data->addexistmediatoevent->media_ids;

        //Check if event is existed or not
        $query_event = $this->dbAdapter->createQueryBuilder();
        $query_event->select('e.event_id')
                    ->from('Application\Entity\Event', 'e')
                    ->where('e.event_id=?1')
                    ->setParameter(1, $event_id);
        $result = $query_event->getQuery()->getResult();

        if (empty($result)){
            $status = 'Failure';
            $message = 'Event does not exist';
        }
        else{
            foreach ($medias->media_id as $media_id){

                //Check if media is existed or not
                $media_query = $this->dbAdapter->createQueryBuilder();
                $media_query->select('m.media_id')
                            ->from('Application\Entity\Media', 'm')
                            ->where('m.media_id=?1')
                            ->setParameter(1, $media_id);
                $result = $media_query->getQuery()->getResult();
                if (!empty($result)){
                    //Check if media is added or not
                    $check_media = $this->dbAdapter->createQueryBuilder();
                    $check_media->select('em')
                                ->from('Application\Entity\EventMedia', 'em')
                                ->where("em.event_id='$event_id' AND em.media_id='$media_id'");
                    $result = $check_media->getQuery()->getResult();
                    if (empty($result)){
                        $EventMediaInstance = new \Application\Entity\EventMedia();
                        $EventMediaInstance->event_id = $event_id;
                        $EventMediaInstance->media_id = $media_id;
                        $this->dbAdapter->persist ($EventMediaInstance);
                        $this->dbAdapter->flush ();
                    }
                }
            }
            $status = 'Success';
            $message = 'Medias successfully updated';
        }


        if ($frmweb) {
            return $output;
        }
        header ( "Content-type: text/xml" );
        $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<addexistmediatoeventresponse>";
        $xml_output .= "<status>" . $status . "</status>";
        $xml_output .= "<event_id>{$event_id}</event_id>";
        if (isset($message)) $xml_output .= "<message>{$message}</message>";
        $xml_output .= $output;
        $xml_output .= "</addexistmediatoeventresponse>";
        $xml_output .= "</xml>";
        echo $xml_output;
error_log("output::".$xml_output.PHP_EOL);
    }
}
?>
