<?php

/**
 * Used to authenticate tokens and set the user into the session
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TokenAuthenticator {
	
	public function __construct () {}
	
	/**
	 * 
	 * @param String $token
	 */
	public function authenticate($token) {
		$user = DataObject::get_one('Member', '"Token" = \''.Convert::raw2sql($token).'\'');
		if ($user && $user->exists()) {
			$user->login(false);
			return $user;
		}
	}
}
