<?php

/**
 * Fixture-backed `wp_remote_get` for deterministic, offline unit tests.
 *
 * Register canned responses with `fixture_http_register($urlSubstring, $status, $bodyArrayOrJsonString)`
 * before exercising client code; `wp_remote_get()` matches the first registration whose
 * substring appears in the requested URL. Call `fixture_http_reset()` between tests.
 */

if (! isset($GLOBALS['__fixture_http_routes'])) {
    $GLOBALS['__fixture_http_routes'] = [];
}

function fixture_http_reset(): void
{
    $GLOBALS['__fixture_http_routes'] = [];
}

/**
 * @param array|string $body Either a PHP array (will be JSON-encoded) or a raw string body.
 */
function fixture_http_register(string $urlSubstring, int $status, $body): void
{
    $GLOBALS['__fixture_http_routes'][] = [
        'match' => $urlSubstring,
        'status' => $status,
        'body' => is_array($body) ? json_encode($body) : (string) $body,
    ];
}

if (! function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = [])
    {
        foreach ($GLOBALS['__fixture_http_routes'] as $route) {
            if (strpos($url, $route['match']) !== false) {
                return [
                    'response' => ['code' => $route['status']],
                    'body' => $route['body'],
                ];
            }
        }

        return new WP_Error(
            'fixture_http_no_route',
            'No fixture registered for URL: ' . $url
        );
    }
}
