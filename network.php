<?php
/**
 * Network
 * @see https://www.php.net/manual/book.network.php
 */


/**
 * Send a cookie with different default options. `SameSite` is supported even before PHP 7.3.
 * @param string $name Same as original; illegal characters throw Exception.
 * @param string $value	Same as original, which auto-uses `rawurlencode()`
 * @param int $max_age
 * 	Instead of `Expires`, `Max-Age` is used here.
 * 	Minus value deletes it; zero makes it live as session (aka until browser close).
 * @param string $path Auto-encoded (illegal characters in `Path` make `setcookie()` fatal error)
 * @param string $domain = '' Same as original
 * @param bool $secure Opposite as original
 * @param bool $httponly Opposite as original
 * @param ?string $samesite = 'Lax'
 * @throws RuntimeException|InvalidArgumentException
 * @return string The header sent
 */
function set_cookie(
	$name,
	$value = '',
	$max_age = -1,
	$path = '',
	$domain = '',
	$secure = true,
	$httponly = true,
	$samesite = 'Lax'
) {
	if (headers_sent())
		throw new RuntimeException('Headers already sent, cannot set cookie.');
	if (! preg_match('/^[\w\-\.]+$/', $name))
		throw new InvalidArgumentException("Invalid character used in cookie name.");

	$header = "Set-Cookie: $name=" . rawurlencode($value);
	if ($max_age) $header .= "; Max-Age=$max_age";
	if ($path) $header .= '; Path=' . str_replace('%2F', '/', urlencode($path));
	if ($domain) $header .= "; Domain=$domain";
	if ($secure) $header .= '; Secure';
	if ($httponly) $header .= '; HttpOnly';
	if ($samesite) $header .= "; SameSite=$samesite";
	header($header, false);
	return $header;
}


/**
 * Delete cookies even without knowing its options.
 * @param string ...$names Names of cookies to delete. Empty for removing all cookies.
 * @return int Amount of cookies deleted.
 */
function delete_cookies_dirty(...$names) {
	if (empty($_COOKIE)) return 0;
	if (! count($names)) $names = array_keys($_COOKIE);
	else $names = array_intersect($names, array_keys($_COOKIE));
	if (! count($names)) return 0;

	$paths = array('', '/');
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$path_parts = array_filter(explode('/', substr($path, 1)));
	for ($i = 1; $i < count($path_parts); ++$i) {
		$part = implode('/', array_slice($path_parts, 0, $i));
		array_push($paths, "/$part", "/$part/");
	}

	if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $_SERVER['HTTP_HOST'])) // IPv4
		$domains = array('', $_SERVER['HTTP_HOST']);
	else {
		$domains = array('');
		$domain_parts = explode('.', $_SERVER['HTTP_HOST']);
		for ($i = 0; $i < count($domain_parts); ++$i)
			$domains[] = implode('.', array_slice($domain_parts, $i));
	}

	foreach ($names as $name) {
		foreach ($paths as $path) {
			foreach ($domains as $domain) {
				foreach (array(false, true) as $secure) {
					foreach (array(false, true) as $httponly) {
						foreach (array(null, 'None', 'Lax', 'Strict') as $samesite) {
							set_cookie($name, '', -1, $path, $domain, $secure, $httponly, $samesite);
						}
					}
				}
			}
		}
	}
	return count($names);
}


/**
 * Redirect browser to the target, either before or after headers sent, either to internal or external destination.
 * @todo Auto-encode to prevent XSS (cross-site scripting) and ensure the header to be legal.
 * @todo Make this work even in `<script>`, `<textarea>`, or some HTML attributes.
 * @param string $url
 * @param int $status HTTP status code, only used if headers are not sent yet.
 * @param ?string $js_method Method of `window.location` to be called in JavaScript.
 * 	Null for auto: use `assign()` on external url; use `replace()` on internal url.
 * @return never
 */
function redirect($url, $status = 302, $js_method = null) {
	if (! headers_sent()) {
		header("Location: $url", true, $status);
		exit(0);
	}
	while (ob_get_level()) ob_end_clean();
	echo '<meta http-equiv="refresh" content="0; url=' . $url . '">';

	if (! $js_method) {
		if (str_starts_with($url, 'https://')
			&& ! str_starts_with(substr($url, 8), $_SERVER['HTTP_HOST'])
		) $js_method = 'assign';
		else $js_method = 'replace';
	}
	echo "<script>location.$js_method('$url');</script>";
	exit(1);
}


/**
 * Send `Last-Modified` header by the maximum mtime of files specified;  if that matches `If-Modified-Since`, then response 304 and exit.
 * @param int|string ...$args Int for timestamp in seconds; string for filepath whose mtime would be used.
 * @return never|int Exit if `If-Modified-Since` matches; otherwise the new timestamp is returned.
 */
function http_304_if_unmodified(...$args) {
	$max = 0;
	if (! count($args)) return 0;
	foreach ($args as $arg) {
		if (is_string($arg)) $arg = filemtime($arg) ?: 0; // E_WARNING occurs if file not exists
		if ($max < $arg) $max = $arg;
	}
	$last_modified = gmdate(DATE_RFC7231, $max);
	header("Last-Modified: $last_modified");

	if ($last_modified === ($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? 0)) {
		http_response_code(304);
		exit(0);
	}
	return $max;
}
