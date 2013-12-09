<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class DataObjectSetXmlConverter {
	public function convert($set) {
		$items = array();
		
		foreach ($set as $item) {
			if ($item instanceof Object && $item->hasMethod('toFilteredMap')) {
				$items[] = $item->toFilteredMap();
			} else if (method_exists($item, 'toMap')) {
				$items[] = $item->toMap();
			} else {
				$items[] = $item;
			}
		}

		$converter = new ArrayToXml('items');
		return $converter->convertArray($items);
	}
}
