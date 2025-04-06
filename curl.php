<?php
/**
 * Client URL Library
 * @see https://www.php.net/manual/book.curl.php
 */


/**
 * Imitate `fetch()` in JavaScript
 * @todo support file by using CURLFile
 * @param string $url
 * @param array $options
 * @return array
 */
function curl_fetch($url, $options) {
	$curl_opts = array(
		CURLOPT_URL => $url,
		CURLOPT_RETURNTRANSFER => true,
	    CURLOPT_FOLLOWLOCATION => true,
	    CURLOPT_HEADER => true
	);

	$body = $options['body'] ?? '';
	if (is_array($body)) $body = http_build_query($body);

	$method = strtoupper($options['method'] ?? 'GET');
	switch ($method) {
		case 'POST': {
			$curl_opts[CURLOPT_POST] = true;
			if ($body) $curl_opts[CURLOPT_POSTFIELDS] = $body;
			break;
		}
		case 'PUT':
		case 'DELETE': {
			$curl_opts[CURLOPT_CUSTOMREQUEST] = $method;
			if ($body) $curl_opts[CURLOPT_POSTFIELDS] = $body;
			break;
		}
		case 'GET': {
			if ($body)
				$curl_opts[CURLOPT_URL] .= (strpos($url, '?') ? '&' : '?') . $body;
			break;
		}
		default: throw new InvalidArgumentException("unknown method $method");
	}

	if (isset($options['headers'])) {
		/**
		 * In one of the following format
		 * * [['Content-Type', 'text/html'], ...]
		 * * ['Content-Type: text/html', ...]
		 * * {'Content-Type': 'text/html'}
		 */
		$req_headers = array();
		foreach ($options['headers'] as $key => $value) {
			if (is_numeric($key)) {
				if (is_string($value)) $req_headers[] = $value;
				else $req_headers[] = "$value[0]: $value[1]";
			}
			else $req_headers[] = "$key: $value";
		}
		$curl_opts[CURLOPT_HTTPHEADER] = $req_headers;
	}

	foreach ($options as $index => $value) {
		if (is_numeric($index)) $curl_opts[intval($index)] = $value;
		else if (! in_array($index, array('method', 'headers', 'body')))
			trigger_error("unsupported option $index", E_USER_NOTICE);
	}

	$ch = curl_init();
	curl_setopt_array($ch, $curl_opts);
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	$errno = curl_errno($ch);
	curl_close($ch);

	$result = array('info' => $info);
	if ($errno) {
		$result['errno'] = $errno;
		$result['error'] = curl_strerror($errno);
	}
	if (! $response) return $result;

	if ($curl_opts[CURLOPT_RETURNTRANSFER]) {
		if ($curl_opts[CURLOPT_HEADER]) {
			$header_raw = substr($response, 0, $info['header_size']);
			$result['headers'] = array_map(
				fn ($h) => rtrim(preg_replace('/\r\n\s/', ' ', $h)),
				preg_split('/\r\n(?!\s)/', rtrim($header_raw))
			);
			$result['body'] = substr($response, $info['header_size']);
		}
		else $result['body'] = $response;
	}

	return $result;
}
