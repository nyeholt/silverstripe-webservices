<?php

/**
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class WebserviceAuthenticatorTest extends SapphireTest {
	
	public function testAuthenticateUserToken() {
		$member = new Member();
		$member->Email = "test@test.com";
		$member->Password = "so encryption settings are used";
		
		$member->write();
		
		$this->assertNotNull($member->Token);
		$this->assertNotNull($member->AuthPrivateKey);
		
		$token = $member->ID . ":" . $member->userToken();
		
		// create an authenticator and see what we get back
		$tokenAuth = new TokenAuthenticator();
		$user = $tokenAuth->authenticate($token);
		
		$this->assertEquals($member->ID, $user->ID);
		
		$token = "42:" . $member->userToken();
		
		$user = $tokenAuth->authenticate($token);
		$this->assertNull($user);
	}
}
