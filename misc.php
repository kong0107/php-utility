<?php
/**
 * Miscellaneous Functions
 * @see https://www.php.net/manual/book.misc.php
 */


/**
 * Output plain text and terminate.
 * @param string $value
 * @param int $status
 * @return never
 */
function exit_text($value = '', $status = 0) {
	if (! headers_sent()) {
		if ($status) http_response_code($status);
		header('Content-Type: text/plain; charset=utf-8');
	}
	echo $value;
	exit($status < 400 ? 0 : 1);
}


/**
 * Output JSON and terminate.
 * @param mixed $value
 * @param int $status
 * @return never
 */
function exit_json($value, $status = 0) {
	if (! headers_sent()) {
		if ($status) http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
	}
	echo json_encode_pretty($value);
	exit($status < 400 ? 0 : 1);
}
