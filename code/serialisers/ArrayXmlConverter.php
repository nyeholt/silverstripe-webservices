<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class ArrayXmlConverter {
	public function convert($array, $controller) {
		$converter = new ArrayToXml('items');
		return $converter->convertArray($array);
	}
}

class ArrayToXml {
	
	public function __construct($name = 'items') {
		$this->name = $name;
	}
	
	public function convertArray($data) {
		return "<$this->name>" . $this->convert($data) . "</$this->name>";
	}
	
	public function convert($value) {
		if (is_scalar($value) || is_null($value)) {
			return Convert::raw2xml($value);
		} else {
			$bits = array();
			foreach ($value as $key => $itemVal) {
				if (is_int($key)) {
					$elem = 'item';
				} else {
					$elem = $key;
				}

				$bits[] = "<$elem>" . $this->convert($itemVal) ."</$elem>";
			}
			return implode($bits);
		}
	}
}