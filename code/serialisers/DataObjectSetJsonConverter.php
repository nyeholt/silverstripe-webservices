<?php

/**
 * Used to convert a data object to a json object
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class DataObjectSetJsonConverter {
	
	public function convert(DataObjectSet $set) {
		$ret = new stdClass();
		$ret->items = array();
		foreach ($set as $item) {
			$ret->items = $item->toMap();
		}

		return Convert::raw2json($ret);
	}
}
