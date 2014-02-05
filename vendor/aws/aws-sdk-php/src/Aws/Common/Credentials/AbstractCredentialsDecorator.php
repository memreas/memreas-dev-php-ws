<?php

/**
 * Copyright 2010-2013 Amazon.com, Inc. or its affiliates. All Rights Reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License").
 * You may not use this file except in compliance with the License.
 * A copy of the License is located at
 *
 * http://aws.amazon.com/apache2.0
 *
 * or in the "license" file accompanying this file. This file is distributed
 * on an "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either
 * express or implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
namespace Aws\Common\Credentials;

/**
 * Abstract credentials decorator
 */
class AbstractCredentialsDecorator implements CredentialsInterface {
	/**
	 *
	 * @var CredentialsInterface Wrapped credentials object
	 */
	protected $credentials;
	
	/**
	 * Constructs a new BasicAWSCredentials object, with the specified AWS
	 * access key and AWS secret key
	 *
	 * @param CredentialsInterface $credentials        	
	 */
	public function __construct(CredentialsInterface $credentials) {
		$this->credentials = $credentials;
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function serialize() {
		return $this->credentials->serialize ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function unserialize($serialized) {
		$this->credentials = new Credentials ( '', '' );
		$this->credentials->unserialize ( $serialized );
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getAccessKeyId() {
		return $this->credentials->getAccessKeyId ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getSecretKey() {
		return $this->credentials->getSecretKey ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getSecurityToken() {
		return $this->credentials->getSecurityToken ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function getExpiration() {
		return $this->credentials->getExpiration ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function isExpired() {
		return $this->credentials->isExpired ();
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function setAccessKeyId($key) {
		$this->credentials->setAccessKeyId ( $key );
		
		return $this;
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function setSecretKey($secret) {
		$this->credentials->setSecretKey ( $secret );
		
		return $this;
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function setSecurityToken($token) {
		$this->credentials->setSecurityToken ( $token );
		
		return $this;
	}
	
	/**
	 *
	 * @ERROR!!!
	 *
	 */
	public function setExpiration($timestamp) {
		$this->credentials->setExpiration ( $timestamp );
		
		return $this;
	}
}
