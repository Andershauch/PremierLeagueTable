<?php

/**
 * Tests PLT_WPLL_Client's private find_next_match_for_team() directly, with
 * synthetic matches at relative offsets from "now" so past/future filtering
 * and soonest-first sorting stay correct regardless of when the suite runs.
 */

function wpll_test_match(string $homeName, string $awayName, int $timestamp): array
{
    return [
        'matchDateUtc' => gmdate('c', $timestamp),
        'home' => ['teamId' => 'test::team::' . $homeName, 'officialName' => $homeName, 'shortName' => $homeName, 'imagery' => []],
        'away' => ['teamId' => 'test::team::' . $awayName, 'officialName' => $awayName, 'shortName' => $awayName, 'imagery' => []],
    ];
}

MiniTest::suite('PLT_WPLL_Client next-match resolution', function (): void {
    $client = new PLT_WPLL_Client();
    $now = time();

    $matches = [
        wpll_test_match('Arsenal', 'Chelsea', $now - 86400), // past, must be ignored
        wpll_test_match('Tottenham Hotspur', 'Everton', $now + (10 * DAY_IN_SECONDS)), // soonest upcoming for Spurs
        wpll_test_match('Manchester City', 'Tottenham Hotspur', $now + (20 * DAY_IN_SECONDS)), // later upcoming for Spurs
        wpll_test_match('Liverpool', 'Manchester United', $now + (5 * DAY_IN_SECONDS)), // upcoming, unrelated team
    ];

    $next = invoke_private_method($client, 'find_next_match_for_team', [$matches, 'Tottenham Hotspur', 'Tottenham Women']);

    MiniTest::assertTrue($next !== null, 'finds an upcoming match for Tottenham Hotspur');
    MiniTest::assertSame('home', $next['focus_side'], 'correctly identifies Tottenham as the home side in the soonest match');
    MiniTest::assertSame('Tottenham Hotspur', $next['home_team']['name'], 'home team name is correct');
    MiniTest::assertSame('Everton', $next['away_team']['name'], 'picks the SOONEST match (vs Everton), not the later one (vs Man City)');

    $none = invoke_private_method($client, 'find_next_match_for_team', [$matches, 'Newcastle United', 'Newcastle United']);
    MiniTest::assertTrue($none === null, 'returns null when the team has no upcoming match in the list');

    $viaFallbackName = invoke_private_method($client, 'find_next_match_for_team', [$matches, '', 'Tottenham Hotspur']);
    MiniTest::assertTrue($viaFallbackName !== null, 'matches via the fallback name when the primary name is empty');

    $allPast = [wpll_test_match('Arsenal', 'Chelsea', $now - 100)];
    $pastOnly = invoke_private_method($client, 'find_next_match_for_team', [$allPast, 'Arsenal', 'Arsenal']);
    MiniTest::assertTrue($pastOnly === null, 'a match that has already kicked off is not treated as upcoming');
});
