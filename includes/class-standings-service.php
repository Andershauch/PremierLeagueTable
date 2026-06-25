<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Standings_Service
{
    private PLT_Standings_Provider $football_data_provider;
    private PLT_Standings_Provider $thesportsdb_provider;
    private PLT_Club_Map $club_map;

    public function __construct(
        PLT_Standings_Provider $football_data_provider,
        PLT_Standings_Provider $thesportsdb_provider,
        PLT_Club_Map $club_map
    ) {
        $this->football_data_provider = $football_data_provider;
        $this->thesportsdb_provider = $thesportsdb_provider;
        $this->club_map = $club_map;
    }

    /**
     * @return array|WP_Error
     */
    public function get_premier_league_standings(string $api_key, int $cache_ttl_seconds)
    {
        return $this->football_data_provider->get_standings(
            'pl',
            [
                'api_key' => $api_key,
                'cache_ttl_seconds' => $cache_ttl_seconds,
            ]
        );
    }

    /**
     * @return array|WP_Error
     */
    public function get_wsl_standings(int $cache_ttl_seconds = 1800)
    {
        return $this->thesportsdb_provider->get_standings(
            'wsl',
            [
                'cache_ttl_seconds' => $cache_ttl_seconds,
            ]
        );
    }

    /**
     * @return array|WP_Error
     */
    public function get_standings(string $competition_key, array $context = [])
    {
        if ($competition_key === 'wsl') {
            return $this->get_wsl_standings(
                isset($context['cache_ttl_seconds']) ? (int) $context['cache_ttl_seconds'] : 1800
            );
        }

        return $this->get_premier_league_standings(
            isset($context['api_key']) ? (string) $context['api_key'] : '',
            isset($context['cache_ttl_seconds']) ? (int) $context['cache_ttl_seconds'] : 600
        );
    }

    public function resolve_focus_team_context(string $saved_team_name): array
    {
        $saved_team_name = trim($saved_team_name);
        $canonical_key = $this->club_map->resolve_canonical_key($saved_team_name);
        $pl_name = $canonical_key !== ''
            ? $this->club_map->get_display_team_name($canonical_key, 'pl', $saved_team_name)
            : $saved_team_name;
        $wsl_name = $canonical_key !== '' && $this->club_map->has_competition_mapping($canonical_key, 'wsl')
            ? $this->club_map->get_display_team_name($canonical_key, 'wsl', '')
            : '';

        return [
            'canonical_key' => $canonical_key,
            'saved_name' => $saved_team_name,
            'pl_name' => $pl_name,
            'wsl_name' => $wsl_name,
        ];
    }

    public function get_focus_team_name_for_competition(array $focus_team_context, string $competition_key): string
    {
        if ($competition_key === 'wsl') {
            return isset($focus_team_context['wsl_name']) ? (string) $focus_team_context['wsl_name'] : '';
        }

        return isset($focus_team_context['pl_name']) ? (string) $focus_team_context['pl_name'] : '';
    }
}
