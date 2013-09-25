<?php

/**
 * Adds token-based access for users.
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class TokenAccessible extends DataExtension {
	private static $db = array(
		'Active'			=> 'Boolean',
		'Token'				=> 'Varchar(128)',
		'AuthPrivateKey'	=> 'Varchar(128)',
		'RegenerateTokens'	=> 'Boolean',
	);
	
	public function onBeforeWrite() {
		
		if (!$this->owner->Token) {
			$this->owner->RegenerateTokens = true;
		}

		if (!$this->owner->AuthPrivateKey) {
			$this->owner->RegenerateTokens = true;
		}
	}

	public function updateCMSFields(\FieldList $fields) {
		parent::updateCMSFields($fields);
		
		$token = $this->userToken();
		
		if (!$token) {
			$token = "This user token can no longer be displayed - if you do not know this value, regenerate tokens by selecting Regenerate below";
		} else {
			$token = $this->owner->ID . ':' . $token;
		}

		$readOnly = ReadonlyField::create('DisplayToken', 'Token', $token);
		$fields->addFieldToTab('Root.Main', $readOnly, 'AuthPrivateKey');
		
		$field = $fields->dataFieldByName('AuthPrivateKey');
		$fields->replaceField('AuthPrivateKey', $field->performReadonlyTransformation());
		
		$fields->removeByName('Token');
	}
	
	public function onAfterWrite() {
		if ($this->owner->RegenerateTokens) {
			$this->owner->RegenerateTokens = false;
			$this->generateTokens();
			$this->owner->write();
		}
	}
	
	/**
	 * Generate and store the authentication tokens required
	 * 
	 * @TODO Rework this, it's not really any better than storing text passwords
	 */
	public function generateTokens() {
		$generator = new RandomGenerator();
		$token = $generator->randomToken('sha1');
		$this->owner->Token = $this->owner->encryptWithUserSettings($token);
		
		// store the new token so it can be displayed later
		Session::set('member_auth_token_' . $this->owner->ID, $token);

		$authToken = $generator->randomToken('whirlpool');
		$this->owner->AuthPrivateKey = $authToken;
	}
	
	public function userToken() {
		return Session::get('member_auth_token_' . $this->owner->ID);
	}
}
