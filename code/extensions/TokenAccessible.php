<?php

/**
 * Adds token-based access for users.
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TokenAccessible extends DataObjectDecorator {
	public function extraStatics() {
		return array(
			'db'			=> array(
				'Token'			=> 'Varchar(32)',
				'Active'		=> 'Boolean',
			)
		);
	}
	
	public function onBeforeWrite() {
		if (!$this->owner->Token) {
			$this->owner->Token = md5(md5(uniqid() . mt_rand(0, 9000000)) . md5(microtime(true) . uniqid() . mt_rand(0, 9000000)));
		}
	}
}