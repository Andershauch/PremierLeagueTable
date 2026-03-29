<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Api_Client
{
    private const BASE_URL = 'https://v3.football.api-sports.io';
    private const LEAGUES_ENDPOINT = '/leagues';
    private const STANDINGS_ENDPOINT = '/standings';
    private const CACHE_KEY = 'plt_standings_api_football_v1';
    private const CACHE_LOCK_KEY = 'plt_standings_api_football_lock_v1';
    private const LEGACY_CACHE_KEY = 'plt_pl_standings_v1';
    private const LEGACY_CACHE_LOCK_KEY = 'plt_pl_standings_lock_v1';
    private const CACHE_TTL_SECONDS = 600;
    private const LOCK_TTL_SECONDS = 20;
    private const PREMIER_LEAGUE_ID = 39;

    public static function get_cache_key(): string
    {
        return self::CACHE_KEY;
    }

    public static function flush_cache(): void
    {
        delete_transient(self::CACHE_KEY);
        delete_transient(self::CACHE_LOCK_KEY);
        delete_transient(self::LEGACY_CACHE_KEY);
        delete_transient(self::LEGACY_CACHE_LOCK_KEY);
    }

    public function get_premier_league_table(string $api_key, int $cache_ttl_seconds = self::CACHE_TTL_SECONDS)
    {
        $api_key = trim($api_key);
        if ($cache_ttl_seconds < 60) {
            $cache_ttl_seconds = self::CACHE_TTL_SECONDS;
        }

        if ($api_key === '') {
            $error = new WP_Error(
                'plt_missing_api_key',
                __('API key missing. Add your API-Football key under Settings -> Premier League Table.', 'premier-league-table')
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
                __('Standings are refreshing right now. Please try again in a moment.', 'premier-league-table')
            );
        }

        set_transient(self::CACHE_LOCK_KEY, '1', self::LOCK_TTL_SECONDS);

        try {
            $league_context = $this->get_premier_league_context($api_key);
            if (is_wp_error($league_context)) {
                return $league_context;
            }

            $standings_url = add_query_arg(
                [
                    'league' => (int) $league_context['league_id'],
                    'season' => (int) $league_context['season'],
                ],
                self::BASE_URL . self::STANDINGS_ENDPOINT
            );

            $response = $this->perform_request($standings_url, $api_key);
            if (is_wp_error($response)) {
                return $response;
            }

            $rows = $this->extract_total_standings_rows($response);
            if (empty($rows)) {
                $error = new WP_Error(
                    'plt_api_empty_standings',
                    __('Could not find standings data in the API-Football response.', 'premier-league-table')
                );
                $this->log_debug('No standings rows found in payload.');
                return $error;
            }

            $league_payload = $response['response'][0]['league'] ?? [];
            $normalized = [
                'competition' => isset($league_payload['name']) ? (string) $league_payload['name'] : 'Premier League',
                'season' => isset($league_payload['season']) ? (int) $league_payload['season'] : (int) $league_context['season'],
                'provider' => 'API-Football',
                'rows' => $rows,
            ];

            set_transient(self::CACHE_KEY, $normalized, $cache_ttl_seconds);

            return $normalized;
        } finally {
            delete_transient(self::CACHE_LOCK_KEY);
        }
    }

    private function get_premier_league_context(string $api_key)
    {
        $leagues_url = add_query_arg(
            [
                'country' => 'England',
                'search' => 'Premier League',
                'current' => 'true',
            ],
            self::BASE_URL . self::LEAGUES_ENDPOINT
        );

        $response = $this->perform_request($leagues_url, $api_key);
        if (is_wp_error($response)) {
            return $response;
        }

        if (! isset($response['response']) || ! is_array($response['response'])) {
            return new WP_Error(
                'plt_api_invalid_leagues_payload',
                __('API-Football returned an unexpected leagues response.', 'premier-league-table')
            );
        }

        foreach ($response['response'] as $league_item) {
            if (! is_array($league_item)) {
                continue;
            }

            $league = isset($league_item['league']) && is_array($league_item['league']) ? $league_item['league'] : [];
            $seasons = isset($league_item['seasons']) && is_array($league_item['seasons']) ? $league_item['seasons'] : [];

            if ((int) ($league['id'] ?? 0) !== self::PREMIER_LEAGUE_ID) {
                continue;
            }

            foreach ($seasons as $season) {
                if (! is_array($season)) {
                    continue;
                }

                $is_current = ! empty($season['current']);
                $has_standings = ! empty($season['coverage']['standings']);

                if ($is_current && $has_standings && isset($season['year'])) {
                    return [
                        'league_id' => self::PREMIER_LEAGUE_ID,
                        'season' => (int) $season['year'],
                    ];
                }
            }
        }

        return [
            'league_id' => self::PREMIER_LEAGUE_ID,
            'season' => $this->get_fallback_season_year(),
        ];
    }

    private function perform_request(string $url, string $api_key)
    {
        $response = wp_remote_get(
            $url,
            [
                'timeout' => 12,
                'headers' => [
                    'x-apisports-key' => $api_key,
                    'Accept' => 'application/json',
                ],
            ]
        );

        if (is_wp_error($response)) {
            $error = new WP_Error(
                'plt_api_http_error',
                __('Could not reach API-Football.', 'premier-league-table'),
                ['details' => $response->get_error_message()]
            );
            $this->log_debug('HTTP request failed.', ['details' => $response->get_error_message()]);
            return $error;
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            $error = new WP_Error(
                'plt_api_invalid_json',
                __('Invalid JSON response from API-Football.', 'premier-league-table')
            );
            $this->log_debug('Invalid JSON payload from API.', ['status_code' => $status_code]);
            return $error;
        }

        if ($status_code !== 200) {
            $message = $this->extract_api_error_message($payload);
            if ($message === '') {
                $message = __('API-Football returned an unknown error.', 'premier-league-table');
            }
            $error = new WP_Error(
                'plt_api_bad_status',
                sprintf(
                    /* translators: 1: HTTP status code, 2: API message */
                    __('API-Football error (%1$d): %2$s', 'premier-league-table'),
                    $status_code,
                    $message
                )
            );
            $this->log_debug('API returned non-200 status.', ['status_code' => $status_code, 'message' => $message]);
            return $error;
        }

        $api_error = $this->extract_api_error_message($payload);
        if ($api_error !== '') {
            $error = new WP_Error(
                'plt_api_error',
                $api_error
            );
            $this->log_debug('API returned application error.', ['message' => $api_error]);
            return $error;
        }

        return $payload;
    }

    private function extract_total_standings_rows(array $payload): array
    {
        $standings = $payload['response'][0]['league']['standings'] ?? null;
        if (! is_array($standings) || $standings === []) {
            return [];
        }

        $table = [];
        foreach ($standings as $group) {
            if (! is_array($group) || $group === []) {
                continue;
            }

            $table = $group;
            break;
        }

        if ($table === []) {
            return [];
        }

        $rows = [];
        foreach ($table as $row) {
            if (! is_array($row)) {
                continue;
            }

            $team = isset($row['team']) && is_array($row['team']) ? $row['team'] : [];
            $stats = isset($row['all']) && is_array($row['all']) ? $row['all'] : [];
            $goals = isset($stats['goals']) && is_array($stats['goals']) ? $stats['goals'] : [];

            $rows[] = [
                'position' => isset($row['rank']) ? (int) $row['rank'] : 0,
                'team_name' => isset($team['name']) ? (string) $team['name'] : '',
                'team_crest' => isset($team['logo']) ? (string) $team['logo'] : '',
                'played' => isset($stats['played']) ? (int) $stats['played'] : 0,
                'won' => isset($stats['win']) ? (int) $stats['win'] : 0,
                'draw' => isset($stats['draw']) ? (int) $stats['draw'] : 0,
                'lost' => isset($stats['lose']) ? (int) $stats['lose'] : 0,
                'goals_for' => isset($goals['for']) ? (int) $goals['for'] : 0,
                'goals_against' => isset($goals['against']) ? (int) $goals['against'] : 0,
                'goal_diff' => isset($row['goalsDiff']) ? (int) $row['goalsDiff'] : 0,
                'points' => isset($row['points']) ? (int) $row['points'] : 0,
            ];
        }

        return $rows;
    }

    private function extract_api_error_message(array $payload): string
    {
        if (isset($payload['errors']) && is_string($payload['errors']) && trim($payload['errors']) !== '') {
            return $this->format_api_error_message($payload['errors']);
        }

        if (isset($payload['errors']) && is_array($payload['errors'])) {
            foreach ($payload['errors'] as $error_value) {
                if (is_string($error_value) && trim($error_value) !== '') {
                    return $this->format_api_error_message($error_value);
                }
            }
        }

        if (isset($payload['message']) && is_string($payload['message']) && trim($payload['message']) !== '') {
            return $this->format_api_error_message($payload['message']);
        }

        return '';
    }

    private function format_api_error_message(string $message): string
    {
        $message = trim($message);
        if ($message === '') {
            return __('API-Football returned an unknown error.', 'premier-league-table');
        }

        $normalized = strtolower($message);
        if (strpos($normalized, 'suspended') !== false) {
            return __('Your API-Football account is suspended. Check the API-SPORTS dashboard before using this plugin key.', 'premier-league-table');
        }

        return $message;
    }

    private function get_fallback_season_year(): int
    {
        $timestamp = current_time('timestamp');
        $year = (int) gmdate('Y', $timestamp);
        $month = (int) gmdate('n', $timestamp);

        return $month >= 7 ? $year : $year - 1;
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
