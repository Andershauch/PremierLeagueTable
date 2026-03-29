<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Api_Client
{
    private const BASE_URL = 'https://www.thesportsdb.com/api/v1/json';
    private const DEFAULT_API_KEY = '123';
    private const CACHE_TTL_SECONDS = 600;
    private const LOCK_TTL_SECONDS = 20;

    public static function get_competition_map(): array
    {
        return [
            'epl' => [
                'league_id' => 4328,
                'label' => 'Premier League',
                'short_label' => 'Premier League',
            ],
            'wsl' => [
                'league_id' => 4849,
                'label' => "Women's Super League",
                'short_label' => 'WSL',
            ],
        ];
    }

    public static function get_default_competition(): string
    {
        return 'epl';
    }

    public static function sanitize_competition(string $competition): string
    {
        $competition = sanitize_key($competition);
        $map = self::get_competition_map();

        return isset($map[$competition]) ? $competition : self::get_default_competition();
    }

    public static function get_competition_label(string $competition): string
    {
        $competition = self::sanitize_competition($competition);
        $map = self::get_competition_map();

        return (string) $map[$competition]['label'];
    }

    public static function get_cache_key(string $competition = 'epl'): string
    {
        $competition = self::sanitize_competition($competition);
        return 'plt_tsd_standings_' . $competition . '_v1';
    }

    public static function get_lock_key(string $competition = 'epl'): string
    {
        $competition = self::sanitize_competition($competition);
        return 'plt_tsd_standings_' . $competition . '_lock_v1';
    }

    public static function flush_cache(): void
    {
        foreach (array_keys(self::get_competition_map()) as $competition) {
            delete_transient(self::get_cache_key($competition));
            delete_transient(self::get_lock_key($competition));
        }

        delete_transient('plt_pl_standings_v1');
        delete_transient('plt_pl_standings_lock_v1');
        delete_transient('plt_standings_api_football_v1');
        delete_transient('plt_standings_api_football_lock_v1');
    }

    public function get_standings(string $competition, string $api_key, int $cache_ttl_seconds = self::CACHE_TTL_SECONDS)
    {
        $competition = self::sanitize_competition($competition);
        $api_key = $this->sanitize_api_key($api_key);
        $cache_key = self::get_cache_key($competition);
        $lock_key = self::get_lock_key($competition);
        $config = self::get_competition_map()[$competition];

        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = self::CACHE_TTL_SECONDS;
        }

        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['rows']) && is_array($cached['rows'])) {
            return $cached;
        }

        if (get_transient($lock_key)) {
            $this->log_debug('Cache lock active, skipping duplicate standings fetch.', ['competition' => $competition]);
            return new WP_Error(
                'plt_api_busy',
                __('Standings are refreshing right now. Please try again in a moment.', 'premier-league-table')
            );
        }

        set_transient($lock_key, '1', self::LOCK_TTL_SECONDS);

        try {
            $season = $this->get_current_season_label();
            $url = sprintf(
                '%1$s/%2$s/lookuptable.php?l=%3$d&s=%4$s',
                self::BASE_URL,
                rawurlencode($api_key),
                (int) $config['league_id'],
                rawurlencode($season)
            );

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
                $error = new WP_Error(
                    'plt_api_http_error',
                    __('Could not reach TheSportsDB.', 'premier-league-table'),
                    ['details' => $response->get_error_message()]
                );
                $this->log_debug('HTTP request failed.', ['competition' => $competition, 'details' => $response->get_error_message()]);
                return $error;
            }

            $status_code = (int) wp_remote_retrieve_response_code($response);
            $body = (string) wp_remote_retrieve_body($response);
            $payload = json_decode($body, true);

            if (! is_array($payload)) {
                $error = new WP_Error(
                    'plt_api_invalid_json',
                    __('Invalid JSON response from TheSportsDB.', 'premier-league-table')
                );
                $this->log_debug('Invalid JSON payload from API.', ['competition' => $competition, 'status_code' => $status_code]);
                return $error;
            }

            if ($status_code !== 200) {
                $message = __('TheSportsDB returned an unexpected error.', 'premier-league-table');
                $error = new WP_Error(
                    'plt_api_bad_status',
                    sprintf(
                        /* translators: 1: HTTP status code, 2: API message */
                        __('TheSportsDB error (%1$d): %2$s', 'premier-league-table'),
                        $status_code,
                        $message
                    )
                );
                $this->log_debug('API returned non-200 status.', ['competition' => $competition, 'status_code' => $status_code]);
                return $error;
            }

            $rows = $this->extract_standings_rows($payload);
            if ($rows === []) {
                return new WP_Error(
                    'plt_api_empty_standings',
                    sprintf(
                        /* translators: %s: competition label */
                        __('No current standings are available from TheSportsDB for %s right now.', 'premier-league-table'),
                        self::get_competition_label($competition)
                    )
                );
            }

            $first_row = $payload['table'][0] ?? [];
            $normalized = [
                'competition' => isset($first_row['strLeague']) ? (string) $first_row['strLeague'] : self::get_competition_label($competition),
                'competition_key' => $competition,
                'provider' => 'TheSportsDB',
                'season' => isset($first_row['strSeason']) ? (string) $first_row['strSeason'] : $season,
                'last_updated' => isset($first_row['dateUpdated']) ? (string) $first_row['dateUpdated'] : '',
                'rows' => $rows,
            ];

            set_transient($cache_key, $normalized, $cache_ttl_seconds);

            return $normalized;
        } finally {
            delete_transient($lock_key);
        }
    }

    private function sanitize_api_key(string $api_key): string
    {
        $api_key = sanitize_text_field(trim($api_key));
        if ($api_key === '') {
            return self::DEFAULT_API_KEY;
        }

        // TheSportsDB v1 uses numeric keys in the request path.
        if (! preg_match('/^\d+$/', $api_key)) {
            return self::DEFAULT_API_KEY;
        }

        return $api_key;
    }

    private function get_current_season_label(): string
    {
        $timestamp = current_time('timestamp');
        $year = (int) gmdate('Y', $timestamp);
        $month = (int) gmdate('n', $timestamp);
        $start_year = $month >= 7 ? $year : $year - 1;
        $end_year = $start_year + 1;

        return $start_year . '-' . $end_year;
    }

    private function extract_standings_rows(array $payload): array
    {
        if (! isset($payload['table']) || ! is_array($payload['table'])) {
            return [];
        }

        $rows = [];
        foreach ($payload['table'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
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

        return $rows;
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
