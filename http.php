<?php

function http_request(
    string $url,
    array $wrapper
) : array {
    $context = stream_context_create(array('http' => $wrapper));
    $result = @ file_get_contents($url, false, $context);
    $res_headers = http_get_last_response_headers();

    if (empty($res_headers)) return array('ok' => false);

    $first = $res_headers[0];
    $status = intval(explode(' ', $first)[1]);
    $headers = array(
        'status' => $status,
        'http' => $first
    );
    foreach ($res_headers as $header) {
        $frags = explode(':', $header, 2);
        if (count($frags) < 2) continue;
        $headers[strtolower($frags[0])] = trim($frags[1]);
    }

    return array(
        'ok' => ($status === 200),
        'status' => $status,
        'headers' => $headers,
        'content' => $result
    );
}


function http_get(
    string $url,
    array $headers = array(),
    array $params = array(),
    float $timeout = 0 // https://tideways.com/profiler/blog/using-http-client-timeouts-in-php
) : array {
    $wrapper = array('method' => 'GET');
    if (count($headers)) $wrapper['header'] = $headers;
    if (count($params)) {
        $url .= (strpos($url, '?')) ? '&' : '?';
        $url .= http_build_query($params);
    }
    if ($timeout > 0) {
        $wrapper['timeout'] = $timeout;
        $wrapper['ignore_errors'] = true;
    }
    return http_request($url, $wrapper);
}


function http_post(
    string $url,
    array $headers = array(),
    string $content = ''
) : array {
    $wrapper = array('method' => 'POST');
    if (count($headers)) $wrapper['header'] = $headers;
    if ($content) $wrapper['content'] = $content;
    return http_request($url, $wrapper);
}


function http_post_multipart(
    string $url,
    array $headers,
    array $params
) : array {
    $boundary = base64_encode(random_bytes(51));

    $type_header = 'Content-Type: multipart/form-data; boundary=' . $boundary;
    foreach ($headers as $i => $header) {
        if (strpos(strtolower($header), 'content-type:') === 0) {
            $headers[$i] = $type_header;
            unset($type_header);
            break;
        }
    }
    if (isset($type_header)) $headers[] = $type_header;

    $content = '';
    foreach ($params as $part) {
        $content .= "--$boundary\r\n";

        $content .= "Content-Disposition: form-data; name=\"{$part['name']}\"";
        if (!empty($part['filename'])) $content .= "; filename=\"{$part['filename']}\"";
        $content .= "\r\n";

        if (!empty($part['type'])) $content .= "Content-Type: {$part['type']}\r\n";
        $content .= "\r\n{$part['value']}\r\n";
    }
    $content .= "--$boundary--\r\n";

    return http_post($url, $headers, $content);
}


/**
 * Combine curl_* functions into one to download file.
 * Origin functions need CURL_FILE and CURL_WRITEHEADER to be write stream;
 * here I support them to be strings containing filepath.
 */
function curl_download(
    string|array $options,
    string $dest = '',
    string $header_dest = ''
) : string|array|true {
    if (gettype($options) === 'string')
        $options = array(CURLOPT_URL => $options);
    if (empty($options[CURLOPT_URL])) throw new Exception;
    if (empty($options[CURLOPT_RETURNTRANSFER]) && empty($options[CURLOPT_FILE]) && ! $dest) throw new Exception;

    $default = array(
        CURLOPT_AUTOREFERER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_FORBID_REUSE => true
    );
    foreach ($default as $k => $v) // 不能用 array_merge ，因為 index 會被改變
        if (! array_key_exists($k, $options))
            $options[$k] = $v;

    if (isset($options[CURLOPT_FILE]) && gettype($options[CURLOPT_FILE]) === 'string')
        $dest = $options[CURLOPT_FILE];
    if ($dest) {
        $options[CURLOPT_RETURNTRANSFER] = false;
        $fh_file = fopen($dest, 'w');
        $options[CURLOPT_FILE] = $fh_file;
    }

    if (isset($options[CURLOPT_WRITEHEADER]) && gettype($options[CURLOPT_WRITEHEADER]) === 'string')
        $header_dest = $options[CURLOPT_WRITEHEADER];
    if ($header_dest) {
        // $options[CURLOPT_HEADER] = true; // this shouldn't be included; otherwise either CURLOPT_FILE or CURLOPT_RETURNTRANSFER would starts with response header.
        $fh_writeheader = fopen($header_dest, 'w');
        $options[CURLOPT_WRITEHEADER] = $fh_writeheader;
    }

    $ch = curl_init();
    curl_setopt_array($ch, $options);

    $result = curl_exec($ch);
    if ($result === false) $result = array(
        'info' => curl_getinfo($ch),
        'error' => curl_error($ch)
    );

    if (PHP_MAJOR_VERSION >= 8) unset($ch);
    else curl_close($ch);
    if (isset($fh_file)) fclose($fh_file);
    if (isset($fh_writeheader)) fclose($fh_writeheader);

    return $result;
}
