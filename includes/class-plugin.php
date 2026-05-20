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
    private PLT_Shortcode $shortcode;
    private PLT_Next_Match_Shortcode $next_match_shortcode;

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
        $this->shortcode = new PLT_Shortcode($this->settings, $this->api_client);
        $this->next_match_shortcode = new PLT_Next_Match_Shortcode($this->settings, $this->next_match_settings, $this->api_client);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void
    {
        $this->settings->register_hooks();
        $this->next_match_settings->register_hooks();
        $this->shortcode->register_hooks();
        $this->next_match_shortcode->register_hooks();
    }
}
