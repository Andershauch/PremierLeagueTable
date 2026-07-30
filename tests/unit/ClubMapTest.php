<?php

MiniTest::suite('PLT_Club_Map', function (): void {
    $map = new PLT_Club_Map();

    foreach (['Tottenham', 'Tottenham Hotspur', 'Spurs', 'Tottenham Women'] as $alias) {
        MiniTest::assertSame(
            'tottenham-hotspur',
            $map->resolve_canonical_key($alias),
            "'{$alias}' resolves to the tottenham-hotspur canonical key"
        );
    }

    $key = $map->resolve_canonical_key('Tottenham Hotspur');
    MiniTest::assertSame('Tottenham Hotspur', $map->get_display_team_name($key, 'pl'), 'Tottenham PL display name');
    MiniTest::assertSame('Tottenham Women', $map->get_display_team_name($key, 'wsl'), 'Tottenham WSL display name');
    MiniTest::assertTrue($map->has_competition_mapping($key, 'wsl'), 'Tottenham has a WSL mapping');

    foreach (['Arsenal', 'Chelsea', 'Manchester City'] as $club) {
        $clubKey = $map->resolve_canonical_key($club);
        MiniTest::assertTrue(
            $map->has_competition_mapping($clubKey, 'wsl'),
            "{$club} has a WSL mapping"
        );
    }

    // A PL-only club (no WSL counterpart) must degrade safely, not throw or
    // return a slug-like value.
    $newcastleKey = $map->resolve_canonical_key('Newcastle United');
    MiniTest::assertSame('newcastle-united', $newcastleKey, 'Newcastle resolves to a canonical key');
    MiniTest::assertTrue(
        ! $map->has_competition_mapping($newcastleKey, 'wsl'),
        'Newcastle correctly has no WSL mapping'
    );
    MiniTest::assertSame(
        '',
        $map->get_display_team_name($newcastleKey, 'wsl'),
        'Newcastle WSL display name falls back to empty string, not a slug'
    );

    MiniTest::assertSame(
        '',
        $map->resolve_canonical_key(''),
        'Empty input resolves to an empty canonical key'
    );
});
