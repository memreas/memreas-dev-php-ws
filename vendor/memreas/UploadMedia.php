<?php

namespace memreas;
 use Aws\Common\Aws;
use Zend\Session\Container;
use Application\Model\MemreasConstants;
use memreas\AWSManagerSender;
use memreas\UUID;
class UploadMedia {

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
 
 
error_log("ENTER uploadmedia.php...");


ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_input_time', 3000);
ini_set('max_execution_time', 3000);

if (empty($_FILES['f'])) {
    return 'Please uplode file ';
}

    $f = $_FILES['f']['tmp_name'];
    
    $file_type = explode('/', $_FILES['f']['type']);
    if ($_FILES["f"]["error"] > 0) {
        return FALSE;
    } else {
        $newfilename = time() . '_' . str_ireplace(" ", "_", $_FILES['f']['name']);
        if (isset($_FILES['f'])) {

//-----------------for image upload------------------
            if (strcasecmp($file_type[0], 'image') == 0) {
                require_once 'upload-advertisement.php';
                //$folder = "/home/sufalam1/public_html/playfulencounter/public/130x150/";
                $w = 10;
                $h = 100;
                $ftmp = $_FILES['f']['tmp_name'];
                //echo $ftmp;exit;
                $oname = $_FILES['f']['name'];
				// dirPath = /data/temp_uuid/media/userimage/
				$temp_job_uuid_dir = UUID::getUUID($this->dbAdapter);
				$dirPath = getcwd() . MemreasConstants::DATA_PATH . $temp_job_uuid_dir . MemreasConstants::MEDIA_PATH;
				if (!file_exists($dirPath)) {
					mkdir($dirPath, 0777, true);
				}
                $upload = $dirPath . $newfilename;

                //memreas added
                $aws_manager = new AWSManagerSender();
                $s3paths = $aws_manager->s3upload($_POST['user_id'], $newfilename, $ftmp);
//                echo "<pre>";
//                print_r($s3paths);
//                echo "</pre>";
//                exit;

                return $s3paths;

                //if (move_uploaded_file($_FILES['f']['tmp_name'], $upload )) {
                //    return $newfilename;
                //}
//        }
            } else
            //--------------------------------for video upload-----------
            if (strcasecmp('video', $file_type[0]) == 0) {

                $aws_manager = new AWSManagerSender();
                $s3paths = $aws_manager->s3upload($_POST['user_id'], $newfilename, $_FILES['f']['tmp_name'], true);

                return $s3paths;

                //if(move_uploaded_file($_FILES['f']['tmp_name'], VIDEO.$newfilename))
                //    return $newfilename;
            } else
            //----------------------------------for audio------------
            if (strcasecmp('audio', $file_type[0]) == 0)
                $aws_manager = new AWSManagerSender();
            $s3paths = $aws_manager->s3upload($_POST['user_id'], $newfilename, $_FILES['f']['tmp_name'], true);

            return $s3paths;
            //if(move_uploaded_file($_FILES['f']['tmp_name'], AUDIO. $newfilename))
            //    return $newfilename;
        }
    }


error_log("EXIT uploadmedia.php...");
    }
    
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

?>
