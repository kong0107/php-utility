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
 * Redirect browser to the target, either before or after headers sent, either to internal or external destination.
 * @todo Auto-encode to prevent XSS (cross-site scripting) and ensure the header to be legal.
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
