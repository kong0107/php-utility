<?php
/**
 * PHP Options and Information
 * @see https://www.php.net/manual/book.info.php
 */


/**
 * Activate/deactive what `php.ini` may behave, even for those not
 * @see https://www.php.net/manual/en/function.ini-set.php
 * @param string $option
 * @param mixed $value
 * @return mixed
 */
function safe_ini_set($option, $value) {
	$old = ini_get($option);
	switch ($option) {
		case 'error_reporting':
			return error_reporting(is_numeric($value) ? $value : constant($value));
		case 'date.timezone':
			return date_default_timezone_set($value)
				? date_default_timezone_get()
				: false;
		case 'session.name':
			return session_name($value);
		case 'session.save_path':
			return session_save_path($value);
		case 'output_buffering': {
			if (! $value || ob_get_level() > 1) return $old;
			return ob_start(null, is_bool($value) ? 0 : $value) ? $old : false;
		}
		case 'implicit_flush':
			ob_implicit_flush($value);
			return $old;
		case 'expose_php': {
			if ($old) {
				if (! $value) header_remove('X-Powered-By');
			}
			else if ($value) header('X-Powered-By: PHP/' . PHP_VERSION);
			return $old;
		}
		case 'opcache.enable': {
			/// Except in `php.ini`, this can only be disabled.
			if ($value) return $old;
			return ini_set('opcache.enable', '0');
		}
		default:
			return ini_set($option, $value);
	}
}
