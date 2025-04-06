<?php
/**
 * Mathematical Functions
 * @see https://www.php.net/manual/book.math.php
 */


/**
 * Integer logarithm
 * @param int $num
 * @param int $base
 * @return int The maximum integer exponent which does not make `base` greater than `num`.
 */
function intlog($num, $base = 2) {
	if ($num < 1) throw new ValueError();
	$r = 0;
	for ($comp = 1; $comp < $num; $comp *= $base) ++$r;
	if ($comp === $num) return $r;
	return $r - 1;
}
