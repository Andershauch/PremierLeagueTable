<?php

/**
 * Covers the release-parsing logic behind the plugin's GitHub updater.
 *
 * This is the piece that decides whether an installed site is offered an
 * update, so the failure modes worth pinning are the quiet ones: a draft or
 * pre-release being treated as shippable, a release with no installable asset
 * producing a broken download, or a version comparison that offers a downgrade.
 */

function github_updater_release(array $overrides = []): array
{
    return array_merge(
        [
            'tag_name' => 'v2.3.0',
            'draft' => false,
            'prerelease' => false,
            'body' => "- Something changed\n",
            'html_url' => 'https://github.com/Andershauch/PremierLeagueTable/releases/tag/v2.3.0',
            'published_at' => '2026-08-19T10:00:00Z',
            'zipball_url' => 'https://api.github.com/repos/Andershauch/PremierLeagueTable/zipball/v2.3.0',
            'assets' => [
                [
                    'name' => 'premier-league-table.zip',
                    'browser_download_url' => 'https://github.com/Andershauch/PremierLeagueTable/releases/download/v2.3.0/premier-league-table.zip',
                ],
            ],
        ],
        $overrides
    );
}

function github_updater_instance(string $version = '2.3.0'): PLT_GitHub_Updater
{
    return new PLT_GitHub_Updater(
        '/var/www/wp-content/plugins/premier-league-table/premier-league-table.php',
        'Andershauch/PremierLeagueTable',
        $version
    );
}

MiniTest::suite('PLT_GitHub_Updater tag parsing', function (): void {
    $updater = github_updater_instance();

    $cases = [
        ['v2.3.0', '2.3.0', 'a leading v is stripped from the tag'],
        ['2.3.0', '2.3.0', 'a bare version tag is accepted as-is'],
        ['V2.3.1', '2.3.1', 'an uppercase V is stripped too'],
        ['2.3.0-beta1', '2.3.0-beta1', 'a prerelease suffix is preserved for version_compare'],
        ['', '', 'an empty tag yields no version'],
        ['release-candidate', '', 'a non-numeric tag is rejected rather than guessed at'],
    ];

    foreach ($cases as [$tag, $expected, $message]) {
        MiniTest::assertSame($expected, invoke_private_method($updater, 'normalize_version', [$tag]), $message);
    }
});

MiniTest::suite('PLT_GitHub_Updater release selection', function (): void {
    $updater = github_updater_instance();

    $release = invoke_private_method($updater, 'parse_release', [github_updater_release()]);
    MiniTest::assertTrue($release !== null, 'a normal published release is accepted');
    MiniTest::assertSame('2.3.0', $release['version'], 'the version comes from the tag');
    MiniTest::assertSame(
        'https://github.com/Andershauch/PremierLeagueTable/releases/download/v2.3.0/premier-league-table.zip',
        $release['package'],
        'the CI-built zip asset is preferred as the download package'
    );

    $draft = invoke_private_method($updater, 'parse_release', [github_updater_release(['draft' => true])]);
    MiniTest::assertTrue($draft === null, 'a draft release is never offered to sites');

    $prerelease = invoke_private_method($updater, 'parse_release', [github_updater_release(['prerelease' => true])]);
    MiniTest::assertTrue($prerelease === null, 'a pre-release is never offered to sites');

    $badTag = invoke_private_method($updater, 'parse_release', [github_updater_release(['tag_name' => 'nightly'])]);
    MiniTest::assertTrue($badTag === null, 'a release whose tag is not a version is skipped');

    // Without an attached asset the source zipball is the only thing available;
    // it installs into a repo-named folder, which fix_source_directory() repairs.
    $noAsset = invoke_private_method($updater, 'parse_release', [github_updater_release(['assets' => []])]);
    MiniTest::assertTrue($noAsset !== null, 'a release with no asset still falls back to the source zipball');
    MiniTest::assertSame(
        'https://api.github.com/repos/Andershauch/PremierLeagueTable/zipball/v2.3.0',
        $noAsset['package'],
        'the fallback package is the zipball URL'
    );

    $nothingDownloadable = invoke_private_method(
        $updater,
        'parse_release',
        [github_updater_release(['assets' => [], 'zipball_url' => ''])]
    );
    MiniTest::assertTrue($nothingDownloadable === null, 'a release with nothing downloadable is skipped, not offered as a broken update');
});

MiniTest::suite('PLT_GitHub_Updater version comparison', function (): void {
    $updater = github_updater_instance();

    $cases = [
        ['2.3.1', '2.3.0', true, 'a higher patch version is an update'],
        ['2.4.0', '2.3.9', true, 'a higher minor version is an update'],
        ['3.0.0', '2.99.99', true, 'a higher major version is an update'],
        ['2.3.0', '2.3.0', false, 'the same version is not an update'],
        ['2.2.0', '2.3.0', false, 'an older release is never offered as an update'],
        ['2.3.0-beta1', '2.3.0', false, 'a beta of the installed version is not an update'],
    ];

    foreach ($cases as [$remote, $current, $expected, $message]) {
        MiniTest::assertSame(
            $expected,
            invoke_private_method($updater, 'is_newer_version', [$remote, $current]),
            $message
        );
    }
});

MiniTest::suite('PLT_GitHub_Updater update transient', function (): void {
    mini_test_reset_transients();

    // 2.2.0 installed, 2.3.0 published -> WordPress should be handed an offer.
    $updater = github_updater_instance('2.2.0');
    set_transient('plt_github_release_v1', invoke_private_method($updater, 'parse_release', [github_updater_release()]));

    $transient = new stdClass();
    $transient->checked = ['premier-league-table/premier-league-table.php' => '2.2.0'];
    $transient->response = [];
    $transient->no_update = [];

    $result = $updater->inject_update($transient);
    MiniTest::assertTrue(
        isset($result->response['premier-league-table/premier-league-table.php']),
        'an available newer release is offered as an update'
    );
    MiniTest::assertSame(
        '2.3.0',
        $result->response['premier-league-table/premier-league-table.php']->new_version,
        'the offered version is the released one'
    );

    // Same site, already on the released version -> no offer at all.
    $current = github_updater_instance('2.3.0');
    $transient2 = new stdClass();
    $transient2->checked = ['premier-league-table/premier-league-table.php' => '2.3.0'];
    $transient2->response = [];
    $transient2->no_update = [];

    $result2 = $current->inject_update($transient2);
    MiniTest::assertTrue(
        ! isset($result2->response['premier-league-table/premier-league-table.php']),
        'an up-to-date site is not offered an update'
    );
    MiniTest::assertTrue(
        isset($result2->no_update['premier-league-table/premier-league-table.php']),
        'an up-to-date site is still registered, so WordPress shows it as current rather than unknown'
    );

    // A transient WordPress has not populated yet must be returned untouched.
    $empty = new stdClass();
    $untouched = $current->inject_update($empty);
    MiniTest::assertTrue(! isset($untouched->response), 'an unpopulated update transient is left alone');

    mini_test_reset_transients();
});
