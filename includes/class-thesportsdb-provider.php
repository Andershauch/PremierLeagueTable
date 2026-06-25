<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_TheSportsDB_Provider implements PLT_Standings_Provider
{
    private const BASE_URL = 'https://www.thesportsdb.com/api/v1/json/123';
    private const WSL_LEAGUE_ID = '4849';
    private const WSL_LEAGUE_NAME = 'English_Womens_Super_League';
    private const CACHE_PREFIX = 'plt_wsl_standings_v2_';
    private const LOCK_PREFIX = 'plt_wsl_standings_lock_v2_';
    private const CACHE_TTL_SECONDS = 1800;
    private const LOCK_TTL_SECONDS = 20;
    private const DATA_MODE_PRESEASON = 'preseason';
    private const DATA_MODE_LIVE = 'live';

    public function get_provider_key(): string
    {
        return 'thesportsdb';
    }

    public function get_standings(string $competition_key, array $context = [])
    {
        if ($competition_key !== 'wsl') {
            return new WP_Error(
                'plt_provider_unsupported_competition',
                __('This provider does not support the requested competition yet.', 'premier-league-table')
            );
        }

        $league_id = isset($context['league_id']) ? (string) $context['league_id'] : self::WSL_LEAGUE_ID;
        $league_name = isset($context['league_name']) ? (string) $context['league_name'] : self::WSL_LEAGUE_NAME;
        $season = isset($context['season']) ? (string) $context['season'] : $this->get_default_wsl_season();
        $cache_ttl_seconds = isset($context['cache_ttl_seconds']) ? (int) $context['cache_ttl_seconds'] : self::CACHE_TTL_SECONDS;
        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = self::CACHE_TTL_SECONDS;
        }

        $cache_key = self::CACHE_PREFIX . md5($league_id . '|' . $league_name . '|' . $season);
        $lock_key = self::LOCK_PREFIX . md5($league_id . '|' . $league_name . '|' . $season);

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            return $cached;
        }

        if (get_transient($lock_key)) {
            return new WP_Error(
                'plt_api_busy',
                __('Data opdateres lige nu. Prøv igen om et øjeblik.', 'premier-league-table')
            );
        }

        set_transient($lock_key, '1', self::LOCK_TTL_SECONDS);

        try {
            $teams_payload = $this->fetch_json(
                add_query_arg(
                    ['l' => $league_name],
                    self::BASE_URL . '/search_all_teams.php'
                )
            );
            if (is_wp_error($teams_payload)) {
                return $teams_payload;
            }

            $events_payload = $this->fetch_json(
                add_query_arg(
                    [
                        'id' => $league_id,
                        's' => $season,
                    ],
                    self::BASE_URL . '/eventsseason.php'
                )
            );
            if (is_wp_error($events_payload)) {
                return $events_payload;
            }

            $teams = isset($teams_payload['teams']) && is_array($teams_payload['teams']) ? $teams_payload['teams'] : [];
            $events = isset($events_payload['events']) && is_array($events_payload['events']) ? $events_payload['events'] : [];
            $completed_event_count = $this->count_completed_events($events);
            $data_mode = $this->resolve_data_mode($season, $teams, $events, $completed_event_count);
            $rows = $this->build_derived_rows($teams, $events, $season, $data_mode);

            if ($rows === []) {
                return new WP_Error(
                    'plt_wsl_empty_standings',
                    __('No WSL standings data is available from TheSportsDB right now.', 'premier-league-table')
                );
            }

            $normalized = [
                'provider' => $this->get_provider_key(),
                'competition_key' => 'wsl',
                'competition_label' => 'Women\'s Super League',
                'source_label' => 'TheSportsDB',
                'season' => [
                    'id' => 0,
                    'start_date' => '',
                    'end_date' => '',
                    'current_matchday' => 0,
                    'label' => $season,
                ],
                'updated_at' => gmdate('c'),
                'data_mode' => $data_mode,
                'rows' => $rows,
                'raw' => [
                    'teams' => $teams,
                    'events' => $events,
                    'completed_event_count' => $completed_event_count,
                    'provider_team_count' => count($teams),
                    'final_team_count' => count($rows),
                ],
            ];

            set_transient($cache_key, $normalized, $cache_ttl_seconds);

            return $normalized;
        } finally {
            delete_transient($lock_key);
        }
    }

    private function fetch_json(string $url)
    {
        $response = wp_remote_get(
            $url,
            [
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            return new WP_Error(
                'plt_wsl_http_error',
                __('Could not fetch WSL data from TheSportsDB.', 'premier-league-table'),
                ['details' => $response->get_error_message()]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($status_code !== 200 || ! is_array($payload)) {
            return new WP_Error(
                'plt_wsl_invalid_response',
                __('TheSportsDB returned an invalid response for WSL standings.', 'premier-league-table')
            );
        }

        return $payload;
    }

    private function build_derived_rows(array $teams, array $events, string $season, string $data_mode): array
    {
        $table_map = [];
        $season = trim($season);
        if ($season === '') {
            $season = $this->extract_season_from_events($events);
        }
        if ($season === '') {
            $season = $this->get_default_wsl_season();
        }

        $fallback_teams = $this->get_fallback_wsl_teams($season);
        $is_preseason_roster = $data_mode === self::DATA_MODE_PRESEASON && $fallback_teams !== [];

        if ($is_preseason_roster) {
            foreach ($fallback_teams as $team) {
                $team_id = isset($team['idTeam']) ? trim((string) $team['idTeam']) : '';
                if ($team_id === '') {
                    continue;
                }

                $table_map[$team_id] = $this->create_empty_row($team_id, $team);
            }
        }

        foreach ($teams as $team) {
            if (! is_array($team)) {
                continue;
            }

            $team_id = isset($team['idTeam']) ? trim((string) $team['idTeam']) : '';
            if ($team_id === '') {
                continue;
            }

            if ($is_preseason_roster && ! isset($table_map[$team_id])) {
                continue;
            }

            $table_map[$team_id] = $this->create_empty_row($team_id, $team);
        }

        foreach ($fallback_teams as $team) {
            $team_id = isset($team['idTeam']) ? trim((string) $team['idTeam']) : '';
            if ($team_id === '' || isset($table_map[$team_id])) {
                continue;
            }

            $table_map[$team_id] = $this->create_empty_row($team_id, $team);
        }

        foreach ($events as $event) {
            if (! $this->is_completed_event($event)) {
                continue;
            }

            $this->apply_completed_event($table_map, $event);
        }

        if ($table_map === []) {
            return [];
        }

        $rows = array_values($table_map);

        foreach ($rows as &$row) {
            $row['goal_diff'] = $row['goals_for'] - $row['goals_against'];
        }
        unset($row);

        usort(
            $rows,
            static function (array $a, array $b): int {
                return
                    ($b['points'] <=> $a['points']) ?:
                    ($b['goal_diff'] <=> $a['goal_diff']) ?:
                    ($b['goals_for'] <=> $a['goals_for']) ?:
                    strcasecmp((string) $a['team_name'], (string) $b['team_name']);
            }
        );

        foreach ($rows as $index => &$row) {
            $row['position'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    private function resolve_data_mode(string $season, array $teams, array $events, int $completed_event_count): string
    {
        $fallback_teams = $this->get_fallback_wsl_teams($season);

        if ($completed_event_count > 0) {
            return self::DATA_MODE_LIVE;
        }

        if ($events === [] || $fallback_teams !== [] || $teams !== []) {
            return self::DATA_MODE_PRESEASON;
        }

        return self::DATA_MODE_PRESEASON;
    }

    private function extract_season_from_events(array $events): string
    {
        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $season = isset($event['strSeason']) ? trim((string) $event['strSeason']) : '';
            if ($season !== '') {
                return $season;
            }
        }

        return '';
    }

    private function count_completed_events(array $events): int
    {
        $count = 0;

        foreach ($events as $event) {
            if ($this->is_completed_event($event)) {
                $count++;
            }
        }

        return $count;
    }

    private function create_empty_row(string $team_id, array $team): array
    {
        return [
            'team_id' => (int) $team_id,
            'position' => 0,
            'team_name' => isset($team['strTeam']) ? (string) $team['strTeam'] : '',
            'team_crest' => isset($team['strBadge']) ? (string) $team['strBadge'] : '',
            'played' => 0,
            'won' => 0,
            'draw' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_diff' => 0,
            'points' => 0,
        ];
    }

    private function is_completed_event($event): bool
    {
        if (! is_array($event)) {
            return false;
        }

        return $this->to_int($event['intHomeScore'] ?? null) !== null
            && $this->to_int($event['intAwayScore'] ?? null) !== null;
    }

    private function apply_completed_event(array &$table_map, array $event): void
    {
        $home_id = isset($event['idHomeTeam']) ? trim((string) $event['idHomeTeam']) : '';
        $away_id = isset($event['idAwayTeam']) ? trim((string) $event['idAwayTeam']) : '';
        $home_score = $this->to_int($event['intHomeScore'] ?? null);
        $away_score = $this->to_int($event['intAwayScore'] ?? null);

        if ($home_id === '' || $away_id === '' || $home_score === null || $away_score === null) {
            return;
        }

        if (! isset($table_map[$home_id])) {
            $table_map[$home_id] = $this->create_empty_row(
                $home_id,
                [
                    'strTeam' => isset($event['strHomeTeam']) ? (string) $event['strHomeTeam'] : '',
                    'strBadge' => isset($event['strHomeTeamBadge']) ? (string) $event['strHomeTeamBadge'] : '',
                ]
            );
        }

        if (! isset($table_map[$away_id])) {
            $table_map[$away_id] = $this->create_empty_row(
                $away_id,
                [
                    'strTeam' => isset($event['strAwayTeam']) ? (string) $event['strAwayTeam'] : '',
                    'strBadge' => isset($event['strAwayTeamBadge']) ? (string) $event['strAwayTeamBadge'] : '',
                ]
            );
        }

        $table_map[$home_id]['played'] += 1;
        $table_map[$away_id]['played'] += 1;

        $table_map[$home_id]['goals_for'] += $home_score;
        $table_map[$home_id]['goals_against'] += $away_score;
        $table_map[$away_id]['goals_for'] += $away_score;
        $table_map[$away_id]['goals_against'] += $home_score;

        if ($home_score > $away_score) {
            $table_map[$home_id]['won'] += 1;
            $table_map[$away_id]['lost'] += 1;
            $table_map[$home_id]['points'] += 3;
            return;
        }

        if ($away_score > $home_score) {
            $table_map[$away_id]['won'] += 1;
            $table_map[$home_id]['lost'] += 1;
            $table_map[$away_id]['points'] += 3;
            return;
        }

        $table_map[$home_id]['draw'] += 1;
        $table_map[$away_id]['draw'] += 1;
        $table_map[$home_id]['points'] += 1;
        $table_map[$away_id]['points'] += 1;
    }

    private function to_int($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', trim($value)) === 1) {
            return (int) trim($value);
        }

        return null;
    }

    private function normalize_rows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalized[] = [
                'team_id' => isset($row['idTeam']) ? (int) $row['idTeam'] : 0,
                'position' => isset($row['intRank']) ? (int) $row['intRank'] : 0,
                'team_name' => isset($row['strTeam']) ? (string) $row['strTeam'] : '',
                'team_crest' => isset($row['strBadge']) ? (string) $row['strBadge'] : '',
                'played' => isset($row['intPlayed']) ? (int) $row['intPlayed'] : 0,
                'won' => isset($row['intWin']) ? (int) $row['intWin'] : 0,
                'draw' => isset($row['intDraw']) ? (int) $row['intDraw'] : 0,
                'lost' => isset($row['intLoss']) ? (int) $row['intLoss'] : 0,
                'goals_for' => isset($row['intGoalsFor']) ? (int) $row['intGoalsFor'] : 0,
                'goals_against' => isset($row['intGoalsAgainst']) ? (int) $row['intGoalsAgainst'] : 0,
                'goal_diff' => isset($row['intGoalDifference']) ? (int) $row['intGoalDifference'] : 0,
                'points' => isset($row['intPoints']) ? (int) $row['intPoints'] : 0,
            ];
        }

        return $normalized;
    }

    private function get_default_wsl_season(): string
    {
        $year = (int) gmdate('Y');
        $month = (int) gmdate('n');
        $season_start_year = $month >= 6 ? $year : $year - 1;

        return $season_start_year . '-' . ($season_start_year + 1);
    }

    private function get_fallback_wsl_teams(string $season): array
    {
        $season_start_year = $this->extract_season_start_year($season);

        if ($season_start_year >= 2026) {
            return [
                ['idTeam' => '140219', 'strTeam' => 'Arsenal WFC', 'strBadge' => ''],
                ['idTeam' => '140220', 'strTeam' => 'Aston Villa WFC', 'strBadge' => ''],
                ['idTeam' => '140221', 'strTeam' => 'Birmingham City WFC', 'strBadge' => ''],
                ['idTeam' => '140222', 'strTeam' => 'Brighton WFC', 'strBadge' => ''],
                ['idTeam' => '140224', 'strTeam' => 'Chelsea Women', 'strBadge' => ''],
                ['idTeam' => '142043', 'strTeam' => 'Crystal Palace Women', 'strBadge' => ''],
                ['idTeam' => '140225', 'strTeam' => 'Everton FC Women', 'strBadge' => ''],
                ['idTeam' => '140532', 'strTeam' => 'Liverpool FC Women', 'strBadge' => ''],
                ['idTeam' => '140399', 'strTeam' => 'London City Lionesses', 'strBadge' => ''],
                ['idTeam' => '140218', 'strTeam' => 'Manchester City WFC', 'strBadge' => ''],
                ['idTeam' => '140226', 'strTeam' => 'Manchester United WFC', 'strBadge' => ''],
                ['idTeam' => '140228', 'strTeam' => 'Tottenham Women', 'strBadge' => ''],
                ['idTeam' => '141251', 'strTeam' => 'West Ham Women', 'strBadge' => ''],
                ['idTeam' => '136056', 'strTeam' => 'Charlton Athletic Women', 'strBadge' => ''],
            ];
        }

        return [
            ['idTeam' => '140219', 'strTeam' => 'Arsenal WFC', 'strBadge' => ''],
            ['idTeam' => '140220', 'strTeam' => 'Aston Villa WFC', 'strBadge' => ''],
            ['idTeam' => '140222', 'strTeam' => 'Brighton WFC', 'strBadge' => ''],
            ['idTeam' => '140224', 'strTeam' => 'Chelsea Women', 'strBadge' => ''],
            ['idTeam' => '140225', 'strTeam' => 'Everton FC Women', 'strBadge' => ''],
            ['idTeam' => '140540', 'strTeam' => 'Leicester City WFC', 'strBadge' => ''],
            ['idTeam' => '140532', 'strTeam' => 'Liverpool FC Women', 'strBadge' => ''],
            ['idTeam' => '140218', 'strTeam' => 'Manchester City WFC', 'strBadge' => ''],
            ['idTeam' => '140226', 'strTeam' => 'Manchester United WFC', 'strBadge' => ''],
            ['idTeam' => '140228', 'strTeam' => 'Tottenham Women', 'strBadge' => ''],
            ['idTeam' => '141251', 'strTeam' => 'West Ham Women', 'strBadge' => ''],
            ['idTeam' => '140399', 'strTeam' => 'London City Lionesses', 'strBadge' => ''],
        ];
    }

    private function extract_season_start_year(string $season): int
    {
        if (preg_match('/^(\d{4})-\d{4}$/', trim($season), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
