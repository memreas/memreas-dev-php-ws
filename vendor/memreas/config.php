<?php
//error_reporting(0);
//ini_set("display_errors",0);
//define("RECORD_PER_PAGE_LIMIT", 10);
$dbhost = 'aa19n8yspndox3g.co0fw2snbu92.us-east-1.rds.amazonaws.com';
$dbuser = 'memreasdbuser';
$dbpass = 'memreas2013';
$dbname = 'memreasdevdb';
//$dbhost = 'localhost';
//$dbuser = 'root';
//$dbpass = '';
//$dbname = 'eventappnew';
$conn = mysql_connect($dbhost, $dbuser, $dbpass) or die('Error connecting to mysql');
mysql_select_db($dbname, $conn);
//$imgurl = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1/media/";
$siteUrl = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1";
//$siteUrl="http://192.168.1.139/eventapp_zend2.1FilesToS3/eventapp_zend2.1";    //this url use for call websercives using curl
$imgurl = $siteUrl."/media/";
define('SITE_URL', $siteUrl);

$dirpath = realpath("../media/userimage").'/';
$folderpath = realpath("../media").'/';
define('FOLDER_PATH', $folderpath);
define("DIR_PATH", $dirpath);
define('FOLDER_AUDIO','upload_audio');
define('FOLDER_VIDEO','uploadVideo');
define('VIDEO', ("../media/").FOLDER_VIDEO.'/');
define("AUDIO",("../media/").FOLDER_AUDIO.'/');
ini_set("gd.jpeg_ignore_warning", 1);
//added for S3 and Cloudfront
define('CLOUDFRONT_STREAMING_HOST','http://s1iq2cbtodqqky.cloudfront.net/');
define('CLOUDFRONT_DOWNLOAD_HOST','http://d1ckv7o9k6o3x9.cloudfront.net/');
define('S3BUCKET','memreasdev');
define('MEMREAS_TRANSCODER_URL','http://memreas-rest-backend.localhost/index/transcoder');
define('MEMREAS_TRANSCODER_TOPIC_ARN','arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker');