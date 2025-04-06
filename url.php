<?php
/**
 * URLs
 * @see https://www.php.net/manual/book.url.php
 */


/**
 * Decodes data encoded with base64url
 * @param string $string
 * @return string
 */
function base64url_decode($string) {
	return base64_decode(str_pad(
	    strtr($string, '-_', '+/'),
	    strlen($string) % 4,
	    '='
	));
}


/**
 * Encodes data with base64url
 * @param string $string
 * @return string
 */
function base64url_encode($string) {
	return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}


/**
 * Decodes data encoded with JWT
 * @param string $string
 * @param bool $associative whether returns an associative array or an object.
 * @return array|object
 */
function jwt_decode($token, $associative = false) {
	$parts = explode('.', $token);
	$result = array(
	    'header' => json_decode(base64url_decode($parts[0])),
	    'payload' => json_decode(base64url_decode($parts[1]))
	);
	return $associative ? $result : (object) $result;
}


/**
 * Parse a Data URL and return its components
 * @todo support mime type with appending options
 * @todo support input not encoded in base64
 * @see https://developer.mozilla.org/en-US/docs/Web/URI/Reference/Schemes/data
 * @param string $url
 * @throws ValueError
 * @return array
 */
function parse_dataurl($url) {
	if (preg_match('/^data:(\w+)\/([\w\.\-]+);base64,/', $url, $matches)) {
	    return array(
	        'type' => $matchs[0],
	        'subtype' => $matches[1],
	        'base64' => substr($url, strlen($matches[0]))
	    );
	}
	else throw new ValueError('not a data URL');
}


/**
 * Modify the given URL to which some query params are re-assigned.
 * @param array $new_params
 * @param string $url
 * @return string
 */
function rebuild_url($new_params, $url = '') {
	if (! $url) $url = $_SERVER['REQUEST_URI'];
	$parts = parse_url($url);
	if (! $parts) throw new InvalidArgumentException('The second argument must be a URL.');

	$pos = str_pos($url, '?');
	if ($pos !== false) $url = substr($url, 0, $pos);

	parse_str($parts['query'] ?? '', $old_params);
	$params = array_merge($old_params, $new_params);

	return $url .= '?' . http_build_query($params);
}
