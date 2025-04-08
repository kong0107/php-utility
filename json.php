<?php
/**
 * JavaScript Object Notation
 * @see https://www.php.net/manual/book.json.php
 */


/**
 * Force some flags of `json_encode()`, and convert every 4 spaces at the beginning of each line into tab characters.
 * @param mixed $value
 * @param int $flags
 * @param int $depth
 * @return string|false
 */
function json_encode_pretty($value, $flags = 0, $depth = 512) {
	$json = json_encode($value, $flags | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth);
	if ($json === false) return false;
	return preg_replace_callback(
		'/\n((?:    )+)/',
		function ($matches) {
			return "\n" . str_repeat("\t", strlen($matches[1]) / 4 - 1);
		},
		$json
	) ?? false;
}


/**
 * Encode things into valid JavaScript code but not legal JSON by skipping quotations in keys.
 * @param mixed $value
 * @param int $flags `JSON_UNESCAPED_UNICODE` and `JSON_UNESCAPED_SLASHES` are always set.
 * @param int $depth
 * @return string|false
 */
function json_encode_fake($value, $flags = 0, $depth = 512) {
	switch (gettype($value)) {
		case 'boolean':
			return $value ? 'true' : 'false';
		case 'integer':
		case 'double':
			return (string) $value;
		case 'array': {
			if (! $depth) return false;
			$inner = array();
			if (array_is_list($value)) {
				foreach ($value as $v) {
					$v = json_encode_fake($v, $flags, $depth - 1);
					if ($v === false) return false;
					$inner[] = $v;
				}
				return '[' . implode(', ', $inner) . ']';
			}
			foreach ($value as $k => $v) {
				if (is_string($k) && ! preg_match('/^[A-Za-z_$][\w$]*$/', $k))
					$k = json_encode($k, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$v = json_encode_fake($v, $flags, $depth - 1);
				if ($v === false) return false;
				$inner[] = "$k: $v";
			}
			return '{' . implode(', ', $inner) . '}';
		}
		case 'NULL':
			return 'null';
		default:
			return json_encode($value, $flags | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth - 1);
	}
}


/**
 * Decodes a JSON file
 * @param string $filepath
 * @param mixed[] $json_args Rest arguments for `json_decode()`
 * @param mixed[] $file_args Rest arguments for `file_get_contents()`
 * @return mixed `NULL` if `filepath` is not readable
 */
function json_file_read($filepath, $json_args = array(), $file_args = array()) {
	if (! is_readable($filepath)) return null;
	return json_decode(file_get_contents($filepath, ...$file_args), ...$json_args);
}


/**
 * Writes JSON string into a file
 * @param string $filepath
 * @param mixed $value
 * @param mixed[] $json_args Rest arguments for `json_encode()`
 * @param mixed[] $file_args Rest arguments for `file_put_contents()`
 * @return int|false bytes written
 */
function json_file_write(
	$filepath,
	$value,
	$json_args = array(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
	$file_args = array()
) {
	return file_put_contents($filepath, json_encode($value, ...$json_args), ...$file_args);
}


/**
 * Safely get a property from an JSON file
 * @param string $filepath
 * @param string $key
 * @param mixed[] $json_args Rest arguments for `json_decode()`
 * @param mixed[] $file_args Rest arguments for `file_get_contents()`
 * @return mixed
 */
function json_file_get($filepath, $key, $json_args = array(), $file_args = array()) {
	$data = json_file_read($filepath, $json_args, $file_args);
	if (! $data) return null;
	return $data->$key ?? null;
}


/**
 * Safely set or unset a property within an JSON file; created the file if not exists
 * @param string $filepath
 * @param string $key
 * @param mixed $value
 * @return int|false size of the file
 */
function json_file_set($filepath, $key, $value = null) {
	$data = json_file_read($filepath) ?? array();
	if ($value === null) unset($data[$key]);
	else $data[$key] = $value;
	return json_file_write($filepath, $data);
}
