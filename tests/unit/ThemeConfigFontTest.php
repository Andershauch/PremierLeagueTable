<?php

/**
 * Covers how the appearance settings turn into what the frontend actually gets.
 *
 * The bug this pins: the "Preset and layout" group (table font, font scale,
 * density) is deliberately NOT custom-only, so those controls are visible and
 * saveable while the Legacy preset is selected — but Legacy then returned an
 * empty style attribute and hardcoded classes, silently discarding all three.
 * A user could pick a font, save it, see it in no preview and no page, with
 * nothing reporting a problem.
 */

$settingsForTheme = new PLT_Settings();

function theme_config_for(PLT_Settings $settings, array $overrides): array
{
    return $settings->get_frontend_theme_config(array_merge(
        [
            'visual_preset' => 'legacy',
            'font_family' => 'theme',
            'font_scale' => 'medium',
            'density' => 'comfortable',
        ],
        $overrides
    ));
}

MiniTest::suite('Legacy preset honours the preset-and-layout controls', function () use ($settingsForTheme): void {
    $config = theme_config_for($settingsForTheme, [
        'visual_preset' => 'legacy',
        'font_family' => 'apex',
        'font_scale' => 'large',
        'density' => 'compact',
    ]);

    MiniTest::assertSame('legacy', $config['preset'], 'the preset is still legacy');
    MiniTest::assertTrue(
        strpos($config['style'], '--plt-font-family') !== false,
        'legacy now emits the table font instead of discarding it'
    );
    MiniTest::assertTrue(
        strpos($config['style'], 'Apex New') !== false,
        'the chosen font reaches the frontend on the legacy preset'
    );
    MiniTest::assertTrue(
        in_array('plt-font-large', $config['classes'], true),
        'the chosen font scale reaches the frontend on the legacy preset'
    );
    MiniTest::assertTrue(
        in_array('plt-density-compact', $config['classes'], true),
        'the chosen density reaches the frontend on the legacy preset'
    );
    MiniTest::assertTrue(
        in_array('plt-skin-legacy', $config['classes'], true),
        'the legacy skin class is kept, so colours and structure stay as released'
    );

    // Legacy must NOT start emitting the custom-only variables.
    foreach (['--plt-favorite-bg', '--plt-header-bg', '--plt-zebra-bg', '--plt-team-font-family'] as $customOnlyVar) {
        MiniTest::assertTrue(
            strpos($config['style'], $customOnlyVar) === false,
            "legacy does not leak the custom-only variable {$customOnlyVar}"
        );
    }
});

MiniTest::suite('Apex New falls back to the bundled font', function () use ($settingsForTheme): void {
    $config = theme_config_for($settingsForTheme, ['font_family' => 'apex']);

    // Apex New is Tottenham's proprietary face and cannot be bundled, so the
    // stack must name it first and then fall through to something that is
    // actually shipped, rather than to a bare generic sans-serif.
    MiniTest::assertTrue(strpos($config['style'], '"Apex New"') !== false, 'Apex New is still preferred when installed');
    MiniTest::assertTrue(strpos($config['style'], '"Archivo"') !== false, 'the bundled Archivo backs Apex New up');

    $archivo = theme_config_for($settingsForTheme, ['font_family' => 'archivo']);
    MiniTest::assertTrue(strpos($archivo['style'], '"Archivo"') !== false, 'Archivo can also be chosen on its own');

    $theme = theme_config_for($settingsForTheme, ['font_family' => 'theme']);
    MiniTest::assertTrue(strpos($theme['style'], 'inherit') !== false, 'the theme default still inherits the site font');
});

MiniTest::suite('Custom preset still applies the full token set', function () use ($settingsForTheme): void {
    $config = theme_config_for($settingsForTheme, [
        'visual_preset' => 'custom',
        'font_family' => 'georgia',
        'font_scale' => 'small',
    ]);

    MiniTest::assertSame('custom', $config['preset'], 'the custom preset is resolved');
    MiniTest::assertTrue(in_array('plt-skin-custom', $config['classes'], true), 'the custom skin class is applied');
    MiniTest::assertTrue(strpos($config['style'], 'Georgia') !== false, 'the chosen font is applied on custom too');
    MiniTest::assertTrue(
        strpos($config['style'], '--plt-favorite-bg') !== false,
        'custom still emits the colour tokens legacy deliberately omits'
    );
    MiniTest::assertTrue(in_array('plt-font-small', $config['classes'], true), 'font scale is applied on custom');
});

MiniTest::suite('The bundled font ships with the plugin', function (): void {
    // A CSS @font-face pointing at a file the release zip does not carry would
    // fail silently in exactly the way this whole change set out to fix.
    $fontFile = dirname(__DIR__, 2) . '/assets/fonts/archivo-latin-variable.woff2';
    $licence = dirname(__DIR__, 2) . '/assets/fonts/Archivo-OFL.txt';

    MiniTest::assertTrue(file_exists($fontFile), 'the bundled woff2 exists in assets/fonts');
    MiniTest::assertTrue(filesize($fontFile) > 10000, 'the bundled woff2 is a real font file, not an error page');
    MiniTest::assertSame('wOF2', substr((string) file_get_contents($fontFile, false, null, 0, 4), 0, 4), 'the bundled file has a woff2 signature');
    MiniTest::assertTrue(file_exists($licence), 'the SIL Open Font License text ships alongside it');

    foreach (['assets/css/frontend.css', 'assets/css/next-match.css'] as $stylesheet) {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/' . $stylesheet);
        MiniTest::assertTrue(
            strpos($css, '@font-face') !== false && strpos($css, 'archivo-latin-variable.woff2') !== false,
            "{$stylesheet} declares the bundled font, so it works when enqueued on its own"
        );
    }
});
