<?php

/**
 * Covers the preseason handling in PLT_Football_Data_Provider.
 *
 * Before the first matchday, football-data.org returns every club on position 1
 * — genuinely tied on nothing, but rendered verbatim it looks like broken data.
 * These tests pin the two behaviours that fix it: the table is flagged
 * preseason, and the meaningless positions are cleared and alphabetised.
 */

/**
 * Stands in for the real API client by overriding only the one method the
 * provider calls, so the provider can be exercised without HTTP or a key.
 */
class PLT_Fake_Api_Client extends PLT_Api_Client
{
    /** @var array|WP_Error */
    private $table;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function get_premier_league_table(string $api_key, int $cache_ttl_seconds = 600)
    {
        return $this->table;
    }
}

function football_data_test_row(string $name, int $position, int $played, int $points): array
{
    return [
        'team_id' => crc32($name),
        'position' => $position,
        'team_name' => $name,
        'team_crest' => 'https://crests.example/' . rawurlencode($name) . '.png',
        'played' => $played,
        'won' => 0,
        'draw' => 0,
        'lost' => 0,
        'goals_for' => 0,
        'goals_against' => 0,
        'goal_diff' => 0,
        'points' => $points,
    ];
}

function football_data_test_table(array $rows): array
{
    return [
        'competition' => 'Premier League',
        'season_id' => 2502,
        'season_start_date' => '2026-08-21',
        'season_end_date' => '2027-05-30',
        'current_matchday' => 1,
        'fetched_at' => gmdate('c'),
        'rows' => $rows,
    ];
}

MiniTest::suite('PLT_Football_Data_Provider preseason table', function (): void {
    // Exactly the shape the live feed serves before kickoff: everyone on
    // position 1, nobody having played. Deliberately not in alphabetical order,
    // so the re-sort is actually proven rather than coincidental.
    $preseasonRows = [
        football_data_test_row('Wolverhampton Wanderers FC', 1, 0, 0),
        football_data_test_row('Arsenal FC', 1, 0, 0),
        football_data_test_row('Tottenham Hotspur FC', 1, 0, 0),
        football_data_test_row('Chelsea FC', 1, 0, 0),
    ];

    $provider = new PLT_Football_Data_Provider(new PLT_Fake_Api_Client(football_data_test_table($preseasonRows)));
    $result = $provider->get_standings('pl', ['api_key' => 'test-key']);

    MiniTest::assertTrue(! is_wp_error($result), 'a preseason table is returned, not an error');
    MiniTest::assertSame('preseason', $result['data_mode'], 'a table where nobody has played is flagged preseason');
    MiniTest::assertSame(4, count($result['rows']), 'every club is still listed in preseason');

    $positions = array_map(static fn(array $row): int => (int) $row['position'], $result['rows']);
    MiniTest::assertSame([0, 0, 0, 0], $positions, 'the tied position-1 values are cleared rather than shown as a ranking');

    $names = array_map(static fn(array $row): string => (string) $row['team_name'], $result['rows']);
    MiniTest::assertSame(
        ['Arsenal FC', 'Chelsea FC', 'Tottenham Hotspur FC', 'Wolverhampton Wanderers FC'],
        $names,
        'preseason clubs are ordered alphabetically'
    );

    MiniTest::assertSame('2026-08-21', $result['season']['start_date'], 'the season start date survives for the preseason notice');
});

MiniTest::suite('PLT_Football_Data_Provider live table', function (): void {
    // As soon as a single match has been played the real ranking is meaningful
    // again, and the provider must stop interfering with it.
    $liveRows = [
        football_data_test_row('Arsenal FC', 1, 1, 3),
        football_data_test_row('Tottenham Hotspur FC', 2, 1, 1),
        football_data_test_row('Chelsea FC', 3, 0, 0),
    ];

    $provider = new PLT_Football_Data_Provider(new PLT_Fake_Api_Client(football_data_test_table($liveRows)));
    $result = $provider->get_standings('pl', ['api_key' => 'test-key']);

    MiniTest::assertSame('live', $result['data_mode'], 'one played match is enough to make the table live');

    $positions = array_map(static fn(array $row): int => (int) $row['position'], $result['rows']);
    MiniTest::assertSame([1, 2, 3], $positions, 'live positions are passed through untouched');

    $names = array_map(static fn(array $row): string => (string) $row['team_name'], $result['rows']);
    MiniTest::assertSame(
        ['Arsenal FC', 'Tottenham Hotspur FC', 'Chelsea FC'],
        $names,
        'live row order is left exactly as the provider ranked it, not re-sorted alphabetically'
    );
});

MiniTest::suite('PLT_Football_Data_Provider error and competition handling', function (): void {
    $provider = new PLT_Football_Data_Provider(new PLT_Fake_Api_Client(football_data_test_table([])));

    $wrongCompetition = $provider->get_standings('wsl', ['api_key' => 'test-key']);
    MiniTest::assertTrue(is_wp_error($wrongCompetition), 'the PL provider refuses a WSL request rather than guessing');

    $upstreamError = new PLT_Football_Data_Provider(
        new PLT_Fake_Api_Client(new WP_Error('plt_api_bad_status', 'API-fejl (403)'))
    );
    $result = $upstreamError->get_standings('pl', ['api_key' => 'test-key']);
    MiniTest::assertTrue(is_wp_error($result), 'an upstream API error is passed through, not swallowed into an empty table');
});
