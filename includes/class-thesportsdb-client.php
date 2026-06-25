<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_TheSportsDB_Client
{
    private const BASE_URL = 'https://www.thesportsdb.com/api/v1/json/123';
    private const WSL_LEAGUE_NAME = 'English_Womens_Super_League';
    private const NEXT_MATCH_CACHE_PREFIX = 'plt_wsl_next_match_v2_';
    private const NEXT_MATCH_LOCK_PREFIX = 'plt_wsl_next_match_lock_v2_';
    private const LOCK_TTL_SECONDS = 20;

    /**
     * @return array|WP_Error
     */
    public function get_next_wsl_match(string $team_name, int $cache_ttl_seconds = 600)
    {
        $team_name = trim($team_name);
        if ($team_name === '') {
            return new WP_Error(
                'plt_missing_wsl_team_name',
                __('WSL focus team could not be resolved.', 'premier-league-table')
            );
        }

        $cache_key = self::NEXT_MATCH_CACHE_PREFIX . md5(strtolower($team_name));
        $lock_key = self::NEXT_MATCH_LOCK_PREFIX . md5(strtolower($team_name));

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['utc_date'])) {
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
            $team = $this->find_wsl_team($team_name);
            if (is_wp_error($team)) {
                return $team;
            }

            $team_id = isset($team['id']) ? (int) $team['id'] : 0;
            if ($team_id <= 0) {
                return new WP_Error(
                    'plt_missing_wsl_team_id',
                    __('WSL focus team id could not be resolved.', 'premier-league-table')
                );
            }

            $payload = $this->fetch_json(
                add_query_arg(
                    ['id' => $team_id],
                    self::BASE_URL . '/eventsnext.php'
                ),
                'plt_wsl_invalid_response',
                __('TheSportsDB returnerede et ugyldigt svar for WSL-kampe.', 'premier-league-table'),
                true
            );
            if (is_wp_error($payload)) {
                return $payload;
            }

            $events = isset($payload['events']) && is_array($payload['events']) ? $payload['events'] : [];
            $match = $this->extract_next_wsl_match($events, $team_id);
            if (! is_array($match)) {
                return new WP_Error(
                    'plt_no_upcoming_wsl_match',
                    sprintf(
                        __('Ingen kommende WSL-kamp fundet for %s lige nu. Det er normalt mellem sæsoner eller før næste spilleplan er offentliggjort.', 'premier-league-table'),
                        $team_name
                    )
                );
            }

            set_transient($cache_key, $match, max(60, $cache_ttl_seconds));

            return $match;
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * @return array|WP_Error
     */
    private function find_wsl_team(string $team_name)
    {
        $candidates = $this->build_team_name_candidates($team_name);

        foreach ($candidates as $candidate) {
            $payload = $this->fetch_json(
                add_query_arg(
                    ['t' => $candidate],
                    self::BASE_URL . '/searchteams.php'
                ),
                'plt_wsl_team_invalid_response',
                __('TheSportsDB returnerede et ugyldigt svar ved holdopslag.', 'premier-league-table'),
                true
            );
            if (is_wp_error($payload)) {
                continue;
            }

            $teams = isset($payload['teams']) && is_array($payload['teams']) ? $payload['teams'] : [];
            $match = $this->pick_matching_wsl_team($teams, $candidate);
            if (is_array($match)) {
                return $match;
            }
        }

        $league_payload = $this->fetch_json(
            add_query_arg(
                ['l' => self::WSL_LEAGUE_NAME],
                self::BASE_URL . '/search_all_teams.php'
            ),
            'plt_wsl_team_invalid_response',
            __('TheSportsDB returnerede et ugyldigt svar ved WSL-holdlisten.', 'premier-league-table'),
            false
        );
        if (! is_wp_error($league_payload)) {
            $teams = isset($league_payload['teams']) && is_array($league_payload['teams']) ? $league_payload['teams'] : [];
            $match = $this->pick_matching_wsl_team($teams, $team_name);
            if (is_array($match)) {
                return $match;
            }
        }

        return new WP_Error(
            'plt_wsl_team_not_found',
            sprintf(
                __('WSL-holdet %s blev ikke fundet hos TheSportsDB.', 'premier-league-table'),
                $team_name
            )
        );
    }

    private function fetch_json(string $url, string $error_code, string $error_message, bool $allow_empty_body)
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
                __('Kunne ikke hente WSL-kampdata fra TheSportsDB.', 'premier-league-table'),
                ['details' => $response->get_error_message()]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = trim((string) wp_remote_retrieve_body($response));
        if ($status_code !== 200) {
            return new WP_Error($error_code, $error_message);
        }

        if ($body === '') {
            return $allow_empty_body ? [] : new WP_Error($error_code, $error_message);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload)) {
            return new WP_Error($error_code, $error_message);
        }

        return $payload;
    }

    private function pick_matching_wsl_team(array $teams, string $team_name): ?array
    {
        $normalized_target = $this->normalize_team_name($team_name);

        foreach ($teams as $team) {
            if (! is_array($team)) {
                continue;
            }

            $league = isset($team['strLeague']) ? strtolower((string) $team['strLeague']) : '';
            $gender = isset($team['strGender']) ? strtolower((string) $team['strGender']) : '';
            if ($gender !== 'female' || strpos($league, 'womens super league') === false) {
                continue;
            }

            $team_label = isset($team['strTeam']) ? (string) $team['strTeam'] : '';
            $normalized_team = $this->normalize_team_name($team_label);
            if (
                $normalized_team === $normalized_target ||
                strpos($normalized_team, $normalized_target) !== false ||
                strpos($normalized_target, $normalized_team) !== false
            ) {
                return [
                    'id' => isset($team['idTeam']) ? (int) $team['idTeam'] : 0,
                    'name' => $team_label,
                    'badge' => isset($team['strBadge']) ? (string) $team['strBadge'] : '',
                ];
            }
        }

        return null;
    }

    private function build_team_name_candidates(string $team_name): array
    {
        $team_name = trim($team_name);
        $candidates = [$team_name];
        $normalized = $this->normalize_team_name($team_name);

        $variants = [
            'arsenal' => ['Arsenal WFC', 'Arsenal Women', 'Arsenal'],
            'aston villa' => ['Aston Villa WFC', 'Aston Villa Women', 'Aston Villa'],
            'brighton hove albion' => ['Brighton WFC', 'Brighton Women', 'Brighton'],
            'brighton' => ['Brighton WFC', 'Brighton Women', 'Brighton'],
            'chelsea' => ['Chelsea Women', 'Chelsea'],
            'crystal palace' => ['Crystal Palace Women', 'Crystal Palace'],
            'everton' => ['Everton FC Women', 'Everton Women', 'Everton'],
            'leicester city' => ['Leicester City WFC', 'Leicester Women', 'Leicester City'],
            'liverpool' => ['Liverpool FC Women', 'Liverpool Women', 'Liverpool'],
            'manchester city' => ['Manchester City WFC', 'Manchester City Women', 'Manchester City'],
            'manchester united' => ['Manchester United WFC', 'Manchester United Women', 'Manchester United'],
            'tottenham hotspur' => ['Tottenham Women', 'Tottenham Hotspur Women', 'Tottenham'],
            'tottenham women' => ['Tottenham Women', 'Tottenham'],
            'west ham united' => ['West Ham Women', 'West Ham United', 'West Ham'],
            'west ham' => ['West Ham Women', 'West Ham United', 'West Ham'],
        ];

        if (isset($variants[$normalized])) {
            $candidates = array_merge($candidates, $variants[$normalized]);
        }

        if (substr($team_name, -6) === ' Women') {
            $candidates[] = substr($team_name, 0, -6);
        }

        $candidates[] = preg_replace('/\s+WFC$/i', '', $team_name);
        $candidates[] = preg_replace('/\s+FC Women$/i', '', $team_name);

        $filtered = [];
        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $filtered[strtolower($candidate)] = $candidate;
        }

        return array_values($filtered);
    }

    private function extract_next_wsl_match(array $events, int $focus_team_id): ?array
    {
        $filtered = [];

        foreach ($events as $event) {
            if (! is_array($event)) {
                continue;
            }

            $league = isset($event['strLeague']) ? strtolower((string) $event['strLeague']) : '';
            if (strpos($league, 'womens super league') === false) {
                continue;
            }

            $timestamp = $this->extract_timestamp($event);
            if ($timestamp === null || $timestamp < time()) {
                continue;
            }

            $filtered[] = [
                'timestamp' => $timestamp,
                'event' => $event,
            ];
        }

        if ($filtered === []) {
            return null;
        }

        usort(
            $filtered,
            static function (array $a, array $b): int {
                return $a['timestamp'] <=> $b['timestamp'];
            }
        );

        $event = $filtered[0]['event'];
        $home_id = isset($event['idHomeTeam']) ? (int) $event['idHomeTeam'] : 0;
        $away_id = isset($event['idAwayTeam']) ? (int) $event['idAwayTeam'] : 0;
        $utc_date = $this->extract_utc_date_string($event, $filtered[0]['timestamp']);

        return [
            'competition' => 'Women\'s Super League',
            'utc_date' => $utc_date,
            'home_team' => [
                'id' => $home_id,
                'name' => isset($event['strHomeTeam']) ? (string) $event['strHomeTeam'] : '',
                'crest' => isset($event['strHomeTeamBadge']) ? (string) $event['strHomeTeamBadge'] : '',
            ],
            'away_team' => [
                'id' => $away_id,
                'name' => isset($event['strAwayTeam']) ? (string) $event['strAwayTeam'] : '',
                'crest' => isset($event['strAwayTeamBadge']) ? (string) $event['strAwayTeamBadge'] : '',
            ],
            'focus_side' => $focus_team_id === $home_id ? 'home' : 'away',
        ];
    }

    private function extract_timestamp(array $event): ?int
    {
        $timestamp_string = isset($event['strTimestamp']) ? trim((string) $event['strTimestamp']) : '';
        if ($timestamp_string !== '') {
            $timestamp = strtotime($timestamp_string . ' UTC');
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        $date = isset($event['dateEvent']) ? trim((string) $event['dateEvent']) : '';
        $time = isset($event['strTime']) ? trim((string) $event['strTime']) : '';
        if ($date === '') {
            return null;
        }

        $timestamp = strtotime(trim($date . ' ' . ($time !== '' ? $time : '00:00:00')) . ' UTC');
        if ($timestamp === false) {
            return null;
        }

        return $timestamp;
    }

    private function extract_utc_date_string(array $event, int $timestamp): string
    {
        $timestamp_string = isset($event['strTimestamp']) ? trim((string) $event['strTimestamp']) : '';
        if ($timestamp_string !== '') {
            return gmdate('c', $timestamp);
        }

        return gmdate('c', $timestamp);
    }

    private function normalize_team_name(string $name): string
    {
        $name = remove_accents(strtolower(trim($name)));
        $name = preg_replace('/\b(fc|afc|cf|wfc)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]+/u', ' ', (string) $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }
}
