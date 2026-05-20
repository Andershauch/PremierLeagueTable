<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Api_Client
{
    private const BASE_URL = 'https://api.football-data.org/v4';
    private const PL_STANDINGS_ENDPOINT = '/competitions/PL/standings';
    private const CACHE_KEY = 'plt_pl_standings_v1';
    private const CACHE_LOCK_KEY = 'plt_pl_standings_lock_v1';
    private const CACHE_TTL_SECONDS = 600;
    private const LOCK_TTL_SECONDS = 20;
    private const NEXT_MATCH_CACHE_PREFIX = 'plt_next_match_v1_';
    private const NEXT_MATCH_LOCK_PREFIX = 'plt_next_match_lock_v1_';

    public function get_premier_league_table(string $api_key, int $cache_ttl_seconds = self::CACHE_TTL_SECONDS)
    {
        $api_key = trim($api_key);
        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = self::CACHE_TTL_SECONDS;
        }

        if ($api_key === '') {
            $error = new WP_Error(
                'plt_missing_api_key',
                __('API key missing. Add your key under Settings -> Premier League Table.', 'premier-league-table')
            );
            $this->log_debug('Missing API key.');
            return $error;
        }

        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            return $cached;
        }

        if (get_transient(self::CACHE_LOCK_KEY)) {
            $this->log_debug('Cache lock active, skipping duplicate API fetch.');
            return new WP_Error(
                'plt_api_busy',
                __('Data opdateres lige nu. Proev igen om et oejeblik.', 'premier-league-table')
            );
        }

        set_transient(self::CACHE_LOCK_KEY, '1', self::LOCK_TTL_SECONDS);

        try {
            $url = self::BASE_URL . self::PL_STANDINGS_ENDPOINT;
            $response = wp_remote_get(
                $url,
                [
                    'timeout' => 12,
                    'headers' => [
                        'X-Auth-Token' => $api_key,
                        'Accept' => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                $error = new WP_Error(
                    'plt_api_http_error',
                    __('Kunne ikke hente data fra football-data.org.', 'premier-league-table'),
                    ['details' => $response->get_error_message()]
                );
                $this->log_debug('HTTP request failed.', ['details' => $response->get_error_message()]);
                return $error;
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);

            if ($status_code !== 200) {
                $message = __('API returnerede en fejl.', 'premier-league-table');
                $json = json_decode($body, true);
                if (is_array($json) && isset($json['message']) && is_string($json['message'])) {
                    $message = $json['message'];
                }

                $error = new WP_Error(
                    'plt_api_bad_status',
                    sprintf(
                        /* translators: 1: HTTP status code, 2: API message */
                        __('API-fejl (%1$d): %2$s', 'premier-league-table'),
                        $status_code,
                        $message
                    )
                );
                $this->log_debug('API returned non-200 status.', ['status_code' => $status_code, 'message' => $message]);
                return $error;
            }

            $payload = json_decode($body, true);
            if (! is_array($payload)) {
                $error = new WP_Error(
                    'plt_api_invalid_json',
                    __('Ugyldigt JSON-svar fra API.', 'premier-league-table')
                );
                $this->log_debug('Invalid JSON payload from API.');
                return $error;
            }

            $rows = $this->extract_total_standings_rows($payload);
            if (empty($rows)) {
                $error = new WP_Error(
                    'plt_api_empty_standings',
                    __('Kunne ikke finde stillingsdata i API-svaret.', 'premier-league-table')
                );
                $this->log_debug('No standings rows found in payload.');
                return $error;
            }

            $normalized = [
                'competition' => isset($payload['competition']['name']) ? (string) $payload['competition']['name'] : 'Premier League',
                'last_updated' => isset($payload['season']['currentMatchday']) ? (int) $payload['season']['currentMatchday'] : 0,
                'rows' => $rows,
            ];

            set_transient(self::CACHE_KEY, $normalized, $cache_ttl_seconds);

            return $normalized;
        } finally {
            delete_transient(self::CACHE_LOCK_KEY);
        }
    }

    private function extract_total_standings_rows(array $payload): array
    {
        if (! isset($payload['standings']) || ! is_array($payload['standings'])) {
            return [];
        }

        $table = [];
        foreach ($payload['standings'] as $standing) {
            if (! is_array($standing)) {
                continue;
            }

            $type = isset($standing['type']) ? strtoupper((string) $standing['type']) : '';
            if ($type === 'TOTAL' && isset($standing['table']) && is_array($standing['table'])) {
                $table = $standing['table'];
                break;
            }
        }

        if (empty($table)) {
            return [];
        }

        $rows = [];
        foreach ($table as $row) {
            if (! is_array($row)) {
                continue;
            }

            $team = isset($row['team']) && is_array($row['team']) ? $row['team'] : [];
            $rows[] = [
                'team_id' => isset($team['id']) ? (int) $team['id'] : 0,
                'position' => isset($row['position']) ? (int) $row['position'] : 0,
                'team_name' => isset($team['name']) ? (string) $team['name'] : '',
                'team_crest' => isset($team['crest']) ? (string) $team['crest'] : '',
                'played' => isset($row['playedGames']) ? (int) $row['playedGames'] : 0,
                'won' => isset($row['won']) ? (int) $row['won'] : 0,
                'draw' => isset($row['draw']) ? (int) $row['draw'] : 0,
                'lost' => isset($row['lost']) ? (int) $row['lost'] : 0,
                'goals_for' => isset($row['goalsFor']) ? (int) $row['goalsFor'] : 0,
                'goals_against' => isset($row['goalsAgainst']) ? (int) $row['goalsAgainst'] : 0,
                'goal_diff' => isset($row['goalDifference']) ? (int) $row['goalDifference'] : 0,
                'points' => isset($row['points']) ? (int) $row['points'] : 0,
            ];
        }

        return $rows;
    }

    public function get_next_premier_league_match(int $team_id, string $api_key, int $cache_ttl_seconds = self::CACHE_TTL_SECONDS)
    {
        $api_key = trim($api_key);
        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = self::CACHE_TTL_SECONDS;
        }

        if ($api_key === '') {
            return new WP_Error(
                'plt_missing_api_key',
                __('API key missing. Add your key under Settings -> Premier League Table.', 'premier-league-table')
            );
        }

        if ($team_id <= 0) {
            return new WP_Error(
                'plt_missing_team_id',
                __('Focus team could not be resolved. Save a valid focus team in plugin settings.', 'premier-league-table')
            );
        }

        $cache_key = self::NEXT_MATCH_CACHE_PREFIX . $team_id;
        $lock_key = self::NEXT_MATCH_LOCK_PREFIX . $team_id;

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['utc_date'])) {
            return $cached;
        }

        if (get_transient($lock_key)) {
            return new WP_Error(
                'plt_api_busy',
                __('Data opdateres lige nu. Proev igen om et oejeblik.', 'premier-league-table')
            );
        }

        set_transient($lock_key, '1', self::LOCK_TTL_SECONDS);

        try {
            $url = add_query_arg(
                [
                    'status' => 'SCHEDULED',
                    'limit' => '10',
                ],
                self::BASE_URL . '/teams/' . $team_id . '/matches'
            );

            $response = wp_remote_get(
                $url,
                [
                    'timeout' => 12,
                    'headers' => [
                        'X-Auth-Token' => $api_key,
                        'Accept' => 'application/json',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return new WP_Error(
                    'plt_api_http_error',
                    __('Kunne ikke hente kampdata fra football-data.org.', 'premier-league-table'),
                    ['details' => $response->get_error_message()]
                );
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);
            if ($status_code !== 200) {
                return new WP_Error(
                    'plt_api_bad_status',
                    sprintf(
                        __('API-fejl (%1$d) ved hentning af naeste kamp.', 'premier-league-table'),
                        $status_code
                    )
                );
            }

            $payload = json_decode($body, true);
            if (! is_array($payload) || ! isset($payload['matches']) || ! is_array($payload['matches'])) {
                return new WP_Error(
                    'plt_api_invalid_json',
                    __('Ugyldigt kampsvar fra API.', 'premier-league-table')
                );
            }

            $next_match = $this->extract_next_match($payload['matches'], $team_id);
            if (! is_array($next_match)) {
                return new WP_Error(
                    'plt_no_upcoming_match',
                    __('Ingen kommende kamp fundet for focus team.', 'premier-league-table')
                );
            }

            set_transient($cache_key, $next_match, $cache_ttl_seconds);
            return $next_match;
        } finally {
            delete_transient($lock_key);
        }
    }

    private function extract_next_match(array $matches, int $focus_team_id): ?array
    {
        $upcoming = [];
        foreach ($matches as $match) {
            if (! is_array($match) || empty($match['utcDate'])) {
                continue;
            }

            $utc_date = (string) $match['utcDate'];
            $timestamp = strtotime($utc_date);
            if ($timestamp === false || $timestamp < time()) {
                continue;
            }

            $upcoming[] = [
                'timestamp' => $timestamp,
                'match' => $match,
            ];
        }

        if ($upcoming === []) {
            return null;
        }

        usort(
            $upcoming,
            static function (array $a, array $b): int {
                return $a['timestamp'] <=> $b['timestamp'];
            }
        );

        $match = $upcoming[0]['match'];
        $home_team = isset($match['homeTeam']) && is_array($match['homeTeam']) ? $match['homeTeam'] : [];
        $away_team = isset($match['awayTeam']) && is_array($match['awayTeam']) ? $match['awayTeam'] : [];
        $home_id = isset($home_team['id']) ? (int) $home_team['id'] : 0;
        $away_id = isset($away_team['id']) ? (int) $away_team['id'] : 0;

        return [
            'competition' => isset($match['competition']['name']) ? (string) $match['competition']['name'] : 'Premier League',
            'utc_date' => (string) $match['utcDate'],
            'home_team' => [
                'id' => $home_id,
                'name' => isset($home_team['name']) ? (string) $home_team['name'] : '',
                'crest' => isset($home_team['crest']) ? (string) $home_team['crest'] : '',
            ],
            'away_team' => [
                'id' => $away_id,
                'name' => isset($away_team['name']) ? (string) $away_team['name'] : '',
                'crest' => isset($away_team['crest']) ? (string) $away_team['crest'] : '',
            ],
            'focus_side' => $focus_team_id === $home_id ? 'home' : 'away',
        ];
    }

    private function log_debug(string $message, array $context = []): void
    {
        if (! defined('WP_DEBUG') || WP_DEBUG !== true) {
            return;
        }

        $line = '[PLT] ' . $message;
        if (! empty($context)) {
            $json = wp_json_encode($context);
            if (is_string($json)) {
                $line .= ' ' . $json;
            }
        }

        error_log($line);
    }
}
