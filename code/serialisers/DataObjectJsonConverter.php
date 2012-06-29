<?php

/**
 * Used to convert a data object to a json object
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataObjectJsonConverter {
	
	public function convert(DataObject $object) {
		if ($object->hasMethod('toFilteredMap')) {
			return Convert::raw2json($object->toFilteredMap());
		} 
		return Convert::raw2json($object->toMap());
	}
}
