<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class MemreasConstants {
	
	// Turns off emails for perf testing
	const SEND_EMAIL = true;
	const ALLOW_DUPLICATE_EMAIL_FOR_TESTING = 1;
	// has 1 or 0 value
	const ALLOW_SELL_MEDIA_IN_PUBLIC = 1;
	const FORGOT_PASSWORD_CODE_LENGTH = 6;
	
	// localhost urls
	const WEB_URL = "http://memreas-dev-php-frontend.localhost/";
	const ORIGINAL_URL = "http://memreas-dev-php-ws.localhost/";
	const MEDIA_URL = "http://memreas-dev-php-ws.localhost/?action=addmediaevent";
	const MEMREAS_TRANSCODE_URL = "https://memreasdev-backend.memreas.com/";
	const MEMREAS_PAY_URL = "http://memreas-dev-php-stripe.localhost/";
	const MEMREAS_PAY_URL_STRIPE = "http://memreas-dev-php-stripe.localhost/stripe/";	
	const MEMREAS_PAY_URL_INDEX = "http://memreas-dev-php-stripe.localhost/index/";
	const QUEUEURL = 'https://sqs.us-east-1.amazonaws.com/004184890641/memreasdev-bewq';
	
	// Redis constant section
	const REDIS_SERVER_ENDPOINT = "127.0.0.1";
	const REDIS_SERVER_USE = true;
	const REDIS_SERVER_SESSION_ONLY = true;
	const REDIS_SERVER_PORT = "6379";
	const REDIS_CACHE_TTL = 3600;
	// 1 hour
	
	// memreasdevsec related
	const S3BUCKET = "memreasdevsec";
	const S3HLSBUCKET = "memreasdevhlssec";
	const S3_APPKEY = 'AKIAJMXGGG4BNFS42LZA';
	const S3_APPSEC = 'xQfYNvfT0Ar+Wm/Gc4m6aacPwdT5Ors9YHE/d38H';
	const S3_REGION = 'us-east-1';
	const CLOUDFRONT_STREAMING_HOST = 'rtmp://s1u1vmosmx0myq.cloudfront.net/cfx/st/mp4:';
	const CLOUDFRONT_DOWNLOAD_HOST = 'https://d3sisat5gdssl6.cloudfront.net/';
	const SIGNURLS = true;
	
	// const CLOUDFRONT_HLSSTREAMING_HOST = 'https://d2b3944zpv2o6x.cloudfront.net/';
	const CLOUDFRONT_HLSSTREAMING_HOST = 'https://d2cbahrg0944o.cloudfront.net/';
	const CLOUDFRONT_KEY_FILE = '/key/pk-APKAISSKGZE3DR5HQCHA.pem';
	const CLOUDFRONT_KEY_PAIR_ID = 'APKAISSKGZE3DR5HQCHA';
	const CLOUDFRONT_EXPIRY_TIME = 36000; // 10 hours
	
	//free plan id
	const MEMREAS_FREE_PLAN = "PLAN_A_2GB_MONTHLY";
	                                      
	// Same across...
	const copyright_batch_create_count = 25;
	const copyright_batch_minimum = 10;
	const URL = "/index";
	const MEMREAS_TRANSCODER = true;
	const MEMREASDB = 'memreasintdb';
	const S3HOST = 'https://s3.amazonaws.com/';
	const EXPIRES = 36000;
	// 10 hour
	const DATA_PATH = "/data/";
	const MEDIA_PATH = "/media/";
	const IMAGES_PATH = "/images/";
	const USERIMAGE_PATH = "/media/userimage/";
	const FOLDER_PATH = "/data/media/";
	const FOLDER_AUDIO = "upload_audio";
	const FOLDER_VIDEO = "uploadVideo";
	const VIDEO = "/data/media/uploadVideo";
	const AUDIO = "/data/media/upload_audio";
	const FB_APPID = '462180953876554';
	const FB_SECRET = '23dcd2db19b17f449f39bfe9e93176e6';
	// const FB_FBHREF = 'https://apps.facebook.com/462180953876554/';
	const FB_FBHREF = ' /index/canvas';
	const TW_CONSUMER_KEY = '9jwg1vX4MgH7rfBzxqkcjI90f';
	const TW_CONSUMER_SECRET = 'bDqOeHkJ7OIQ4QPNnT1PA9oz55gf51YW0REBo12aazGA0CBrbY';
	const TW_OAUTH_TOKEN = '1941271416-UuUhh7XTVJ7npEjmgQHAypAnl0VmNqOKJ7BzMp2';
	const TW_OAUTH_TOKEN_SECRET = 't0wqWd0OpHrZTWYHvx9VqVl3iySDTfZklKkB6v1WaohxH';
	const ADMIN_EMAIL = 'admin@memreas.com';
	const GCM_SERVER_KEY = 'AIzaSyArHQUvC2rpXabM3g_T_VPKa82vCaCrslE';
	const COPYRIGHT = '&copy;2015 memreas, llc. all rights reserved.';
	public static function fetchAWS() {
		$sharedConfig = [ 
				'region' => 'us-east-1',
				'version' => 'latest',
				'credentials' => [ 
						'key' => self::S3_APPKEY,
						'secret' => self::S3_APPSEC 
				] 
		];
		
		return new \Aws\Sdk ( $sharedConfig );
	}
}