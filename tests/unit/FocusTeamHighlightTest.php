<?php

/**
 * Covers focus-team matching across the hybrid provider setup.
 *
 * The bug this pins: the saved focus team resolves to a competition-specific
 * name ("Tottenham Women" for WSL), while the WSL feed has served PL-style
 * names ("Tottenham Hotspur") since the WPLL source became primary in 2.2.0.
 * Neither string contains the other, so the highlight silently stopped
 * matching — but only for clubs whose women's name REPLACES the men's suffix
 * (Tottenham, West Ham). Clubs that merely append one (Arsenal -> Arsenal WFC)
 * kept working by substring luck, which is why this went unnoticed.
 */

// Only the club map matters here, so the providers are inert stand-ins.
$focusService = new PLT_Standings_Service(
    new MiniTestWorkingProvider('football-data'),
    [],
    new PLT_Club_Map()
);

MiniTest::suite('Focus team matching across PL and WSL naming', function () use ($focusService): void {
    // The exact pairing that was broken on the live site: WSL focus name from
    // the club map vs the row name the WPLL feed actually serves.
    MiniTest::assertTrue(
        $focusService->matches_focus_team('Tottenham Hotspur', 'Tottenham Women'),
        'a WPLL row matches the WSL focus name for Tottenham'
    );
    MiniTest::assertTrue(
        $focusService->matches_focus_team('West Ham United', 'West Ham Women'),
        'a WPLL row matches the WSL focus name for West Ham'
    );

    // Clubs that were accidentally working before must keep working.
    MiniTest::assertTrue(
        $focusService->matches_focus_team('Arsenal', 'Arsenal WFC'),
        'Arsenal still matches its WSL focus name'
    );
    MiniTest::assertTrue(
        $focusService->matches_focus_team('Manchester City', 'Manchester City WFC'),
        'Manchester City still matches its WSL focus name'
    );
    MiniTest::assertTrue(
        $focusService->matches_focus_team('Everton', 'Everton FC Women'),
        'Everton still matches its WSL focus name'
    );

    // The Premier League side, where names differ only by the FC suffix.
    MiniTest::assertTrue(
        $focusService->matches_focus_team('Tottenham Hotspur FC', 'Tottenham Hotspur'),
        'a football-data row matches the PL focus name despite the FC suffix'
    );

    // Short forms a user might type into the shortcode attribute.
    MiniTest::assertTrue($focusService->matches_focus_team('Tottenham Hotspur FC', 'Spurs'), '"Spurs" resolves to Tottenham');
    MiniTest::assertTrue($focusService->matches_focus_team('Wolverhampton Wanderers FC', 'Wolves'), '"Wolves" resolves to Wolverhampton');
});

MiniTest::suite('Focus team matching rejects the wrong club', function () use ($focusService): void {
    MiniTest::assertTrue(
        ! $focusService->matches_focus_team('Arsenal', 'Tottenham Women'),
        'a different club is never highlighted as the focus team'
    );
    MiniTest::assertTrue(
        ! $focusService->matches_focus_team('Manchester United', 'Manchester City WFC'),
        'the two Manchester clubs are kept apart'
    );

    // Newcastle has no WSL side, so nothing in a WSL table should light up.
    foreach (['Arsenal', 'Chelsea', 'Tottenham Hotspur', 'London City Lionesses'] as $wslClub) {
        MiniTest::assertTrue(
            ! $focusService->matches_focus_team($wslClub, 'Newcastle United'),
            "a WSL table highlights nothing when the focus club has no women's side ({$wslClub})"
        );
    }

    MiniTest::assertTrue(! $focusService->matches_focus_team('', 'Tottenham Women'), 'an empty row name never matches');
    MiniTest::assertTrue(! $focusService->matches_focus_team('Tottenham Hotspur', ''), 'an unset focus team highlights nothing');
});

MiniTest::suite('Focus team matching over a real WSL roster', function () use ($focusService): void {
    // The 14 clubs the WPLL feed serves for 2026-27, with Tottenham as focus:
    // exactly one row must light up.
    $wslRoster = [
        'Arsenal', 'Aston Villa', 'Birmingham City', 'Brighton & Hove Albion',
        'Charlton Athletic', 'Chelsea', 'Crystal Palace', 'Everton', 'Liverpool',
        'London City Lionesses', 'Manchester City', 'Manchester United',
        'Tottenham Hotspur', 'West Ham United',
    ];

    $matched = [];
    foreach ($wslRoster as $club) {
        if ($focusService->matches_focus_team($club, 'Tottenham Women')) {
            $matched[] = $club;
        }
    }

    MiniTest::assertSame(['Tottenham Hotspur'], $matched, 'exactly one WSL row is highlighted, and it is the right one');
});
