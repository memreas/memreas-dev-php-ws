<?php
// ///////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
// ///////////////////////////////
namespace Application\Model;

class MemreasConstants {
	const MEMREAS_TRANSCODER = true;
	
	const MEMREASDB = 'memreasintdb';
	const S3BUCKET = "memreasdev";
	const TOPICARN = "arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int";
	const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/awseb-e-8zdmyxfwy5-stack-AWSEBWorkerQueue-1RTF4RETMQXT8';
	// const ORIGINAL_URL = "http://memreas-dev-php-ws.localhost/";
	const ORIGINAL_URL = "http://memreasdev-ws-elastic.elasticbeanstalk.com/";
	const MEDIA_URL = "http://memreasdev-ws-elastic.elasticbeanstalk.com/?action=addmediaevent";
	const URL = "/index";
	
	// ElastiCache section
	const ELASTICACHE_SERVER_ENDPOINT = "memreasintcache.142tbh.cfg.use1.cache.amazonaws.com";
	const ELASTICACHE_SERVER_PORT = "11211";
	const ELASTICACHE_SERVER_USE = false;
	
	// const SITEURL = "http://memreasdev.elasticbeanstalk.com/eventapp_zend2.1";
	const DATA_PATH = "/data/";
	const MEDIA_PATH = "/media/";
	const IMAGES_PATH = "/images/";
	const USERIMAGE_PATH = "/media/userimage/";
	const FOLDER_PATH = "/data/media/";
	const FOLDER_AUDIO = "upload_audio";
	const FOLDER_VIDEO = "uploadVideo";
	const VIDEO = "/data/media/uploadVideo";
	const AUDIO = "/data/media/upload_audio";
	const CLOUDFRONT_STREAMING_HOST = 'rtmp://s1iq2cbtodqqky.cloudfront.net/cfx/st/mp4:';
	const CLOUDFRONT_DOWNLOAD_HOST = 'http://d1ckv7o9k6o3x9.cloudfront.net/';
	const MEMREAS_TRANSCODER_URL = 'http://memreasbackend.elasticbeanstalk.com/';
	// const MEMREAS_TRANSCODER_URL = 'http://192.168.1.13/memreas-dev-php-backend/app/';
	const MEMREAS_TRANSCODER_TOPIC_ARN = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
}