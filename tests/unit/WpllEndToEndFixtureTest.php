<?php

/**
 * Exercises the full public PLT_WPLL_Client::get_standings() ->
 * PLT_WPLL_Standings_Provider::get_standings() path end to end, through the
 * fixture HTTP layer. Season dates are synthetic/relative-to-now (so the
 * live-vs-preseason branch stays correct forever); the standings payloads
 * themselves are the real captured API responses, so this also confirms the
 * real response shape still parses correctly.
 */
MiniTest::suite('End to end: live season standings via get_standings()', function (): void {
    fixture_http_reset();
    mini_test_reset_transients();
    $now = time();

    // The standings URL ("/seasons/{id}/standings") also contains "/seasons",
    // so the more specific "e2e-live" (standings) route must be registered
    // BEFORE the generic "/seasons" (discovery) route -- fixture_http_get
    // matches in registration order.
    $realStandingsPayload = json_decode(
        file_get_contents(PLT_TESTS_FIXTURES_DIR . 'wpll-standings-2025-26-finished-season.json'),
        true
    );
    fixture_http_register('e2e-live', 200, $realStandingsPayload);
    fixture_http_register('/seasons', 200, [
        'seasons' => [[
            'seasonId' => 'test::season::e2e-live',
            'seasonName' => 'Synthetic E2E Live Season',
            'startDateUtc' => gmdate('c', $now - (10 * DAY_IN_SECONDS)),
            'endDateUtc' => gmdate('c', $now + (200 * DAY_IN_SECONDS)),
        ]],
    ]);
    fixture_http_register('/competitions', 200, [
        'competitions' => [['competitionId' => 'test::competition::wsl', 'shortName' => 'WSL', 'name' => "Women's Super League"]],
    ]);

    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);
    $result = $provider->get_standings('wsl', ['cache_ttl_seconds' => 1800]);

    MiniTest::assertTrue(! is_wp_error($result), 'get_standings succeeds end to end');
    MiniTest::assertSame('wpll', $result['provider'], 'provider key is wpll');
    MiniTest::assertSame('live', $result['data_mode'], 'a season containing "now" reports data_mode=live');
    MiniTest::assertSame(12, count($result['rows']), 'the real 12-team payload comes through unchanged');
    MiniTest::assertSame('Manchester City', $result['rows'][0]['team_name'], 'rank 1 is still Manchester City after the full round trip');
});

MiniTest::suite('End to end: preseason standings via get_standings()', function (): void {
    fixture_http_reset();
    mini_test_reset_transients();
    $now = time();

    $realPreseasonPayload = json_decode(
        file_get_contents(PLT_TESTS_FIXTURES_DIR . 'wpll-standings-2026-27-preseason.json'),
        true
    );
    fixture_http_register('e2e-preseason', 200, $realPreseasonPayload);
    fixture_http_register('/seasons', 200, [
        'seasons' => [[
            'seasonId' => 'test::season::e2e-preseason',
            'seasonName' => 'Synthetic E2E Preseason',
            'startDateUtc' => gmdate('c', $now + (30 * DAY_IN_SECONDS)),
            'endDateUtc' => gmdate('c', $now + (300 * DAY_IN_SECONDS)),
        ]],
    ]);
    fixture_http_register('/competitions', 200, [
        'competitions' => [['competitionId' => 'test::competition::wsl', 'shortName' => 'WSL', 'name' => "Women's Super League"]],
    ]);

    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);
    $result = $provider->get_standings('wsl', ['cache_ttl_seconds' => 1800]);

    MiniTest::assertTrue(! is_wp_error($result), 'get_standings succeeds for a not-yet-started season');
    MiniTest::assertSame('preseason', $result['data_mode'], 'data_mode=preseason for a future season');
    MiniTest::assertSame(14, count($result['rows']), 'preseason still returns a full 0-table (14 clubs), not an empty table');
    MiniTest::assertSame(0, $result['rows'][0]['played'], 'preseason rows show 0 played, not missing data');
});

MiniTest::suite('End to end: unsupported competition key is rejected', function (): void {
    fixture_http_reset();
    mini_test_reset_transients();

    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);
    $result = $provider->get_standings('pl', []);

    MiniTest::assertTrue(is_wp_error($result), 'the WPLL provider refuses a "pl" competition key rather than guessing');
});
