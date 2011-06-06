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
}