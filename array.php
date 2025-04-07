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


/**
 * Imitate JavaScript's `Array.prototype.map`
 * @param ?callable(mixed, int|string): mixed $callback
 * @param array $array
 * @return array
 */
function array_map_with_key($callback, $array) {
	return array_map($callback, array_values($array), array_keys($array));
}


/**
 * Imitate JavaScript's `Array.prototype.reduce`, except the case `initial` is absent
 * @param array $array
 * @param callable(mixed, mixed, int|string): mixed
 * @param mixed $initial If absent, use PHP's `array_reduce()` behavior
 * @return mixed
 */
function array_reduce_with_key($array, $callback, $initial = null) {
	$accumulator = $initial;
	foreach ($array as $key => $value)
		$accumulator = call_user_func($callback, $accumulator, $value, $key);
	return $accumulator;
}
