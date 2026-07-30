<?php

/**
 * Optional live smoke test: hits the real WSL Football feed over the network.
 * NOT part of tests/run-unit-tests.php or the deterministic suite -- run it
 * explicitly with `.\scripts\run-php-tests.ps1 -IncludeLive` (or directly:
 * `php tests/live/wpll-live-smoke-test.php`) when you want to confirm the
 * real feed is still reachable and still shaped the way the client expects.
 *
 * Because this depends on external network access and the real current WSL
 * season state, it soft-skips (rather than failing) on connectivity errors,
 * and only asserts facts that hold regardless of season state (preseason or
 * live) rather than exact point totals that will change once matches are played.
 */

require __DIR__ . '/../mini-test.php';
require __DIR__ . '/../support/wp-stubs.php';
require __DIR__ . '/../support/live-http.php';

$pluginDir = dirname(__DIR__, 2) . '/includes/';
require $pluginDir . 'interface-standings-provider.php';
require $pluginDir . 'class-wpll-client.php';
require $pluginDir . 'class-wpll-standings-provider.php';

MiniTest::suite('LIVE: WSL Football feed (api-sdp.wslfootball.com)', function (): void {
    $client = new PLT_WPLL_Client();
    $provider = new PLT_WPLL_Standings_Provider($client);

    $result = $provider->get_standings('wsl', ['cache_ttl_seconds' => 60]);

    if (is_wp_error($result)) {
        MiniTest::skip(
            'Could not reach the live WSL Football feed (' . $result->get_error_message() . '). '
            . 'This is expected in network-restricted environments; see docs/hybrid-release-qa.md '
            . 'for the fallback path this exercises instead.'
        );

        return;
    }

    MiniTest::assertTrue(in_array($result['data_mode'], ['preseason', 'live'], true), 'data_mode is a recognized value');
    MiniTest::assertTrue(count($result['rows']) >= 12, 'the live feed returns at least 12 WSL clubs');

    $names = array_column($result['rows'], 'team_name');
    foreach (['Arsenal', 'Chelsea', 'Tottenham Hotspur', 'Manchester City'] as $expectedClub) {
        MiniTest::assertTrue(in_array($expectedClub, $names, true), "{$expectedClub} is present using PL-style naming");
    }

    foreach ($result['rows'] as $row) {
        MiniTest::assertTrue($row['position'] > 0, "{$row['team_name']} has a positive table position");
    }
});

MiniTest::summaryAndExit();
