<?php

/**
 * Exercises PLT_WPLL_Standings_Provider::build_rows() directly (via reflection,
 * since it's private) against real API responses captured on 2026-07-30. This
 * is pure data transformation with no wall-clock dependency, so these fixtures
 * stay valid forever regardless of when the suite runs.
 */
MiniTest::suite('PLT_WPLL_Standings_Provider row building (finished 2025-26 season)', function (): void {
    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);

    $payload = json_decode(
        file_get_contents(PLT_TESTS_FIXTURES_DIR . 'wpll-standings-2025-26-finished-season.json'),
        true
    );
    $rows = invoke_private_method($provider, 'build_rows', [$payload['teams']]);

    MiniTest::assertSame(12, count($rows), 'finished 2025-26 season has 12 rows');

    $first = $rows[0];
    MiniTest::assertSame('Manchester City', $first['team_name'], 'rank 1 is Manchester City');
    MiniTest::assertSame(1, $first['position'], 'rank 1 has position 1');
    MiniTest::assertSame(55, $first['points'], 'Manchester City finished on 55 points');
    MiniTest::assertSame(22, $first['played'], 'Manchester City played 22 matches');
    MiniTest::assertSame(43, $first['goal_diff'], 'Manchester City goal difference is 43');
    MiniTest::assertTrue(
        strpos($first['team_crest'], 'https://media-sdp.wslfootball.com/') === 0,
        'crest URL is resolved to the media CDN'
    );

    // Table must be sorted strictly by rank, ascending.
    $positions = array_column($rows, 'position');
    $sorted = $positions;
    sort($sorted);
    MiniTest::assertSame($sorted, $positions, 'rows are returned in rank order');

    $names = array_column($rows, 'team_name');
    MiniTest::assertTrue(in_array('Tottenham Hotspur', $names, true), 'Tottenham Hotspur is present using PL-style naming, not "Tottenham Women"');
});

MiniTest::suite('PLT_WPLL_Standings_Provider row building (2026-27 preseason 0-table)', function (): void {
    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);

    $payload = json_decode(
        file_get_contents(PLT_TESTS_FIXTURES_DIR . 'wpll-standings-2026-27-preseason.json'),
        true
    );
    $rows = invoke_private_method($provider, 'build_rows', [$payload['teams']]);

    MiniTest::assertSame(14, count($rows), 'preseason 2026-27 roster has 14 rows (the confirmed 12->14 club expansion)');

    foreach ($rows as $row) {
        MiniTest::assertSame(0, $row['played'], "{$row['team_name']} has 0 played before the season starts");
        MiniTest::assertSame(0, $row['points'], "{$row['team_name']} has 0 points before the season starts");
    }
});
