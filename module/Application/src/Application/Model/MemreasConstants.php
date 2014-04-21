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
	//bew url
	const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-backend-worker';
	// const ORIGINAL_URL = "http://memreas-dev-php-ws.localhost/";
	const ORIGINAL_URL = "http://memreasdev-ws-etc.elasticbeanstalk.com/";
	const MEDIA_URL = "http://memreasdev-ws-etc.elasticbeanstalk.com/?action=addmediaevent";
	const URL = "/index";
	
	// ElastiCache section
	const ELASTICACHE_SERVER_ENDPOINT = "memreasintcache.142tbh.cfg.use1.cache.amazonaws.com";
	const ELASTICACHE_SERVER_PORT = "11211";
	const ELASTICACHE_SERVER_USE = false;
	
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
	const MEMREAS_TRANSCODER_TOPIC_ARN = 'arn:aws:sns:us-east-1:004184890641:us-east-upload-transcode-worker-int';
	const FB_APPID  = '152686394870393';
	const FB_SECRET = '62215615af53550af20c1d56736c189e';
	const FB_FBHREF = 'http://apps.facebook.com/kptestfb';
	 	 
	 
	const TW_CONSUMER_KEY ='1bqpAfSWfZFuEeY3rbsKrw';
	const TW_CONSUMER_SECRET = 'wM0gGBCzZKl5dLRB8TQydRDfTD5ocf2hGRKSQwag';
	const TW_OAUTH_TOKEN = '74094832-mnJlYPt02qpy1jhEYAYPMKAzrLF2jTeMiJue65Zn7';
	const TW_OAUTH_TOKEN_SECRET = 'zdIrpUzuIs7llt5KLlx1TU1vWUrq28TkSNFUsschaaE4X';
	 	
	

}