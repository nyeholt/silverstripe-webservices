<?php


Director::addRules(20, array(
	'jsonservice/$Service/$Method'			=> 'JsonServiceController',
));


Object::add_extension('Member', 'TokenAccessible');
