<?php

/**
 * Copyright (C) 2015 memreas llc. - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 */
namespace Application\Model;

class MemreasStringsWS {
	
	//set locale
	public static $locale = '_EN';
	
	//en section
	const DMCA_VIOLATION_REPORTED_PRIOR_EN = 'DMCA Violation reported prior for this media id.';
	const DMCA_VERIFY_EMAIL_EN = 'Please enter valid email address.';
	const DMCA_MEDIA_NOT_FOUND_EN = 'We could not find the media specified.';
	const DMCA_AGREE_TO_TERMS_EN = 'Please review and accept our terms.';
	
    static function getString($string) {
        return constant('self::'. $string . self::$locale);
    }
}