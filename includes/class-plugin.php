<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Plugin
{
    private static ?PLT_Plugin $instance = null;

    private PLT_Settings $settings;
    private PLT_Api_Client $api_client;
    private PLT_Shortcode $shortcode;

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
        $this->api_client = new PLT_Api_Client();
        $this->shortcode = new PLT_Shortcode($this->settings, $this->api_client);

        add_action('plugins_loaded', [$this, 'boot']);
    }

    public function boot(): void
    {
        $this->settings->register_hooks();
        $this->shortcode->register_hooks();
    }
}
