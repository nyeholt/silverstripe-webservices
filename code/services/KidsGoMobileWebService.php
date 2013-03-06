<?php

/**
 * An example web service. 
 * 
 * Note that it is NOT necessary to declare both WebServiceable AND
 * webEnabledMethods; it's just done for completeness
 * 
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class DummyWebService implements WebServiceable {
	
	/**
	 * Something that silverstripe imposes!
	 */
	public function __construct() {
		
	}
	
	public function webEnabledMethods() {
		return array(
			'myMethod'		=> 'GET',
		);
	}

	public function myMethod($param) {
		return array(
			'SomeParam'			=> 'Goes here',
			'Boolean'			=> true,
			'Return'			=> $param,
		);
	}
}