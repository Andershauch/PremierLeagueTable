<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Football_Data_Provider implements PLT_Standings_Provider
{
    private const DATA_MODE_LIVE = 'live';
    private const DATA_MODE_PRESEASON = 'preseason';

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

        $rows = isset($table['rows']) && is_array($table['rows']) ? $table['rows'] : [];
        $data_mode = $this->resolve_data_mode($rows);
        if ($data_mode === self::DATA_MODE_PRESEASON) {
            $rows = $this->build_preseason_rows($rows);
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
            'data_mode' => $data_mode,
            'rows' => $rows,
            'raw' => $table,
        ];
    }

    /**
     * Before a ball has been kicked, football-data.org hands back every club on
     * position 1 (they are genuinely all tied on nothing). Rendering that
     * verbatim produces a table where all twenty clubs are shown as first,
     * which reads as broken data rather than as an unstarted season — so the
     * table is flagged as preseason instead and the renderer drops the
     * meaningless position numbers.
     *
     * Keyed off matches actually played rather than the season start date,
     * because `currentMatchday` is already 1 during preseason and a postponed
     * opening weekend would otherwise flip the table to "live" with nothing in
     * it.
     */
    private function resolve_data_mode(array $rows): string
    {
        if ($rows === []) {
            return self::DATA_MODE_PRESEASON;
        }

        foreach ($rows as $row) {
            if (is_array($row) && (int) ($row['played'] ?? 0) > 0) {
                return self::DATA_MODE_LIVE;
            }
        }

        return self::DATA_MODE_PRESEASON;
    }

    /**
     * Zeroes the tied position numbers and puts the clubs in a stable
     * alphabetical order, so the preseason table is presented as a squad list
     * rather than as a ranking nobody has earned yet.
     */
    private function build_preseason_rows(array $rows): array
    {
        $preseason_rows = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $row['position'] = 0;
            $preseason_rows[] = $row;
        }

        usort(
            $preseason_rows,
            static function (array $a, array $b): int {
                return strcasecmp((string) ($a['team_name'] ?? ''), (string) ($b['team_name'] ?? ''));
            }
        );

        return $preseason_rows;
    }
}
