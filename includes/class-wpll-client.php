<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Low-level client for the WSL Football / WPLL data feed (the JSON API behind
 * wslfootball.com's own standings and fixtures pages). Undocumented but public,
 * unauthenticated, and CORS-open; it is Opta-backed and used directly by the
 * official league site, so it is treated as the primary WSL data source with
 * TheSportsDB kept as an automatic fallback if this feed ever breaks or changes shape.
 */
class PLT_WPLL_Client
{
    private const BASE_URL = 'https://api-sdp.wslfootball.com/v1/wpll/football';
    private const IMAGE_BASE_URL = 'https://media-sdp.wslfootball.com/';
    private const COMPETITION_SHORT_NAME = 'WSL';
    private const DISCOVERY_CACHE_KEY = 'plt_wpll_discovery_v1';
    private const DISCOVERY_CACHE_TTL_SECONDS = 6 * HOUR_IN_SECONDS;
    private const STANDINGS_CACHE_PREFIX = 'plt_wpll_standings_v1_';
    private const MATCHES_CACHE_PREFIX = 'plt_wpll_matches_v1_';
    private const NEXT_MATCH_LOCK_PREFIX = 'plt_wpll_next_match_lock_v1_';
    private const LOCK_TTL_SECONDS = 20;

    /**
     * @return array|WP_Error {season: array, teams: array, data_mode: string}
     */
    public function get_standings(int $cache_ttl_seconds = 1800)
    {
        $season = $this->resolve_current_season();
        if (is_wp_error($season)) {
            return $season;
        }

        $cache_key = self::STANDINGS_CACHE_PREFIX . md5((string) $season['season_id']);
        $cached = get_transient($cache_key);
        if (is_array($cached) && isset($cached['teams'])) {
            return $cached;
        }

        $payload = $this->fetch_json(
            self::BASE_URL . '/seasons/' . $season['season_id'] . '/standings',
            'plt_wpll_standings_invalid_response',
            __('The WSL Football feed returned an invalid standings response.', 'premier-league-table')
        );
        if (is_wp_error($payload)) {
            return $payload;
        }

        $teams = isset($payload['teams']) && is_array($payload['teams']) ? $payload['teams'] : [];
        $result = [
            'season' => $season,
            'teams' => $teams,
            'data_mode' => $season['phase'],
        ];

        set_transient($cache_key, $result, max(60, $cache_ttl_seconds));

        return $result;
    }

    /**
     * @return array|WP_Error
     */
    public function get_next_wsl_match(string $primary_team_name, string $fallback_team_name, int $cache_ttl_seconds = 600)
    {
        $primary_team_name = trim($primary_team_name);
        $fallback_team_name = trim($fallback_team_name);
        if ($primary_team_name === '' && $fallback_team_name === '') {
            return new WP_Error(
                'plt_missing_wsl_team_name',
                __('WSL focus team could not be resolved.', 'premier-league-table')
            );
        }

        $lock_key = self::NEXT_MATCH_LOCK_PREFIX . md5(strtolower($primary_team_name . '|' . $fallback_team_name));
        if (get_transient($lock_key)) {
            return new WP_Error(
                'plt_api_busy',
                __('Data opdateres lige nu. Prøv igen om et øjeblik.', 'premier-league-table')
            );
        }

        set_transient($lock_key, '1', self::LOCK_TTL_SECONDS);

        try {
            $season = $this->resolve_current_season();
            if (is_wp_error($season)) {
                return $season;
            }

            $matches = $this->get_matches_for_season($season, $cache_ttl_seconds);
            if (is_wp_error($matches)) {
                return $matches;
            }

            $match = $this->find_next_match_for_team($matches, $primary_team_name, $fallback_team_name);
            if (! is_array($match)) {
                return new WP_Error(
                    'plt_no_upcoming_wsl_match',
                    sprintf(
                        __('Ingen kommende WSL-kamp fundet for %s lige nu. Det er normalt mellem sæsoner eller før næste spilleplan er offentliggjort.', 'premier-league-table'),
                        $primary_team_name !== '' ? $primary_team_name : $fallback_team_name
                    )
                );
            }

            return $match;
        } finally {
            delete_transient($lock_key);
        }
    }

    /**
     * @return array|WP_Error
     */
    private function get_matches_for_season(array $season, int $cache_ttl_seconds)
    {
        $cache_key = self::MATCHES_CACHE_PREFIX . md5((string) $season['season_id']);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $payload = $this->fetch_json(
            self::BASE_URL . '/seasons/' . $season['season_id'] . '/matches',
            'plt_wpll_matches_unavailable',
            __('Ingen kommende WSL-kamp fundet lige nu. Det er normalt mellem sæsoner eller før næste spilleplan er offentliggjort.', 'premier-league-table')
        );
        if (is_wp_error($payload)) {
            return $payload;
        }

        $matches = isset($payload['matches']) && is_array($payload['matches']) ? $payload['matches'] : [];
        set_transient($cache_key, $matches, max(60, $cache_ttl_seconds));

        return $matches;
    }

    private function find_next_match_for_team(array $matches, string $primary_team_name, string $fallback_team_name): ?array
    {
        $primary_normalized = $this->normalize_team_name($primary_team_name);
        $fallback_normalized = $this->normalize_team_name($fallback_team_name);
        $now = time();

        $upcoming = [];
        foreach ($matches as $match) {
            if (! is_array($match)) {
                continue;
            }

            $home = isset($match['home']) && is_array($match['home']) ? $match['home'] : [];
            $away = isset($match['away']) && is_array($match['away']) ? $match['away'] : [];
            $is_home = $this->team_matches($home, $primary_normalized, $fallback_normalized);
            $is_away = $this->team_matches($away, $primary_normalized, $fallback_normalized);
            if (! $is_home && ! $is_away) {
                continue;
            }

            $timestamp = $this->extract_timestamp((string) ($match['matchDateUtc'] ?? ''));
            if ($timestamp === null || $timestamp < $now) {
                continue;
            }

            $upcoming[] = [
                'timestamp' => $timestamp,
                'match' => $match,
                'focus_side' => $is_home ? 'home' : 'away',
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

        $entry = $upcoming[0];
        $match = $entry['match'];
        $home = isset($match['home']) && is_array($match['home']) ? $match['home'] : [];
        $away = isset($match['away']) && is_array($match['away']) ? $match['away'] : [];

        return [
            'competition' => 'Women\'s Super League',
            'utc_date' => gmdate('c', $entry['timestamp']),
            'home_team' => [
                'id' => $this->hash_team_id((string) ($home['teamId'] ?? '')),
                'name' => (string) ($home['officialName'] ?? $home['shortName'] ?? ''),
                'crest' => $this->resolve_image_url((string) ($home['imagery']['teamLogo'] ?? '')),
            ],
            'away_team' => [
                'id' => $this->hash_team_id((string) ($away['teamId'] ?? '')),
                'name' => (string) ($away['officialName'] ?? $away['shortName'] ?? ''),
                'crest' => $this->resolve_image_url((string) ($away['imagery']['teamLogo'] ?? '')),
            ],
            'focus_side' => $entry['focus_side'],
        ];
    }

    private function team_matches(array $team, string $primary_normalized, string $fallback_normalized): bool
    {
        $official = $this->normalize_team_name((string) ($team['officialName'] ?? ''));
        $short = $this->normalize_team_name((string) ($team['shortName'] ?? ''));

        foreach ([$primary_normalized, $fallback_normalized] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if ($official === $candidate || $short === $candidate) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array|WP_Error {season_id: string, label: string, start_date: string, end_date: string, phase: string}
     */
    private function resolve_current_season()
    {
        $discovery = $this->get_discovery();
        if (is_wp_error($discovery)) {
            return $discovery;
        }

        $seasons = $discovery['seasons'];
        if ($seasons === []) {
            return new WP_Error(
                'plt_wpll_no_seasons',
                __('The WSL Football feed did not return any WSL seasons.', 'premier-league-table')
            );
        }

        $now = time();
        $live = null;
        $nearest_upcoming = null;
        $latest_past = null;

        foreach ($seasons as $season) {
            $start = strtotime((string) $season['startDateUtc']);
            $end = strtotime((string) $season['endDateUtc']);
            if ($start === false || $end === false) {
                continue;
            }

            if ($now >= $start && $now <= $end) {
                $live = ['season' => $season, 'start' => $start, 'end' => $end];
                continue;
            }

            if ($start > $now && ($nearest_upcoming === null || $start < $nearest_upcoming['start'])) {
                $nearest_upcoming = ['season' => $season, 'start' => $start, 'end' => $end];
            }

            if ($end < $now && ($latest_past === null || $end > $latest_past['end'])) {
                $latest_past = ['season' => $season, 'start' => $start, 'end' => $end];
            }
        }

        if ($live !== null) {
            $chosen = $live;
            $phase = 'live';
        } elseif ($nearest_upcoming !== null) {
            $chosen = $nearest_upcoming;
            $phase = 'preseason';
        } elseif ($latest_past !== null) {
            $chosen = $latest_past;
            $phase = 'live';
        } else {
            return new WP_Error(
                'plt_wpll_no_current_season',
                __('The WSL Football feed did not return a usable current WSL season.', 'premier-league-table')
            );
        }

        return [
            'season_id' => (string) $chosen['season']['seasonId'],
            'label' => (string) ($chosen['season']['seasonName'] ?? ''),
            'start_date' => gmdate('c', $chosen['start']),
            'end_date' => gmdate('c', $chosen['end']),
            'phase' => $phase,
        ];
    }

    /**
     * @return array|WP_Error {competition_id: string, seasons: array}
     */
    private function get_discovery()
    {
        $cached = get_transient(self::DISCOVERY_CACHE_KEY);
        if (is_array($cached) && isset($cached['competition_id'], $cached['seasons'])) {
            return $cached;
        }

        $competitions_payload = $this->fetch_json(
            self::BASE_URL . '/competitions',
            'plt_wpll_competitions_invalid_response',
            __('The WSL Football feed returned an invalid competitions response.', 'premier-league-table')
        );
        if (is_wp_error($competitions_payload)) {
            return $competitions_payload;
        }

        $competitions = isset($competitions_payload['competitions']) && is_array($competitions_payload['competitions'])
            ? $competitions_payload['competitions']
            : [];

        $competition_id = '';
        foreach ($competitions as $competition) {
            if (! is_array($competition)) {
                continue;
            }

            if ((string) ($competition['shortName'] ?? '') === self::COMPETITION_SHORT_NAME) {
                $competition_id = (string) ($competition['competitionId'] ?? '');
                break;
            }
        }

        if ($competition_id === '') {
            return new WP_Error(
                'plt_wpll_competition_not_found',
                __('The WSL Football feed did not list the Women\'s Super League competition.', 'premier-league-table')
            );
        }

        $seasons_payload = $this->fetch_json(
            self::BASE_URL . '/competitions/' . $competition_id . '/seasons',
            'plt_wpll_seasons_invalid_response',
            __('The WSL Football feed returned an invalid seasons response.', 'premier-league-table')
        );
        if (is_wp_error($seasons_payload)) {
            return $seasons_payload;
        }

        $seasons = isset($seasons_payload['seasons']) && is_array($seasons_payload['seasons'])
            ? $seasons_payload['seasons']
            : [];

        $discovery = [
            'competition_id' => $competition_id,
            'seasons' => $seasons,
        ];

        set_transient(self::DISCOVERY_CACHE_KEY, $discovery, self::DISCOVERY_CACHE_TTL_SECONDS);

        return $discovery;
    }

    private function fetch_json(string $url, string $error_code, string $error_message)
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
                'plt_wpll_http_error',
                __('Could not fetch WSL data from the WSL Football feed.', 'premier-league-table'),
                ['details' => $response->get_error_message()]
            );
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $body = (string) wp_remote_retrieve_body($response);
        $payload = json_decode($body, true);

        if ($status_code !== 200 || ! is_array($payload)) {
            return new WP_Error($error_code, $error_message);
        }

        return $payload;
    }

    private function extract_timestamp(string $date_string): ?int
    {
        $date_string = trim($date_string);
        if ($date_string === '') {
            return null;
        }

        $timestamp = strtotime($date_string);

        return $timestamp === false ? null : $timestamp;
    }

    public function resolve_image_url(string $relative_path): string
    {
        $relative_path = trim($relative_path);
        if ($relative_path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $relative_path) === 1) {
            return $relative_path;
        }

        return self::IMAGE_BASE_URL . ltrim($relative_path, '/');
    }

    public function hash_team_id(string $provider_team_id): int
    {
        if ($provider_team_id === '') {
            return 0;
        }

        return (int) crc32($provider_team_id);
    }

    public function normalize_team_name(string $name): string
    {
        $name = remove_accents(strtolower(trim($name)));
        $name = preg_replace('/\b(fc|afc|cf|wfc)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]+/u', ' ', (string) $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }
}
