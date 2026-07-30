<?php

MiniTest::suite('PLT_WPLL_Client helpers', function (): void {
    $client = new PLT_WPLL_Client();

    MiniTest::assertSame(
        'https://media-sdp.wslfootball.com/clubLogos/abc.webp',
        $client->resolve_image_url('clubLogos/abc.webp'),
        'resolve_image_url prefixes the media CDN base'
    );
    MiniTest::assertSame(
        'https://media-sdp.wslfootball.com/clubLogos/abc.webp',
        $client->resolve_image_url('/clubLogos/abc.webp'),
        'resolve_image_url strips a leading slash before prefixing'
    );
    MiniTest::assertSame(
        'https://example.com/already-absolute.png',
        $client->resolve_image_url('https://example.com/already-absolute.png'),
        'resolve_image_url leaves an already-absolute URL untouched'
    );
    MiniTest::assertSame('', $client->resolve_image_url(''), 'resolve_image_url returns empty string for empty input');

    MiniTest::assertSame(0, $client->hash_team_id(''), 'hash_team_id returns 0 for empty input');
    $hash = $client->hash_team_id('wpll::Football_Team::c834bcf0e12046ecbce4d4c27930d6d7');
    MiniTest::assertTrue(is_int($hash) && $hash !== 0, 'hash_team_id returns a non-zero int for a real team id');
    MiniTest::assertSame(
        $hash,
        $client->hash_team_id('wpll::Football_Team::c834bcf0e12046ecbce4d4c27930d6d7'),
        'hash_team_id is deterministic for the same input'
    );

    MiniTest::assertSame(
        'manchester city',
        $client->normalize_team_name('Manchester City WFC'),
        'normalize_team_name strips the WFC suffix and lowercases'
    );
    MiniTest::assertSame(
        $client->normalize_team_name('Chelsea Women'),
        $client->normalize_team_name('chelsea   women'),
        'normalize_team_name collapses whitespace and case differences the same way'
    );
});
