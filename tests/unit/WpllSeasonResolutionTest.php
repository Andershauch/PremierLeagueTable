<?php

/**
 * Tests PLT_WPLL_Client's private resolve_current_season() date-branching
 * logic (live / preseason / latest-finished) via reflection, using synthetic
 * season lists built relative to the real current time at test-run time.
 *
 * Deliberately NOT using the real captured WSL season dates here: those are
 * fixed calendar dates that will eventually all fall in the past as real time
 * moves forward, which would silently change which branch this test exercises
 * depending on when it happens to run. Relative offsets keep it correct forever.
 */

function wpll_test_register_discovery(array $seasons): void
{
    fixture_http_reset();
    mini_test_reset_transients();

    fixture_http_register('/seasons', 200, ['seasons' => $seasons]);
    fixture_http_register('/competitions', 200, [
        'competitions' => [
            ['competitionId' => 'test::competition::wsl', 'shortName' => 'WSL', 'name' => "Women's Super League"],
            ['competitionId' => 'test::competition::wsl2', 'shortName' => 'WSL 2', 'name' => 'WSL 2'],
        ],
    ]);
}

function wpll_test_iso(int $timestamp): string
{
    return gmdate('c', $timestamp);
}

MiniTest::suite('PLT_WPLL_Client season resolution: now falls inside a season', function (): void {
    $now = time();
    wpll_test_register_discovery([
        [
            'seasonId' => 'test::season::live',
            'seasonName' => 'Synthetic Live Season',
            'startDateUtc' => wpll_test_iso($now - (30 * DAY_IN_SECONDS)),
            'endDateUtc' => wpll_test_iso($now + (30 * DAY_IN_SECONDS)),
        ],
    ]);

    $client = new PLT_WPLL_Client();
    $season = invoke_private_method($client, 'resolve_current_season', []);

    MiniTest::assertTrue(! is_wp_error($season), 'resolve_current_season does not error');
    MiniTest::assertSame('test::season::live', $season['season_id'], 'picks the season containing "now"');
    MiniTest::assertSame('live', $season['phase'], 'a season containing "now" is phase=live');
});

MiniTest::suite('PLT_WPLL_Client season resolution: only an upcoming season exists', function (): void {
    $now = time();
    wpll_test_register_discovery([
        [
            'seasonId' => 'test::season::upcoming',
            'seasonName' => 'Synthetic Upcoming Season',
            'startDateUtc' => wpll_test_iso($now + (20 * DAY_IN_SECONDS)),
            'endDateUtc' => wpll_test_iso($now + (200 * DAY_IN_SECONDS)),
        ],
        [
            'seasonId' => 'test::season::further-upcoming',
            'seasonName' => 'Synthetic Further-Out Season',
            'startDateUtc' => wpll_test_iso($now + (400 * DAY_IN_SECONDS)),
            'endDateUtc' => wpll_test_iso($now + (600 * DAY_IN_SECONDS)),
        ],
    ]);

    $client = new PLT_WPLL_Client();
    $season = invoke_private_method($client, 'resolve_current_season', []);

    MiniTest::assertSame('test::season::upcoming', $season['season_id'], 'picks the NEAREST upcoming season, not the further one');
    MiniTest::assertSame('preseason', $season['phase'], 'a not-yet-started season is phase=preseason');
});

MiniTest::suite('PLT_WPLL_Client season resolution: only finished seasons exist', function (): void {
    $now = time();
    wpll_test_register_discovery([
        [
            'seasonId' => 'test::season::older-finished',
            'seasonName' => 'Synthetic Older Finished Season',
            'startDateUtc' => wpll_test_iso($now - (800 * DAY_IN_SECONDS)),
            'endDateUtc' => wpll_test_iso($now - (500 * DAY_IN_SECONDS)),
        ],
        [
            'seasonId' => 'test::season::most-recent-finished',
            'seasonName' => 'Synthetic Most Recent Finished Season',
            'startDateUtc' => wpll_test_iso($now - (400 * DAY_IN_SECONDS)),
            'endDateUtc' => wpll_test_iso($now - (60 * DAY_IN_SECONDS)),
        ],
    ]);

    $client = new PLT_WPLL_Client();
    $season = invoke_private_method($client, 'resolve_current_season', []);

    MiniTest::assertSame(
        'test::season::most-recent-finished',
        $season['season_id'],
        'with no live/upcoming season, picks the MOST RECENT finished one'
    );
    MiniTest::assertSame('live', $season['phase'], 'a finished season with no successor is still phase=live (shows the final table)');
});

MiniTest::suite('PLT_WPLL_Client season resolution: no seasons at all', function (): void {
    wpll_test_register_discovery([]);

    $client = new PLT_WPLL_Client();
    $season = invoke_private_method($client, 'resolve_current_season', []);

    MiniTest::assertTrue(is_wp_error($season), 'an empty season list produces a WP_Error, not a fatal or a bogus season');
});

MiniTest::suite('PLT_WPLL_Client season resolution: WSL 2 must not be picked instead of WSL', function (): void {
    fixture_http_reset();
    mini_test_reset_transients();
    $now = time();

    // Register the specific "wsl-real" seasons route BEFORE the generic
    // /competitions route: fixture_http_get matches in registration order,
    // and the seasons URL contains both substrings, so the more specific
    // route must win. Only the CORRECT (WSL) competition id has a seasons
    // fixture at all -- if the code mistakenly picked "WSL 2" instead, it
    // would request a URL nothing matches specifically, fall through to the
    // generic /competitions route, and get the competitions payload back
    // where a seasons list was expected, which the assertion below catches.
    fixture_http_register('wsl-real', 200, [
        'seasons' => [[
            'seasonId' => 'test::season::live',
            'seasonName' => 'Synthetic Live Season',
            'startDateUtc' => wpll_test_iso($now - 100),
            'endDateUtc' => wpll_test_iso($now + 100),
        ]],
    ]);
    fixture_http_register('/competitions', 200, [
        'competitions' => [
            ['competitionId' => 'test::competition::wsl2-only-listed-first', 'shortName' => 'WSL 2', 'name' => 'WSL 2'],
            ['competitionId' => 'test::competition::wsl-real', 'shortName' => 'WSL', 'name' => "Women's Super League"],
        ],
    ]);

    $client = new PLT_WPLL_Client();
    $season = invoke_private_method($client, 'resolve_current_season', []);

    MiniTest::assertTrue(
        ! is_wp_error($season),
        'resolves successfully, proving it requested seasons for the WSL competition id, not WSL 2'
    );
});
