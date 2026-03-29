<?php
/**
 * Plugin Name: Premier League Table Embed
 * Description: Embed a live Premier League standings table from API-Football with legacy and custom appearance presets.
 * Version: 1.3.0
 * Author: HansenDjurhuus
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: premier-league-table
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('PLT_PLUGIN_FILE')) {
    define('PLT_PLUGIN_FILE', __FILE__);
}

if (! defined('PLT_PLUGIN_DIR')) {
    define('PLT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (! defined('PLT_PLUGIN_URL')) {
    define('PLT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once PLT_PLUGIN_DIR . 'includes/class-plugin.php';
require_once PLT_PLUGIN_DIR . 'includes/class-settings.php';
require_once PLT_PLUGIN_DIR . 'includes/class-api-client.php';
require_once PLT_PLUGIN_DIR . 'includes/class-cache.php';
require_once PLT_PLUGIN_DIR . 'includes/class-renderer.php';
require_once PLT_PLUGIN_DIR . 'includes/class-shortcode.php';

PLT_Plugin::instance();
