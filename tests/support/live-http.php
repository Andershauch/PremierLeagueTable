<?php

/**
 * Real-network `wp_remote_get` used only by tests/live/*.php. Requires the
 * openssl extension for HTTPS; if it's not loaded, requests fail cleanly with
 * a WP_Error rather than a fatal, so the live suite can soft-skip instead of
 * hard-failing on an environment that simply can't make outbound HTTPS calls.
 */

if (! function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = [])
    {
        if (! extension_loaded('openssl') && strpos($url, 'https://') === 0) {
            return new WP_Error('http_request_failed', 'openssl extension not loaded; cannot make HTTPS requests.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: Mozilla/5.0\r\n",
                'timeout' => $args['timeout'] ?? 12,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $matches) === 1) {
                    $status = (int) $matches[1];
                }
            }
        }

        if ($body === false) {
            $error = error_get_last();

            return new WP_Error('http_request_failed', $error['message'] ?? 'request failed');
        }

        return [
            'response' => ['code' => $status],
            'body' => $body,
        ];
    }
}
