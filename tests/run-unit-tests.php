<?php

/**
 * Repeatable, offline, dependency-free unit test runner for the plugin's pure
 * PHP classes. No WordPress install, no network access, no Composer/PHPUnit —
 * just `php tests/run-unit-tests.php` (or `.\scripts\run-php-tests.ps1`),
 * locally or in CI. See tests/live/ for the separate, network-touching smoke
 * test that is NOT part of this deterministic suite.
 */

require __DIR__ . '/mini-test.php';
require __DIR__ . '/support/wp-stubs.php';
require __DIR__ . '/support/fixture-http.php';
require __DIR__ . '/support/reflection.php';

$pluginDir = dirname(__DIR__) . '/includes/';
require $pluginDir . 'interface-standings-provider.php';
require $pluginDir . 'class-club-map.php';
require $pluginDir . 'class-standings-service.php';
require $pluginDir . 'class-api-client.php';
require $pluginDir . 'class-football-data-provider.php';
require $pluginDir . 'class-wpll-client.php';
require $pluginDir . 'class-wpll-standings-provider.php';
require $pluginDir . 'class-github-updater.php';
require $pluginDir . 'class-settings.php';
require $pluginDir . 'class-next-match-settings.php';
require $pluginDir . 'class-thesportsdb-client.php';
require $pluginDir . 'class-next-match-shortcode.php';

define('PLT_TESTS_FIXTURES_DIR', __DIR__ . '/fixtures/');

require __DIR__ . '/unit/ClubMapTest.php';
require __DIR__ . '/unit/WpllClientHelpersTest.php';
require __DIR__ . '/unit/WpllStandingsProviderTest.php';
require __DIR__ . '/unit/WpllSeasonResolutionTest.php';
require __DIR__ . '/unit/WpllNextMatchTest.php';
require __DIR__ . '/unit/WpllEndToEndFixtureTest.php';
require __DIR__ . '/unit/StandingsServiceFallbackTest.php';
require __DIR__ . '/unit/FootballDataPreseasonTest.php';
require __DIR__ . '/unit/GitHubUpdaterTest.php';
require __DIR__ . '/unit/NextMatchKickoffFormatTest.php';
require __DIR__ . '/unit/FocusTeamHighlightTest.php';
require __DIR__ . '/unit/ThemeConfigFontTest.php';

MiniTest::summaryAndExit();
