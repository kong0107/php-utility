<?php
/**
 * Session Handling
 * @see https://www.php.net/manual/book.session.php
 */


/**
 * Force to start session
 * @param array $options
 * @param int|string|Throwable|callable $exception
 * 		If an integer or a string, used for `exit()`.
 * 		Otherwise `exception` would be thrown or called.
 * @return true|never
 */
function assert_session_start($options = array(), $exception = 0) {
	switch ($status = session_status()) {
		case PHP_SESSION_ACTIVE:
			return true;
		case PHP_SESSION_NONE:
			if (session_start($options)) return true;
			// no break
		case PHP_SESSION_DISABLED:
			if (is_callable($exception)) {
				$exception($status);
				return $status;
			}
			if ($exception instanceof Throwable)
				throw $exception;
			exit($exception);
	}
}
