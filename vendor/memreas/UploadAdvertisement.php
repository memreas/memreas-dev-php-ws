<?php

namespace memreas;
 use Aws\Common\Aws;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManager;
use memreas\UUID;
class UploadAdvertisement {

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
 
define("MAX_SIZE", "1000");

function getExtension($str) {
    $i = strrpos($str, ".");
    if (!$i) {
        return "";
    }
    $l = strlen($str) - $i;
    $ext = substr($str, $i + 1, $l);
    return $ext;
}
function resize_image($image,$uploadedfile,$filename_tmla,$foldername,$w,$h){
    
    if ($image) {
        $filename = stripslashes($image);
        $extension = getExtension($filename);
        $extension = strtolower($extension);
        

        if (($extension != "jpg") && ($extension != "jpeg") && ($extension != "png") && ($extension != "gif")) {
           // $change = '<div class="msgdiv">Unknown Image extension </div> ';
            $errors = 1;
        } else {
            
            $size = filesize($uploadedfile);                                   
//            if ($size > MAX_SIZE * 1024) {
//                // $change = '<div class="msgdiv">You have exceeded the size limit!</div> ';
//                $errors = 1;
//            }            
            if ($extension == "jpg" || $extension == "jpeg") {
                $src = imagecreatefromjpeg($uploadedfile);
            } else if ($extension == "png") {
                $src = imagecreatefrompng($uploadedfile);
            } else {
                $src = imagecreatefromgif($uploadedfile);
            }
            list($width, $height) = getimagesize($uploadedfile);
            $newwidth = $w;
            $newheight = $h;
            $tmp = imagecreatetruecolor($newwidth, $newheight);

//            $newwidth1 = $w;
//            $newheight1 =$h;
//            $tmp1 = imagecreatetruecolor($newwidth1, $newheight1);

            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
          //  imagecopyresampled($tmp1, $src, 0, 0, 0, 0, $newwidth1, $newheight1, $width, $height);

            $filename = $foldername.$filename_tmla;
            //$filename1 = $foldername. $image;
            $file=imagejpeg($tmp, $filename, 100);
            return true;
        }
    }
    //return false;
}
    }

}

?>
