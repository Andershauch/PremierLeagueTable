<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_WPLL_Standings_Provider implements PLT_Standings_Provider
{
    private PLT_WPLL_Client $client;

    public function __construct(PLT_WPLL_Client $client)
    {
        $this->client = $client;
    }

    public function get_provider_key(): string
    {
        return 'wpll';
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        if ($competition_key !== 'wsl') {
            return new WP_Error(
                'plt_provider_unsupported_competition',
                __('This provider does not support the requested competition yet.', 'premier-league-table')
            );
        }

        $cache_ttl_seconds = isset($context['cache_ttl_seconds']) ? (int) $context['cache_ttl_seconds'] : 1800;
        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = 1800;
        }

        $standings = $this->client->get_standings($cache_ttl_seconds);
        if (is_wp_error($standings)) {
            return $standings;
        }

        $season = isset($standings['season']) && is_array($standings['season']) ? $standings['season'] : [];
        $data_mode = isset($standings['data_mode']) ? (string) $standings['data_mode'] : 'preseason';
        $rows = $this->build_rows(isset($standings['teams']) && is_array($standings['teams']) ? $standings['teams'] : []);

        if ($rows === [] && $data_mode !== 'preseason') {
            return new WP_Error(
                'plt_wpll_empty_standings',
                __('No WSL standings data is available from the WSL Football feed right now.', 'premier-league-table')
            );
        }

        return [
            'provider' => $this->get_provider_key(),
            'competition_key' => 'wsl',
            'competition_label' => 'Women\'s Super League',
            'source_label' => 'WSL Football',
            'season' => [
                'id' => 0,
                'start_date' => isset($season['start_date']) ? (string) $season['start_date'] : '',
                'end_date' => isset($season['end_date']) ? (string) $season['end_date'] : '',
                'current_matchday' => 0,
                'label' => isset($season['label']) ? (string) $season['label'] : '',
            ],
            'updated_at' => gmdate('c'),
            'data_mode' => $data_mode,
            'rows' => $rows,
            'raw' => $standings,
        ];
    }

    private function build_rows(array $teams): array
    {
        $rows = [];

        foreach ($teams as $team) {
            if (! is_array($team)) {
                continue;
            }

            $stats = isset($team['stats']) && is_array($team['stats']) ? $team['stats'] : [];
            $stat_values = [];
            foreach ($stats as $stat) {
                if (! is_array($stat) || ! isset($stat['statsId'])) {
                    continue;
                }

                $stat_values[(string) $stat['statsId']] = $stat['statsValue'] ?? null;
            }

            $team_id = isset($team['teamId']) ? (string) $team['teamId'] : '';
            $team_name = isset($team['officialName']) && $team['officialName'] !== ''
                ? (string) $team['officialName']
                : (string) ($team['shortName'] ?? '');
            $crest = isset($team['imagery']['teamLogo']) ? (string) $team['imagery']['teamLogo'] : '';

            $goals_for = (int) ($stat_values['goals-for'] ?? 0);
            $goals_against = (int) ($stat_values['goals-against'] ?? 0);

            $rows[] = [
                'team_id' => $this->client->hash_team_id($team_id),
                'position' => (int) ($stat_values['rank'] ?? 0),
                'team_name' => $team_name,
                'team_crest' => $this->client->resolve_image_url($crest),
                'played' => (int) ($stat_values['matches-played'] ?? 0),
                'won' => (int) ($stat_values['win'] ?? 0),
                'draw' => (int) ($stat_values['draw'] ?? 0),
                'lost' => (int) ($stat_values['lose'] ?? 0),
                'goals_for' => $goals_for,
                'goals_against' => $goals_against,
                'goal_diff' => isset($stat_values['goal-difference']) ? (int) $stat_values['goal-difference'] : ($goals_for - $goals_against),
                'points' => (int) ($stat_values['points'] ?? 0),
            ];
        }

        usort(
            $rows,
            static function (array $a, array $b): int {
                if ($a['position'] > 0 && $b['position'] > 0) {
                    return $a['position'] <=> $b['position'];
                }

                return
                    ($b['points'] <=> $a['points']) ?:
                    ($b['goal_diff'] <=> $a['goal_diff']) ?:
                    ($b['goals_for'] <=> $a['goals_for']) ?:
                    strcasecmp((string) $a['team_name'], (string) $b['team_name']);
            }
        );

        return $rows;
    }
}
