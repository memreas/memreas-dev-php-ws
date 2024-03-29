<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class MemreasStringsWS {
	
	// set locale
	public static $locale = '_EN';
	
	// en section
	// dmca
	const DMCA_VIOLATION_REPORTED_PRIOR_EN = 'DMCA Violation reported prior for this media id.';
	const DMCA_VERIFY_EMAIL_EN = 'Please enter valid email address.';
	const DMCA_MEDIA_NOT_FOUND_EN = 'We could not find the media specified.';
	const DMCA_AGREE_TO_TERMS_EN = 'Please review and accept our terms.';
	
	// email general
	const EMAIL_SIGNATURE = 'Best Regards,';
	const EMAIL_EMAIL_ADDRESS = 'email address';
	const EMAIL_HEADER_TO = 'To';
	
	// user comment email
	const EMAIL_USER_COMMENT_BODY = 'has added a new comment to your';
	
	//registration
	const EMAIL_ALREADY_EXISTS = 'This email already exists. Please check your info.';
	const USERNAME_ALREADY_EXISTS = 'This username already exists. Please check your info.';
	const USERNAME_EMAIL_ALREADY_EXISTS = 'This username and email already exists. Please check your info.';
	
	// register email
	const EMAIL_REGISTER_WELCOME = 'Welcome to memreas!';
	const EMAIL_REGISTER_BODY = 'Thanks for joining! Please click the following link to verify your';
	
	// notifications
	const NOTIFICATION_ADD_FRIEND = 'friend request from @';
	static function getString($string) {
		return constant ( 'self::' . $string . self::$locale );
	}
}