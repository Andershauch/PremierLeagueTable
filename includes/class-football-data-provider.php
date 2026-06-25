<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Football_Data_Provider implements PLT_Standings_Provider
{
    private PLT_Api_Client $api_client;

    public function __construct(PLT_Api_Client $api_client)
    {
        $this->api_client = $api_client;
    }

    public function get_provider_key(): string
    {
        return 'football-data';
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        if ($competition_key !== 'pl') {
            return new WP_Error(
                'plt_provider_unsupported_competition',
                __('This provider does not support the requested competition yet.', 'premier-league-table')
            );
        }

        $api_key = isset($context['api_key']) ? (string) $context['api_key'] : '';
        $cache_ttl_seconds = isset($context['cache_ttl_seconds']) ? (int) $context['cache_ttl_seconds'] : 600;

        $table = $this->api_client->get_premier_league_table($api_key, $cache_ttl_seconds);
        if (is_wp_error($table)) {
            return $table;
        }

        return [
            'provider' => $this->get_provider_key(),
            'competition_key' => 'pl',
            'competition_label' => 'Premier League',
            'source_label' => 'Football-Data.org',
            'season' => [
                'id' => isset($table['season_id']) ? (int) $table['season_id'] : 0,
                'start_date' => isset($table['season_start_date']) ? (string) $table['season_start_date'] : '',
                'end_date' => isset($table['season_end_date']) ? (string) $table['season_end_date'] : '',
                'current_matchday' => isset($table['current_matchday']) ? (int) $table['current_matchday'] : 0,
            ],
            'updated_at' => isset($table['fetched_at']) ? (string) $table['fetched_at'] : '',
            'rows' => isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : [],
            'raw' => $table,
        ];
    }
}
