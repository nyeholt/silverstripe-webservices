<?php

/**
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class WebserviceMethodHmacValidator {
	
	/**
	 * Verify whether the given user/request has a valid HMAC header
	 * 
	 * HMAC should be calculated as a concatenation of 
	 * 
	 * service name
	 * method called
	 * gmdate in format YmdH
	 * 
	 * So an example before hashing would be
	 * 
	 * product-getPrice-20130225
	 * 
	 * The key used for signing should come from the user's "AuthPrivateKey" field
	 * 
	 * The validator will accept an hour either side of 'now'
	 * 
	 * @param type $user
	 * @param SS_HTTPRequest $request
	 * @return boolean
	 */
	public function validateHmac($user, SS_HTTPRequest $request) {
		$service = $request->param('Service');
		$method = $request->param('Method');
		$hmac = $request->getHeader('X-Silverstripe-Hmac');
		
		$key = $user->AuthPrivateKey;
		
		if (!strlen($key)) {
			return false;
		}
		
		$times = array(
			gmdate('YmdH', strtotime('-1 hour')),
			gmdate('YmdH'),
			gmdate('YmdH', strtotime('+1 hour')),
		);
		
		foreach ($times as $time) {
			$message = $this->generateHmac(array($service, $method, $time), $key);
			if ($message == $hmac) {
				return true;
			}
		}

		return false;
	}
	
	public function generateHmac($args, $key) {
		$msg = implode('-', $args);
		return hash_hmac('sha1', $msg, $key);
	}
}
