<?php


Director::addRules(100, array(
	'jsonservice/$Service/$Method'			=> 'WebServiceController',
	'xmlservice/$Service/$Method'			=> 'WebServiceController',
));

Object::add_extension('Member', 'TokenAccessible');
