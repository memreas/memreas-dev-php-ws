<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\AWSManagerSender;

class ListComments {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data    = $message_data;
        $this->memreas_tables  = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter       = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter     = $P->get(MemreasConstants::MEMREASDB);
        }

    public function exec() {
        $error_flag = 0;
        $message    = '';
        $data       = simplexml_load_string($_POST['xml']);
        $event_id   = trim($data->listcomments->event_id);
        $page       = trim($data->listcomments->page);
        if (empty($page)) {$limit = 1;}
        $limit      = trim($data->listcomments->limit);
        if (empty($limit)) {$limit = 10;}

        $from = ($page - 1) * $limit;


        header("Content-type: text/xml");
        $xml_output  = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
        $xml_output .= "<xml>";
        $xml_output .= "<listcommentsresponse>";

        //$q_comment = "SELECT COUNT(c.type) as totale_comment FROM Application\Entity\Comment c WHERE c.media_id='$media_id' and (c.type='text' or c.type='audio')";
            
            $qb = $this->dbAdapter->createQueryBuilder();
            $qb->select('c.text,u.username', 'm.metadata');
            $qb->from('Application\Entity\Comment', 'c');
            $qb->join('Application\Entity\User', 'u', 'WITH', 'c.user_id = u.user_id');
            $qb->leftjoin('Application\Entity\Media', 'm', 'WITH', 'm.user_id = u.user_id AND m.is_profile_pic = 1');
            $qb->where("c.event_id=?1 AND (c.type='text' or c.type='audio') ORDER BY c.create_time DESC");
            $qb->setMaxResults($limit);
            $qb->setFirstResult($from); 
            $qb->setParameter(1, $event_id);
            $result_comment = $qb->getQuery()->getResult();

             $xml_output.='<comments>';

            if (count($result_comment) <= 0) {
                $status = "Success";
                $message = "No TEXT Comment For this Event";
            } else {
                foreach ($result_comment as  $value) {
                $xml_output.='<comment>';
                    $xml_output.="<event_id>" . $event_id . "</event_id>";
                    $xml_output.="<comment_text>" . $value['text'] . "</comment_text>";
                    $xml_output.="<username>" . $value['username'] . "</username>";
                    $json_array = json_decode($value['metadata'], true);
                    $url1='';
                    if (!empty($json_array['S3_files']['path']))
                        $url1 = MemreasConstants::CLOUDFRONT_DOWNLOAD_HOST . $json_array['S3_files']['path'];
                        $xml_output.="<profile_pic><![CDATA[" . $url1 . "]]></profile_pic>";

                $xml_output.='</comment>';                 
                }

            }
            $xml_output.='</comments>';

        
        $xml_output .="</listcommentsresponse>";
        $xml_output .="</xml>";
        echo $xml_output;
    }
}

?>
