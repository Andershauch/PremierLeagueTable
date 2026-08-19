<?php

/**
 * Covers kickoff formatting for fixtures whose time is not confirmed yet.
 *
 * The kickoff format is user-configurable, so dropping the time from it is
 * string surgery rather than a fixed swap — and the failure mode is a card
 * reading "6. September 2026 - " with a dangling separator where the time used
 * to be.
 *
 * The shortcode is constructed without its dependency graph, since none of the
 * collaborators are involved in formatting a date.
 */

$nextMatchShortcode = (new ReflectionClass('PLT_Next_Match_Shortcode'))->newInstanceWithoutConstructor();

MiniTest::suite('Next-match kickoff format without a time', function () use ($nextMatchShortcode): void {
    $cases = [
        ['d.m.Y H:i', 'd.m.Y', 'the default Danish format keeps just the date'],
        ['j. F Y - H:i', 'j. F Y', 'a dash separator is removed along with the time'],
        ['Y-m-d H:i:s', 'Y-m-d', 'seconds and the space separator are removed'],
        ['d/m/Y g:ia', 'd/m/Y', 'a 12-hour format with am/pm is reduced to the date'],
        ['d.m.Y', 'd.m.Y', 'a date-only format is left unchanged'],
        ['H:i', 'd.m.Y', 'a time-only format falls back to a usable date format'],
    ];

    foreach ($cases as [$input, $expected, $message]) {
        MiniTest::assertSame(
            $expected,
            invoke_private_method($nextMatchShortcode, 'strip_time_from_format', [$input]),
            $message
        );
    }
});

MiniTest::suite('Next-match kickoff rendering', function () use ($nextMatchShortcode): void {
    $settings = ['timezone' => 'Europe/Copenhagen', 'datetime_format' => 'd.m.Y H:i'];

    // The real first Tottenham WSL fixture of the 2026-27 season: 11:00 UTC is
    // 13:00 in Copenhagen, which also proves the timezone conversion survives.
    MiniTest::assertSame(
        '06.09.2026 13:00',
        invoke_private_method($nextMatchShortcode, 'format_kickoff', ['2026-09-06T11:00:00Z', $settings, true]),
        'a confirmed kickoff shows the full local date and time'
    );

    $unconfirmed = invoke_private_method($nextMatchShortcode, 'format_kickoff', ['2026-09-06T11:00:00Z', $settings, false]);
    MiniTest::assertTrue(
        strpos($unconfirmed, '06.09.2026') === 0,
        'an unconfirmed kickoff still shows the confirmed date'
    );
    MiniTest::assertTrue(
        strpos($unconfirmed, '13:00') === false,
        'an unconfirmed kickoff does not show the placeholder time as if it were final'
    );

    MiniTest::assertSame(
        'Kickoff TBD',
        invoke_private_method($nextMatchShortcode, 'format_kickoff', ['not-a-date', $settings, true]),
        'an unparseable date falls back to the TBD label rather than a bogus timestamp'
    );
});
