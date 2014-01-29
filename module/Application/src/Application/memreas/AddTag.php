<?php

namespace Application\memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use Application\memreas\MUUID;
use \Exception;

class AddTag {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($service_locator) {
        error_log("Inside__construct...");
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('doctrine.entitymanager.orm_default');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec($frmweb = '') {

        if (empty($frmweb)) {
            $data = simplexml_load_string($_POST['xml']);
        } else {

            $data = json_decode(json_encode($frmweb));
        }
        $message = '';
        $tag = trim($data->addtag->tag);
        $meta = trim($data->addtag->meta);
        $tag_type = trim($data->addtag->tag_type);
        $time = time();

        //save notification in table
        $tblTag = $this->dbAdapter->getRepository('\Application\Entity\Tag')->findOneBy(array('tag' => $tag));

        if (!$tblTag) {
            //add tag
            $tag_id = MUUID::fetchUUID();
            $tblTag = new \Application\Entity\Tag();
            $tblTag->tag = $tag;
            $tblTag->tag_id = $tag_id;
            $tblTag->tag_type = $tag_type;
            $tblTag->create_time = $time;

            $tblTag->update_time = $time;
            $tblTag->meta = $meta;
            $this->dbAdapter->persist($tblTag);
            $this->dbAdapter->flush();
        } else {

            //update tag 
            $tblTag->meta = $meta;
            $tblTag->update_time = $time;

            $status = "Sucess";
            $message = "Notification Updated";

            $this->dbAdapter->merge($tblTag);
            $this->dbAdapter->flush();
        }



        if (empty($frmweb)) {
            header("Content-type: text/xml");
            $xml_output = "<?xml version=\"1.0\"  encoding=\"utf-8\" ?>";
            $xml_output .= "<xml>";
            $xml_output.= "<tagresult>";
            $xml_output.= "<status>$status</status>";
            $xml_output.= "<tag>" . $message . "</tag>";
            $xml_output.= "<tag_id>$tag_id</tag_id>";
            $xml_output.= "<meta>$meta</meta>";

            $xml_output.= "</tagresult>";
            $xml_output.= "</xml>";
            echo $xml_output;
        }
    }

}

?>
