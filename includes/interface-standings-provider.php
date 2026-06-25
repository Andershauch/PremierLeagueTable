<?php

if (! defined('ABSPATH')) {
    exit;
}

interface PLT_Standings_Provider
{
    public function get_provider_key(): string;

    /**
     * @return array|WP_Error
     */
    public function get_standings(string $competition_key, array $context = []);
}
