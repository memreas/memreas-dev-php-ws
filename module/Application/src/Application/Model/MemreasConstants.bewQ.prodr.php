<?php
// ///////////////////////////////
// Author: John Meah
// Copyright memreas llc 2013
// ///////////////////////////////
namespace Application\Model;

class MemreasConstants {

	//Turns off emails for perf testing
	const SEND_EMAIL = false;
	const ALLOW_DUPLICATE_EMAIL_FOR_TESTING = 1;//has 1 or 0 value
	const ALLOW_SELL_MEDIA_IN_PUBLIC = 1;
	
	//memreasprod urls
	const WEB_URL = "https://memreasprod-fe.memreas.com/";
	const ORIGINAL_URL = "https://memreasprod-wsr.memreas.com/";
	const ELASTICACHE_REDIS_WARM_PERSON_URL = "https://memreasprod-wsr.memreas.com/index?action=warm_person";
	const MEDIA_URL = "https://memreasprod-wsr.memreas.com/?action=addmediaevent";
	const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasprod-backend-worker';
	const MEMREAS_PAY_URL = "https://memreasdev-pay.memreas.com";
	
	// ElastiCache section
	//const ELASTICACHE_SERVER_ENDPOINT = "memreasprod-redis.142tbh.0001.use1.cache.amazonaws.com";
	const ELASTICACHE_SERVER_ENDPOINT = "54.204.57.197"; //ubuntu standalone for redis 2.8.9 version
	const ELASTICACHE_SERVER_PORT = "6379";
	const ELASTICACHE_SERVER_USE = true;
	const ELASTICACHE_REDIS_USE = true;
	const ELASTICACHE_CACHE_TTL = 600; //10 minutes
	
	//memreasprdsec related
	const S3BUCKET = "memreasprdsec";
    const S3_APPKEY = 'AKIAJMXGGG4BNFS42LZA';
    const S3_APPSEC = 'xQfYNvfT0Ar+Wm/Gc4m6aacPwdT5Ors9YHE/d38H';
	const CLOUDFRONT_STREAMING_HOST = 'rtmp://s12hcdq6y0d1zq.cloudfront.net/cfx/st/mp4:';
	const CLOUDFRONT_DOWNLOAD_HOST = 'https://d3j7vnip9qhisx.cloudfront.net/';
	const SIGNURLS = true;

	//Same across...
	const URL = "/index";
	const MEMREAS_TRANSCODER = true;
	const MEMREASDB = 'memreasintdb';
	const S3HOST = 'https://s3.amazonaws.com/';
	const EXPIRES = 36000; // 10 hour
	
	const DATA_PATH = "/data/";
	const MEDIA_PATH = "/media/";
	const IMAGES_PATH = "/images/";
	const USERIMAGE_PATH = "/media/userimage/";
	const FOLDER_PATH = "/data/media/";
	const FOLDER_AUDIO = "upload_audio";
	const FOLDER_VIDEO = "uploadVideo";
	const VIDEO = "/data/media/uploadVideo";
	const AUDIO = "/data/media/upload_audio";
	
	
	const FB_APPID  = '462180953876554';
	const FB_SECRET = '23dcd2db19b17f449f39bfe9e93176e6';
	//const FB_FBHREF = 'https://apps.facebook.com/462180953876554';
	const FB_FBHREF = '/index';
	const TW_CONSUMER_KEY ='9jwg1vX4MgH7rfBzxqkcjI90f';
	const TW_CONSUMER_SECRET = 'bDqOeHkJ7OIQ4QPNnT1PA9oz55gf51YW0REBo12aazGA0CBrbY';
	const TW_OAUTH_TOKEN = '1941271416-UuUhh7XTVJ7npEjmgQHAypAnl0VmNqOKJ7BzMp2';
	const TW_OAUTH_TOKEN_SECRET = 't0wqWd0OpHrZTWYHvx9VqVl3iySDTfZklKkB6v1WaohxH';
	const ADMIN_EMAIL ='admin@memreas.com';
	const ALLOW_DUPLICATE_EMAIL_FOR_TESTING = 0;//has 1 or 0 value
	


}