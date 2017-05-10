<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class MemreasConstants {
	
	//environment
	const ENV = 'DEV';
	
	// Turns off emails for perf testing
	const SEND_EMAIL = true;
	const ALLOW_DUPLICATE_EMAIL_FOR_TESTING = 1;
	// has 1 or 0 value
	const ALLOW_SELL_MEDIA_IN_PUBLIC = 1;
	const FORGOT_PASSWORD_CODE_LENGTH = 6;
	
	// localhost urls
	const BASE_URL= "https://www.memreas.com";
	const WEB_URL = "http://127.0.0.1:55151/";
	const ORIGINAL_URL = "http://127.0.0.1:55152/";
	const MEDIA_URL = "http://127.0.0.1:55152/?action=addmediaevent";
	const MEMREAS_TRANSCODE_URL = "https://memreasdev-backend.memreas.com/";
	const MEMREAS_PAY_URL = "http://127.0.0.1:55153/";
	const MEMREAS_PAY_URL_STRIPE = "http://127.0.0.1:55153/stripe/";
	const MEMREAS_PAY_URL_INDEX = "http://127.0.0.1:55153/index/";
	
	// Redis constant section
	const REDIS_SERVER_ENDPOINT = "127.0.0.1";
	const REDIS_SERVER_USE = true;
	const REDIS_SERVER_SESSION_ONLY = true;
	const REDIS_SERVER_PORT = "6379";
	// 1 hour
	const REDIS_CACHE_SESSION_TTL = 3600;
	// 1day
	const REDIS_CACHE_TTL = (3600 * 24);
	const REDIS_CACHE_SESSION_DEVICE_TTL = (3600 * 24);
	
	// memreasdevsec related
	const S3BUCKET = "memreasdevsec";
	const S3HLSBUCKET = "memreasdevhlssec";
	const S3_APPKEY = 'AKIAJZE2O2WDMXLGR27A';
	const S3_APPSEC = 'FI09T7vRXcWx+QBE/n5ysEtZxx/DOAxkks/o2rzG';
	const S3_REGION = 'us-east-1';
	const CLOUDFRONT_STREAMING_HOST = 'rtmp://s1u1vmosmx0myq.cloudfront.net/cfx/st/mp4:';
	const CLOUDFRONT_DOWNLOAD_HOST = 'https://d3sisat5gdssl6.cloudfront.net/';
	const SIGNURLS = true;
	
	// const CLOUDFRONT_HLSSTREAMING_HOST = 'https://d2b3944zpv2o6x.cloudfront.net/';
	const CLOUDFRONT_HLSSTREAMING_HOST = 'https://d2cbahrg0944o.cloudfront.net/';
	const CLOUDFRONT_KEY_FILE = '/key/pk-APKAISSKGZE3DR5HQCHA.pem';
	const CLOUDFRONT_KEY_PAIR_ID = 'APKAISSKGZE3DR5HQCHA';
	// 48 hours
	const CLOUDFRONT_EXPIRY_TIME = (3600 * 48); 
	
	// Same across...
	const registration_secret_passphrase = 'freedom tower';
	const media_id_batch_create_count = 50;
	const copyright_batch_create_count = 25;
	const copyright_batch_minimum = 10;
	const URL = "/index";
	const MEMREAS_TRANSCODER = true;
	const MEMREASDB = 'memreasintdb';
	const S3HOST = 'https://s3.amazonaws.com/';
	// 10 hour
	const EXPIRES = 36000;
	const DATA_PATH = "/data/";
	const MEDIA_PATH = "/media/";
	const IMAGES_PATH = "/images/";
	const USERIMAGE_PATH = "/media/userimage/";
	const FOLDER_PATH = "/data/media/";
	const FOLDER_AUDIO = "upload_audio";
	const FOLDER_VIDEO = "uploadVideo";
	const VIDEO = "/data/media/uploadVideo";
	const AUDIO = "/data/media/upload_audio";
	const ADMIN_EMAIL = 'admin@memreas.com';
	const GCM_SERVER_KEY = 'AIzaSyAt0YAt_Nb9Q4zjaU0-epWeTejUVVh8lDI';
	//const FCM_SERVER_URL = 'https://android.googleapis.com/gcm/send';
	const FCM_SERVER_URL = 'https://fcm.googleapis.com/fcm/send';
	const APNS = 'aps_pkey.pem';
	const APNS_GATEWAY = 'ssl://gateway.sandbox.push.apple.com:2195';
	const COPYRIGHT = '&copy;memreas, llc. all rights reserved.';
	const DCMA_CLAIM = 2;
	const DCMA_CLAIM_TEXT = 'claim reported';
	const DCMA_COUNTER_CLAIM = 3;
	const DCMA_COUNTER_CLAIM_TEXT = 'counter claim reported';
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
	
	// not used
	const FB_APPID = '462180953876554';
	const FB_SECRET = '23dcd2db19b17f449f39bfe9e93176e6';
	// const FB_FBHREF = 'https://apps.facebook.com/462180953876554/';
	const FB_FBHREF = ' /index/canvas';
	const TW_CONSUMER_KEY = '9jwg1vX4MgH7rfBzxqkcjI90f';
	const TW_CONSUMER_SECRET = 'bDqOeHkJ7OIQ4QPNnT1PA9oz55gf51YW0REBo12aazGA0CBrbY';
	const TW_OAUTH_TOKEN = '1941271416-UuUhh7XTVJ7npEjmgQHAypAnl0VmNqOKJ7BzMp2';
	const TW_OAUTH_TOKEN_SECRET = 't0wqWd0OpHrZTWYHvx9VqVl3iySDTfZklKkB6v1WaohxH';
}