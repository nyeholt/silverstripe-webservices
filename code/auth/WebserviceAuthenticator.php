<?php

/**
 * Manages authentication of a user for webservice access
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class WebserviceAuthenticator {
	
	private static $dependencies = array(
		'tokenAuthenticator'	=> '%$TokenAuthenticator',
		// uncomment this to enable a basic hmac validator
		// 'hmacValidator'			=> '%$WebserviceMethodHmacValidator',
	);

	/**
	 * Disable all public requests by default; If this is 
	 * set to true, services must still explicitly allow public access
	 * on those services that can be called by non-auth'd users. 
	 *
	 * @var boolean
	 */
	public $allowPublicAccess = false;
	
	
	/**
	 * Whether allowing access to the API by passing a security ID after
	 * logging in. 
	 *
	 * @var boolean
	 */
	public $allowSecurityId = true;
	
	/**
	 *
	 * @var TokenAuthenticator
	 */
	public $tokenAuthenticator;

	/**
	 * Optionally set an hmac validator if you want to require hmac auth on 
	 * the messages. 
	 * 
	 * @var HmacValidator
	 */
	public $hmacValidator;

	public function authenticate(SS_HTTPRequest $request) {
		$token = $this->getToken($request);

		$user = null;

		if ((!Member::currentUserID() && !$this->allowPublicAccess) || $token) {
			if (!$token) {
				throw new WebServiceException(403, "Missing token parameter");
			}
			$user = $this->tokenAuthenticator->authenticate($token);
			if (!$user) {
				throw new WebServiceException(403, "Invalid user token");
			}
		} else if ($this->allowSecurityId && Member::currentUserID()) {
			// we check the SecurityID parameter for the current user
			$secParam = SecurityToken::inst()->getName();
			$securityID = $request->requestVar($secParam);
			if ($securityID && ($securityID != SecurityToken::inst()->getValue())) {
				throw new WebServiceException(403, "Invalid security ID");
			}
			$user = Member::currentUser();
		} 
		
		if (!$user && !$this->allowPublicAccess) {
			throw new WebServiceException(403, "Invalid request");
		}

		// now, if we have an hmacValidator in place, use it
		if ($this->hmacValidator && $user) {
			if (!$this->hmacValidator->validateHmac($user, $request)) {
				throw new WebServiceException(403, "Invalid message");
			}
		}

		return true;
	}
	
	protected function getToken(SS_HTTPRequest $request) {
		$token = $request->requestVar('token');
		if (!$token) {
			$token = $request->getHeader('X-Auth-Token');
		}
		
		return $token;
	}
}
