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
function kong_set_cookie(
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
