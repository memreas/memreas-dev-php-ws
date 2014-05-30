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
	//bew2 url
	//const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-backend-worker2';
	// const ORIGINAL_URL = "http://memreas-dev-php-ws.localhost/";
	const ORIGINAL_URL = "http://memreasdev-wsu.elasticbeanstalk.com/";
	const MEDIA_URL = "http://memreasdev-wsu.elasticbeanstalk.com/?action=addmediaevent";
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
	
	
	const FB_APPID  = '462180953876554';
	const FB_SECRET = '23dcd2db19b17f449f39bfe9e93176e6';
	const FB_FBHREF = 'https://apps.facebook.com/462180953876554';
	 	 


	/*
	API key XjWz7d8AIh0hq6mDqjR7mA
[8:01:56 PM] johnmeah2: API secret wlF52rzjDCtrFNzZ8lJRgMU9Fd4aOagkqLclomXOYg
	 */	
	const TW_CONSUMER_KEY ='9jwg1vX4MgH7rfBzxqkcjI90f';
	const TW_CONSUMER_SECRET = 'bDqOeHkJ7OIQ4QPNnT1PA9oz55gf51YW0REBo12aazGA0CBrbY';
	const TW_OAUTH_TOKEN = '1941271416-UuUhh7XTVJ7npEjmgQHAypAnl0VmNqOKJ7BzMp2';
	const TW_OAUTH_TOKEN_SECRET = 't0wqWd0OpHrZTWYHvx9VqVl3iySDTfZklKkB6v1WaohxH';
	 


}