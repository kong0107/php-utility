<?php
/**
 * System program execution
 * @see https://www.php.net/manual/en/book.exec.php
 */

/**
 * Simple wrapper for `proc_open()` to `proc_close()`
 * @param array|string $cmd
 * @param array|string|null $out = null File path or arg list of file_put_contents for stdout to write on
 * @param array|string|null $err = null File path or arg list of file_put_contents for stderr to write on
 * @return array|false Array of [exit code, stdout, stderr]; or False if failed.
 */
function proc_wrap($cmd, $out = null, $err = null) {
	if (is_array($cmd)) {
		if (!count($cmd)) throw new ValueError('command array cannot be empty');
		if (PHP_VERSION_ID < 70400) $cmd = implode(' ', $cmd);
	}
	else if (!$cmd) throw new InvalidArgumentException('command cannot be empty');

	if ($out && !is_string($out) && !array_is_list($out)
		|| $err && !is_string($err) && !array_is_list($err)
	) throw new InvalidArgumentException('proc_wrap() accepts arguments as strings or lists.');

	$process = proc_open(
		$cmd,
		array(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w')
		),
		$pipes
	);

	if (!is_resource($process)) return false;
	fclose($pipes[0]);
	$result = array(null, '', '');

	stream_set_blocking($pipes[1], false);
	stream_set_blocking($pipes[2], false);

	while (true) {
		$read = array_slice($pipes, 1);
		$w = $e = NULL;
		if (stream_select($read, $w, $e, 1) === false) break;
		foreach ($read as $r) {
			$chunk = fread($r, 8192);
			if ($r === $pipes[1]) $result[1] .= $chunk;
			else if ($r === $pipes[2]) $result[2] .= $chunk;
		}

		if (!proc_get_status($process)['running']) break;
	}

	fclose($pipes[1]);
	fclose($pipes[2]);
	$result[0] = proc_close($process);

	if ($out) {
		if (is_string($out)) file_put_contents($out, $result[1]);
		else file_put_contents($out[0], $result[1], ...array_slice($out, 1));
	}
	if ($err) {
		if (is_string($err)) file_put_contents($err, $result[2]);
		else file_put_contents($err[0], $result[2], ...array_slice($err, 1));
	}

	return $result;
}
