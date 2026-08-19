<?php

/**
 * Minimal stand-ins for the WordPress functions/constants the plugin classes
 * under test rely on. Intentionally not a WP test harness (no wp-load.php,
 * no database) — just enough surface area for the plugin's pure PHP classes
 * to run outside WordPress. HTTP (`wp_remote_get`) is deliberately NOT stubbed
 * here; each suite supplies its own (fixture-based for unit tests, real for
 * the live smoke test) via tests/support/fixture-http.php or tests/support/live-http.php.
 */

if (! defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (! defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}

if (! defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (! function_exists('__')) {
    function __($text, $domain = 'default')
    {
        return $text;
    }
}

if (! function_exists('remove_accents')) {
    function remove_accents($text)
    {
        // Good enough for the club names this plugin deals with; not a full port of WP's version.
        $transliterated = @iconv('UTF-8', 'ASCII//TRANSLIT', (string) $text);

        return $transliterated !== false ? $transliterated : (string) $text;
    }
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        private string $code;
        private string $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '')
        {
            $this->code = (string) $code;
            $this->message = (string) $message;
            $this->data = $data;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing)
    {
        return $thing instanceof WP_Error;
    }
}

if (! isset($GLOBALS['__mini_test_transients'])) {
    $GLOBALS['__mini_test_transients'] = [];
}

if (! function_exists('get_transient')) {
    function get_transient($key)
    {
        return $GLOBALS['__mini_test_transients'][$key] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient($key, $value, $ttl = 0)
    {
        $GLOBALS['__mini_test_transients'][$key] = $value;

        return true;
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient($key)
    {
        unset($GLOBALS['__mini_test_transients'][$key]);

        return true;
    }
}

function mini_test_reset_transients(): void
{
    $GLOBALS['__mini_test_transients'] = [];
}

if (! function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        $GLOBALS['__mini_test_hooks'][$hook][] = $callback;

        return true;
    }
}

if (! function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
    {
        return add_filter($hook, $callback, $priority, $accepted_args);
    }
}

if (! function_exists('plugin_basename')) {
    function plugin_basename($file)
    {
        $file = str_replace('\\', '/', (string) $file);

        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (! function_exists('esc_html')) {
    function esc_html($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default')
    {
        return esc_html($text);
    }
}

if (! function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return $response['response']['code'] ?? 0;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return $response['body'] ?? '';
    }
}
