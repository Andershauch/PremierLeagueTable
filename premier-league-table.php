<?php
/**
 * Plugin Name: Premier League Table Embed
 * Plugin URI: https://github.com/Andershauch/PremierLeagueTable
 * Description: Embed a live Premier League standings table with legacy and custom appearance presets.
 * Version: 2.3.0
 * Author: HansenDjurhuus
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: premier-league-table
 * Update URI: https://github.com/Andershauch/PremierLeagueTable
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('PLT_VERSION')) {
    define('PLT_VERSION', '2.3.0');
}

if (! defined('PLT_GITHUB_REPO')) {
    define('PLT_GITHUB_REPO', 'Andershauch/PremierLeagueTable');
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
require_once PLT_PLUGIN_DIR . 'includes/interface-standings-provider.php';
require_once PLT_PLUGIN_DIR . 'includes/class-club-map.php';
require_once PLT_PLUGIN_DIR . 'includes/class-football-data-provider.php';
require_once PLT_PLUGIN_DIR . 'includes/class-thesportsdb-provider.php';
require_once PLT_PLUGIN_DIR . 'includes/class-thesportsdb-client.php';
require_once PLT_PLUGIN_DIR . 'includes/class-wpll-client.php';
require_once PLT_PLUGIN_DIR . 'includes/class-wpll-standings-provider.php';
require_once PLT_PLUGIN_DIR . 'includes/class-standings-service.php';
require_once PLT_PLUGIN_DIR . 'includes/class-cache.php';
require_once PLT_PLUGIN_DIR . 'includes/class-renderer.php';
require_once PLT_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once PLT_PLUGIN_DIR . 'includes/class-next-match-settings.php';
require_once PLT_PLUGIN_DIR . 'includes/class-next-match-shortcode.php';
require_once PLT_PLUGIN_DIR . 'includes/class-github-updater.php';

PLT_Plugin::instance();
