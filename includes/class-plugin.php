<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Plugin
{
    private static ?PLT_Plugin $instance = null;

    private PLT_Settings $settings;
    private PLT_Next_Match_Settings $next_match_settings;
    private PLT_Api_Client $api_client;
    private PLT_Club_Map $club_map;
    private PLT_Standings_Service $standings_service;
    private PLT_TheSportsDB_Client $thesportsdb_client;
    private PLT_WPLL_Client $wpll_client;
    private PLT_Shortcode $shortcode;
    private PLT_Next_Match_Shortcode $next_match_shortcode;
    private PLT_GitHub_Updater $github_updater;

    public static function instance(): PLT_Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->settings = new PLT_Settings();
        $this->next_match_settings = new PLT_Next_Match_Settings();
        $this->api_client = new PLT_Api_Client();
        $this->club_map = new PLT_Club_Map();
        $this->thesportsdb_client = new PLT_TheSportsDB_Client();
        $this->wpll_client = new PLT_WPLL_Client();
        $this->standings_service = new PLT_Standings_Service(
            new PLT_Football_Data_Provider($this->api_client),
            [
                new PLT_WPLL_Standings_Provider($this->wpll_client),
                new PLT_TheSportsDB_Provider(),
            ],
            $this->club_map
        );
        $this->shortcode = new PLT_Shortcode($this->settings, $this->standings_service);
        $this->next_match_shortcode = new PLT_Next_Match_Shortcode(
            $this->settings,
            $this->next_match_settings,
            $this->api_client,
            $this->standings_service,
            $this->thesportsdb_client,
            $this->wpll_client
        );
        $this->github_updater = new PLT_GitHub_Updater(
            PLT_PLUGIN_FILE,
            PLT_GITHUB_REPO,
            PLT_VERSION
        );

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void
    {
        $this->settings->register_hooks();
        $this->next_match_settings->register_hooks();
        $this->shortcode->register_hooks();
        $this->next_match_shortcode->register_hooks();
        $this->github_updater->register_hooks();
    }
}
