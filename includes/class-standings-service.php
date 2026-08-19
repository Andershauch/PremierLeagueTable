<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Standings_Service
{
    private PLT_Standings_Provider $football_data_provider;

    /** @var PLT_Standings_Provider[] Ordered WSL providers, tried in sequence until one succeeds. */
    private array $wsl_providers;

    private PLT_Club_Map $club_map;

    /**
     * @param PLT_Standings_Provider[] $wsl_providers Ordered WSL providers; each is tried in turn
     *                                                 until one returns data, so an unstable primary
     *                                                 source (e.g. an undocumented feed) automatically
     *                                                 falls back to the next one.
     */
    public function __construct(
        PLT_Standings_Provider $football_data_provider,
        array $wsl_providers,
        PLT_Club_Map $club_map
    ) {
        $this->football_data_provider = $football_data_provider;
        $this->wsl_providers = $wsl_providers;
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
        $last_error = new WP_Error(
            'plt_wsl_no_providers',
            __('No WSL standings provider is configured.', 'premier-league-table')
        );

        foreach ($this->wsl_providers as $provider) {
            $result = $provider->get_standings(
                'wsl',
                [
                    'cache_ttl_seconds' => $cache_ttl_seconds,
                ]
            );

            if (! is_wp_error($result)) {
                return $result;
            }

            $last_error = $result;
        }

        return $last_error;
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

    /**
     * Decides whether a standings row is the focus team, by club identity
     * rather than by string similarity.
     *
     * Comparing display names directly does not survive the hybrid provider
     * setup: the saved focus team resolves to a competition-specific name
     * ("Tottenham Women" for WSL), while the WSL feed has served PL-style names
     * ("Tottenham Hotspur") since the WPLL source became primary. Neither
     * string contains the other, so the highlight silently stopped matching for
     * clubs whose women's name replaces the men's suffix instead of appending
     * to it. Resolving both sides to a canonical club key compares the club,
     * not the wording, so it holds across providers and future renamings.
     */
    public function matches_focus_team(string $team_name, string $focus_team_name): bool
    {
        $team_name = trim($team_name);
        $focus_team_name = trim($focus_team_name);
        if ($team_name === '' || $focus_team_name === '') {
            return false;
        }

        $team_key = $this->club_map->resolve_canonical_key($team_name);
        $focus_key = $this->club_map->resolve_canonical_key($focus_team_name);

        return $team_key !== '' && $team_key === $focus_key;
    }

    public function get_focus_team_name_for_competition(array $focus_team_context, string $competition_key): string
    {
        if ($competition_key === 'wsl') {
            return isset($focus_team_context['wsl_name']) ? (string) $focus_team_context['wsl_name'] : '';
        }

        return isset($focus_team_context['pl_name']) ? (string) $focus_team_context['pl_name'] : '';
    }
}
