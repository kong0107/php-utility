<?php
/**
 * Arrays
 * @see https://www.php.net/manual/book.array.php
 */


/**
 * Remove null elements in an array in place, recursively
 * @param array &$array
 * @return int Number of elements removed
 */
function array_remove_null(&$array) {
	$count = 0;

	if (function_exists('array_is_list')) {
		$is_list = array_is_list($array);
	}
	else {
		$is_list = true;
		$length = count($array);
		for ($i = 0; $i < $length; ++$i) {
			if (! array_key_exists($i, $array)) {
				$is_list = false;
				break;
			}
		}
	}

	foreach ($array as $key => &$child) {
		if (is_array($child)) {
			$count += array_remove_null($child);
		}
		else if (is_null($child)) {
			unset($array[$key]);
			++$count;
		}
	}
	if ($is_list) $array = array_values($array);
	return $count;
}
