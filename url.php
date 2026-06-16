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
 * @param string $token
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
 * Encodes data into JWT
 * @todo does not work yet!!!
 */
function jwt_encode(
	string $algo,
	mixed $payload,
	string $secret
) : string {
	$str = base64url_encode('{"alg":"' . $algo . '","typ":"JWT"}')
		. '.'
		. base64url_encode(is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
	;
	openssl_sign($str, $signature, $secret, $algo); ///< todo
	return "$str.$signature";
}


/**
 *
 */
function parse_mime_type(
	string $mime_type,
	bool $associative = false
) : array|false {
	$pattern = implode('', array(
		'#^([^\s/;=,]+)',
		'/([^\s/;=,]+?)',
		'(?:\+([^\s/;=,+]+))?',
		'(?:\s*;(.+))?$#'
	));
	if (!preg_match($pattern, $mime_type, $matches)) return false;
	list (, $type, $subtype, $suffix, $para_list) = $matches;

	$parameters = null;
	if ($para_list) {
		preg_match_all(
			'/([^\s\/;=,]+)\s*=\s*(?:"((?:[^"\\]|\\.)*)"|([^\s\/;=,]+))/',
			$para_list, $matches, PREG_SET_ORDER
		);
		$parameters = array();
		foreach ($matches as $match) {
			$key = strtolower($match[1]);
			if (str_ends_with($key, '*')
				&& preg_match("/^([^']+)''(.+)$/", $match[3], $v_match)
			) {
				/// for "filename*=UTF-8''%E6%B8%AC%E8%A9%A6.txt"
				$key = substr($key, 0, -1);
				$value = rawurldecode($v_match[2]);
				if (strcasecmp($v_match[1], 'UTF-8'))
					$value = mb_convert_encoding($value, 'UTF-8', $v_match[1]);
			}
			else $value = $match[3] ?: stripcslashes($match[2] ?? '');
			$parameters[$key] = $value;
		}
	}

	$result = array(
		'type' => $type,
		'subtype' => $subtype,
		'facets' => explode('.', $subtype),
		'suffix' => $suffix,
		'parameters' => $parameters
	);
	return $associative ? $result : (object) $result;
}


/**
 * Parse a Data URL and return its components
 * @see https://developer.mozilla.org/en-US/docs/Web/URI/Reference/Schemes/data
 * @throws ValueError
 */
function parse_dataurl(
	string $url,
	bool $associative = false
) : array|object {
	if (preg_match('/^data:([^;=,]+)(;base64)?,/', $url, $matches)) {
		$raw = substr($url, strlen($matches[0]));
		$info = parse_mime_type($matches[1]);
		$info['mime'] = $matches[1];
		$info['data'] = $matches[2] ? base64_decode($raw) : rawurldecode($raw);
		return $associative ? $info : (object) $info;
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

	$pos = strpos($url, '?');
	if ($pos !== false) $url = substr($url, 0, $pos);

	parse_str($parts['query'] ?? '', $old_params);
	$params = array_merge($old_params, $new_params);

	return $url .= '?' . http_build_query($params);
}
