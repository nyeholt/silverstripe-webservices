<?php

/**
 * Description of ArrayJsonConverter
 *
 * @author Marcus Nyeholt <marcus@silverstripe.com.au>
 */
class ArrayJsonConverter {
	public function convert($array, $controller) {
		$isMap = false;
		$vals = array();
		foreach ($array as $key => $value) {
			if (!is_int($key)) {
				$isMap = true;
			}
			
			$vals[] = $controller->convertResponse($value);
			
		}

		if (!$isMap) {
			$retString = rtrim(implode(",", $vals), ',');
			return '[' . $retString . ']'; 
		}

		// otherwise, we need to go through and do a key/val pairing
		$keys = array_keys($array);
		$ret = array();
		for ($i = 0, $c = count($keys); $i < $c; $i++) {
			$ret[] = '"' . str_replace('"', '\"', $keys[$i]). '": ' . $vals[$i];
		}

		$retString = rtrim(implode(",", $ret), ',');
		return '{ ' . $retString . ' }';
	}
}