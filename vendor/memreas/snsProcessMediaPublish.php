<?php

namespace memreas;

use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;
class snsProcessMediaPublish {

    protected $message_data;
    protected $memreas_tables;
    protected $service_locator;
    protected $dbAdapter;

    public function __construct($message_data, $memreas_tables, $service_locator) {
        error_log("Inside__construct...");
        $this->message_data = $message_data;
        $this->memreas_tables = $memreas_tables;
        $this->service_locator = $service_locator;
        $this->dbAdapter = $service_locator->get('memreasdevdb');
        //$this->dbAdapter = $service_locator->get(MemreasConstants::MEMREASDB);
    }

    public function exec() {
    //Fetch the media ID
error_log("xml ------> " . $_POST['xml']);
$data = simplexml_load_string($_POST['xml']);
$user_id = trim($data->snsProcessMediaPublish->user_id);
$media_id = trim($data->snsProcessMediaPublish->media_id);
$content_type = trim($data->snsProcessMediaPublish->content_type);
$s3path = trim($data->snsProcessMediaPublish->s3path);
$s3file_name = trim($data->snsProcessMediaPublish->s3file_name);
$isVideo = trim($data->snsProcessMediaPublish->isVideo);
$email = trim($data->snsProcessMediaPublish->email);
$message_data = array (
	'user_id' => trim($data->snsProcessMediaPublish->user_id),
	'media_id' => trim($data->snsProcessMediaPublish->media_id),
	'content_type' => trim($data->snsProcessMediaPublish->content_type),
	's3path' => trim($data->snsProcessMediaPublish->s3path),
	's3file_name' => trim($data->snsProcessMediaPublish->s3file_name),
	'isVideo' => trim($data->snsProcessMediaPublish->isVideo),
	'email' => trim($data->snsProcessMediaPublish->email)
	);




//Process Message here - 
$aws_manager = new AWSManager();
$response = $aws_manager->snsProcessMediaPublish($message_data);
echo "<pre>";print_r($response);
//echo "</pre>";exit;    

    }

}

?>
