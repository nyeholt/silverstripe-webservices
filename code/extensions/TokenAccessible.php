<?php

/**
 * Adds token-based access for users.
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TokenAccessible extends DataExtension {
	public static $db = array(
		'Token'				=> 'Varchar(32)',
		'Active'			=> 'Boolean',
		'AuthPrivateKey'	=> 'Varchar(64)',
	);
	
	public function onBeforeWrite() {
		if (!$this->owner->Token) {
			$this->owner->Token = md5(md5(uniqid() . mt_rand(0, 9000000)) . md5(microtime(true) . uniqid() . mt_rand(0, 9000000)));
		}

		if (!$this->owner->AuthPrivateKey) {
			$this->owner->AuthPrivateKey = md5(microtime(true) . uniqid() . mt_rand(0, 9100000)) . md5(uniqid() . mt_rand(0, 900000));
		}
	}
}