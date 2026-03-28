<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Settings
{
    private const OPTION_NAME = 'plt_settings';
    private const PAGE_SLUG = 'plt-settings';
    private const PRESET_LEGACY = 'legacy';
    private const PRESET_CUSTOM = 'custom';

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_settings_page(): void
    {
        add_options_page(
            __('Premier League Table', 'premier-league-table'),
            __('Premier League Table', 'premier-league-table'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'plt_settings_group',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );

        add_settings_section(
            'plt_api_section',
            __('API settings', 'premier-league-table'),
            [$this, 'render_api_section_intro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'api_key',
            __('API key', 'premier-league-table'),
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            'plt_api_section'
        );

        add_settings_field(
            'favorite_team',
            __('Focus team', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_api_section',
            [
                'key' => 'favorite_team',
                'options' => $this->get_favorite_team_options(),
                'description' => __('Choose the row that should be highlighted on the frontend when the shortcode does not override it.', 'premier-league-table'),
            ]
        );

        add_settings_field(
            'cache_ttl_minutes',
            __('Cache lifetime (minutes)', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_api_section',
            [
                'key' => 'cache_ttl_minutes',
                'options' => [
                    '1' => __('1 minute', 'premier-league-table'),
                    '5' => __('5 minutes', 'premier-league-table'),
                    '10' => __('10 minutes', 'premier-league-table'),
                    '15' => __('15 minutes', 'premier-league-table'),
                    '30' => __('30 minutes', 'premier-league-table'),
                    '60' => __('60 minutes', 'premier-league-table'),
                ],
                'description' => __('Shorter cache times improve freshness but increase API usage.', 'premier-league-table'),
            ]
        );

        add_settings_section(
            'plt_appearance_section',
            __('Appearance', 'premier-league-table'),
            [$this, 'render_appearance_section_intro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'visual_preset',
            __('Visual preset', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'visual_preset',
                'options' => $this->get_visual_preset_options(),
                'description' => __('Legacy keeps the released Spurs-style look. Custom unlocks the safe appearance controls below.', 'premier-league-table'),
            ]
        );

        add_settings_field(
            'font_family',
            __('Table font family', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'font_family',
                'options' => $this->get_font_family_options(),
                'description' => __('Fonts are limited to trusted choices so the frontend stays stable across sites.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'team_font_family',
            __('Other teams font', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'team_font_family',
                'options' => $this->get_team_font_family_options(),
                'description' => __('Controls the font used for non-highlighted team names.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'team_font_weight',
            __('Other teams font weight', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'team_font_weight',
                'options' => $this->get_font_weight_options(),
                'description' => __('Applies only to non-highlighted team names.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'focus_team_font_family',
            __('Focus team font', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'focus_team_font_family',
                'options' => $this->get_team_font_family_options(),
                'description' => __('Controls the font used for the highlighted focus team name.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'focus_team_font_weight',
            __('Focus team font weight', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'focus_team_font_weight',
                'options' => $this->get_font_weight_options(),
                'description' => __('Applies only to the highlighted focus team name.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'font_scale',
            __('Font size', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'font_scale',
                'options' => [
                    'small' => __('Small', 'premier-league-table'),
                    'medium' => __('Medium', 'premier-league-table'),
                    'large' => __('Large', 'premier-league-table'),
                ],
                'description' => __('Controls table text size without changing the legacy layout width.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'density',
            __('Row density', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'density',
                'options' => [
                    'compact' => __('Compact', 'premier-league-table'),
                    'comfortable' => __('Comfortable', 'premier-league-table'),
                ],
                'description' => __('Compact reduces cell padding. Comfortable keeps a little more breathing room.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'text_color',
            __('Body text color', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'text_color',
                'description' => __('Applies to club names, numbers, and footer text in custom mode.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'grid_color',
            __('Grid color', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'grid_color',
                'description' => __('Controls borders around the custom table cells.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'header_bg_color',
            __('Header background', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'header_bg_color',
                'description' => __('Low-contrast combinations automatically fall back to safe defaults.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'header_font_family',
            __('Header font', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'header_font_family',
                'options' => $this->get_team_font_family_options(),
                'description' => __('Controls the font used for the table header labels.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'header_font_weight',
            __('Header font weight', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'header_font_weight',
                'options' => $this->get_font_weight_options(),
                'description' => __('Controls the font weight used for the table header labels.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'header_text_color',
            __('Header text color', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'header_text_color',
                'description' => __('Header background and text are validated together for readable contrast.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'favorite_row_bg',
            __('Focus row background', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'favorite_row_bg',
                'description' => __('Highlights the selected focus team row in custom mode.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'favorite_row_text',
            __('Focus row text color', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'favorite_row_text',
                'description' => __('Validated together with the focus row background color.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'zebra_rows',
            __('Alternate row shading', 'premier-league-table'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'zebra_rows',
                'label' => __('Add a light zebra pattern to non-highlighted rows in custom mode', 'premier-league-table'),
                'description' => __('Useful when you want a more styled table without changing the core layout.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'zebra_row_bg',
            __('Alternate row background', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'zebra_row_bg',
                'description' => __('Controls the background color for alternate rows when zebra mode is enabled.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );

        add_settings_field(
            'zebra_row_text',
            __('Alternate row text color', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_appearance_section',
            [
                'key' => 'zebra_row_text',
                'description' => __('Controls the text color for alternate rows when zebra mode is enabled.', 'premier-league-table'),
                'class' => 'plt-appearance-row plt-appearance-row--custom',
            ]
        );
    }

    public function sanitize_settings($input): array
    {
        $defaults = $this->get_default_settings();
        $output = $defaults;

        if (! is_array($input)) {
            return $output;
        }

        $new_api_key = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
        $clear_api_key = ! empty($input['clear_api_key']);
        $existing_api_key = $this->get_existing_api_key();

        if ($clear_api_key) {
            $output['api_key'] = '';
        } elseif ($new_api_key !== '') {
            $output['api_key'] = $new_api_key;
        } else {
            $output['api_key'] = $existing_api_key;
        }

        $favorite_team = isset($input['favorite_team']) ? sanitize_text_field($input['favorite_team']) : '';
        $output['favorite_team'] = $this->sanitize_favorite_team($favorite_team, (string) $defaults['favorite_team']);

        $visual_preset = isset($input['visual_preset']) ? sanitize_key($input['visual_preset']) : (string) $defaults['visual_preset'];
        $output['visual_preset'] = $this->sanitize_visual_preset($visual_preset, (string) $defaults['visual_preset']);

        $output['font_family'] = $this->sanitize_font_family_key((string) ($input['font_family'] ?? $defaults['font_family']), (string) $defaults['font_family']);
        $output['team_font_family'] = $this->sanitize_font_family_key((string) ($input['team_font_family'] ?? $defaults['team_font_family']), (string) $defaults['team_font_family']);
        $output['focus_team_font_family'] = $this->sanitize_font_family_key((string) ($input['focus_team_font_family'] ?? $defaults['focus_team_font_family']), (string) $defaults['focus_team_font_family']);
        $output['header_font_family'] = $this->sanitize_font_family_key((string) ($input['header_font_family'] ?? $defaults['header_font_family']), (string) $defaults['header_font_family']);
        $output['team_font_weight'] = $this->sanitize_font_weight((string) ($input['team_font_weight'] ?? $defaults['team_font_weight']), (string) $defaults['team_font_weight']);
        $output['focus_team_font_weight'] = $this->sanitize_font_weight((string) ($input['focus_team_font_weight'] ?? $defaults['focus_team_font_weight']), (string) $defaults['focus_team_font_weight']);
        $output['header_font_weight'] = $this->sanitize_font_weight((string) ($input['header_font_weight'] ?? $defaults['header_font_weight']), (string) $defaults['header_font_weight']);

        $font_scale = isset($input['font_scale']) ? sanitize_key($input['font_scale']) : (string) $defaults['font_scale'];
        $output['font_scale'] = in_array($font_scale, ['small', 'medium', 'large'], true) ? $font_scale : (string) $defaults['font_scale'];

        $density = isset($input['density']) ? sanitize_key($input['density']) : (string) $defaults['density'];
        $output['density'] = in_array($density, ['compact', 'comfortable'], true) ? $density : (string) $defaults['density'];

        $output['text_color'] = $this->sanitize_color((string) ($input['text_color'] ?? $defaults['text_color']), (string) $defaults['text_color']);
        $output['grid_color'] = $this->sanitize_color((string) ($input['grid_color'] ?? $defaults['grid_color']), (string) $defaults['grid_color']);

        [$output['header_bg_color'], $output['header_text_color']] = $this->sanitize_color_pair(
            (string) ($input['header_bg_color'] ?? $defaults['header_bg_color']),
            (string) ($input['header_text_color'] ?? $defaults['header_text_color']),
            (string) $defaults['header_bg_color'],
            (string) $defaults['header_text_color']
        );

        [$output['zebra_row_bg'], $output['zebra_row_text']] = $this->sanitize_color_pair(
            (string) ($input['zebra_row_bg'] ?? $defaults['zebra_row_bg']),
            (string) ($input['zebra_row_text'] ?? $defaults['zebra_row_text']),
            (string) $defaults['zebra_row_bg'],
            (string) $defaults['zebra_row_text']
        );

        [$output['favorite_row_bg'], $output['favorite_row_text']] = $this->sanitize_color_pair(
            (string) ($input['favorite_row_bg'] ?? $defaults['favorite_row_bg']),
            (string) ($input['favorite_row_text'] ?? $defaults['favorite_row_text']),
            (string) $defaults['favorite_row_bg'],
            (string) $defaults['favorite_row_text']
        );

        $output['zebra_rows'] = ! empty($input['zebra_rows']) ? '1' : '0';

        $allowed_cache_ttls = [1, 5, 10, 15, 30, 60];
        $cache_ttl_minutes = isset($input['cache_ttl_minutes']) ? absint($input['cache_ttl_minutes']) : (int) $defaults['cache_ttl_minutes'];
        $output['cache_ttl_minutes'] = in_array($cache_ttl_minutes, $allowed_cache_ttls, true) ? $cache_ttl_minutes : (int) $defaults['cache_ttl_minutes'];

        delete_transient('plt_pl_standings_v1');

        return $output;
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <div class="plt-settings-wrap">
                <h1><?php echo esc_html__('Premier League Table Settings', 'premier-league-table'); ?></h1>
                <p><?php echo esc_html__('API key, focus-team behavior, and appearance presets are managed here. Legacy is the safe default. Custom unlocks validated color and font controls with a live preview.', 'premier-league-table'); ?></p>
                <div class="plt-settings-layout">
                    <form method="post" action="options.php" class="plt-settings-main">
                        <?php
                        settings_fields('plt_settings_group');
                        do_settings_sections(self::PAGE_SLUG);
                        submit_button(__('Save settings', 'premier-league-table'));
                        ?>
                    </form>
                    <aside class="plt-settings-sidebar">
                        <?php $this->render_preview_panel(); ?>
                    </aside>
                </div>
            </div>
        </div>
        <?php
    }

    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_NAME, []);

        if (! is_array($settings)) {
            $settings = [];
        }

        $defaults = $this->get_default_settings();
        $settings = wp_parse_args($settings, $defaults);
        $settings['favorite_team'] = $this->sanitize_favorite_team(
            isset($settings['favorite_team']) ? (string) $settings['favorite_team'] : '',
            (string) $defaults['favorite_team']
        );
        $settings['visual_preset'] = $this->sanitize_visual_preset((string) ($settings['visual_preset'] ?? $defaults['visual_preset']), (string) $defaults['visual_preset']);
        $settings['font_family'] = $this->sanitize_font_family_key((string) ($settings['font_family'] ?? $defaults['font_family']), (string) $defaults['font_family']);
        $settings['team_font_family'] = $this->sanitize_font_family_key((string) ($settings['team_font_family'] ?? $defaults['team_font_family']), (string) $defaults['team_font_family']);
        $settings['focus_team_font_family'] = $this->sanitize_font_family_key((string) ($settings['focus_team_font_family'] ?? $defaults['focus_team_font_family']), (string) $defaults['focus_team_font_family']);
        $settings['header_font_family'] = $this->sanitize_font_family_key((string) ($settings['header_font_family'] ?? $defaults['header_font_family']), (string) $defaults['header_font_family']);
        $settings['team_font_weight'] = $this->sanitize_font_weight((string) ($settings['team_font_weight'] ?? $defaults['team_font_weight']), (string) $defaults['team_font_weight']);
        $settings['focus_team_font_weight'] = $this->sanitize_font_weight((string) ($settings['focus_team_font_weight'] ?? $defaults['focus_team_font_weight']), (string) $defaults['focus_team_font_weight']);
        $settings['header_font_weight'] = $this->sanitize_font_weight((string) ($settings['header_font_weight'] ?? $defaults['header_font_weight']), (string) $defaults['header_font_weight']);
        $settings['font_scale'] = in_array((string) ($settings['font_scale'] ?? ''), ['small', 'medium', 'large'], true) ? (string) $settings['font_scale'] : (string) $defaults['font_scale'];
        $settings['density'] = in_array((string) ($settings['density'] ?? ''), ['compact', 'comfortable'], true) ? (string) $settings['density'] : (string) $defaults['density'];
        $settings['text_color'] = $this->sanitize_color((string) ($settings['text_color'] ?? $defaults['text_color']), (string) $defaults['text_color']);
        $settings['grid_color'] = $this->sanitize_color((string) ($settings['grid_color'] ?? $defaults['grid_color']), (string) $defaults['grid_color']);
        [$settings['header_bg_color'], $settings['header_text_color']] = $this->sanitize_color_pair(
            (string) ($settings['header_bg_color'] ?? $defaults['header_bg_color']),
            (string) ($settings['header_text_color'] ?? $defaults['header_text_color']),
            (string) $defaults['header_bg_color'],
            (string) $defaults['header_text_color']
        );
        [$settings['zebra_row_bg'], $settings['zebra_row_text']] = $this->sanitize_color_pair(
            (string) ($settings['zebra_row_bg'] ?? $defaults['zebra_row_bg']),
            (string) ($settings['zebra_row_text'] ?? $defaults['zebra_row_text']),
            (string) $defaults['zebra_row_bg'],
            (string) $defaults['zebra_row_text']
        );
        [$settings['favorite_row_bg'], $settings['favorite_row_text']] = $this->sanitize_color_pair(
            (string) ($settings['favorite_row_bg'] ?? $defaults['favorite_row_bg']),
            (string) ($settings['favorite_row_text'] ?? $defaults['favorite_row_text']),
            (string) $defaults['favorite_row_bg'],
            (string) $defaults['favorite_row_text']
        );
        $settings['zebra_rows'] = ! empty($settings['zebra_rows']) ? '1' : '0';
        $settings['cache_ttl_minutes'] = in_array((int) ($settings['cache_ttl_minutes'] ?? 0), [1, 5, 10, 15, 30, 60], true)
            ? (int) $settings['cache_ttl_minutes']
            : (int) $defaults['cache_ttl_minutes'];

        return $settings;
    }

    public function get_frontend_theme_config(?array $settings = null): array
    {
        $settings = is_array($settings) ? wp_parse_args($settings, $this->get_settings()) : $this->get_settings();
        $preset = $this->sanitize_visual_preset((string) ($settings['visual_preset'] ?? self::PRESET_LEGACY), self::PRESET_LEGACY);

        if ($preset === self::PRESET_CUSTOM) {
            $classes = [
                'plt-table',
                'plt-skin-custom',
                'plt-font-' . sanitize_html_class((string) $settings['font_scale']),
                'plt-density-' . sanitize_html_class((string) $settings['density']),
            ];

            if (! empty($settings['zebra_rows'])) {
                $classes[] = 'plt-zebra-on';
            }

            return [
                'preset' => self::PRESET_CUSTOM,
                'classes' => $classes,
                'style' => $this->build_style_attribute($this->get_custom_theme_variables($settings)),
            ];
        }

        return [
            'preset' => self::PRESET_LEGACY,
            'classes' => [
                'plt-table',
                'plt-skin-legacy',
                'plt-font-medium',
                'plt-density-comfortable',
            ],
            'style' => '',
        ];
    }

    private function get_default_settings(): array
    {
        return [
            'api_key' => '',
            'favorite_team' => '',
            'visual_preset' => self::PRESET_LEGACY,
            'font_family' => 'theme',
            'team_font_family' => 'theme',
            'focus_team_font_family' => 'theme',
            'header_font_family' => 'theme',
            'team_font_weight' => '400',
            'focus_team_font_weight' => '700',
            'header_font_weight' => '600',
            'font_scale' => 'medium',
            'density' => 'comfortable',
            'text_color' => '#333333',
            'grid_color' => '#bec6d3',
            'zebra_row_bg' => '#f7f8fb',
            'zebra_row_text' => '#333333',
            'header_bg_color' => '#ffffff',
            'header_text_color' => '#333333',
            'favorite_row_bg' => '#172c69',
            'favorite_row_text' => '#ffffff',
            'zebra_rows' => '0',
            'cache_ttl_minutes' => 10,
        ];
    }

    private function get_visual_preset_options(): array
    {
        return [
            self::PRESET_LEGACY => __('Legacy', 'premier-league-table'),
            self::PRESET_CUSTOM => __('Custom', 'premier-league-table'),
        ];
    }

    private function get_font_family_options(): array
    {
        return [
            'theme' => __('Theme default', 'premier-league-table'),
            'system' => __('System sans-serif', 'premier-league-table'),
            'apex' => __('Apex New', 'premier-league-table'),
            'arial' => __('Arial', 'premier-league-table'),
            'georgia' => __('Georgia', 'premier-league-table'),
        ];
    }

    private function get_team_font_family_options(): array
    {
        return [
            'theme' => __('Use table font', 'premier-league-table'),
            'system' => __('System sans-serif', 'premier-league-table'),
            'apex' => __('Apex New', 'premier-league-table'),
            'arial' => __('Arial', 'premier-league-table'),
            'georgia' => __('Georgia', 'premier-league-table'),
        ];
    }

    private function get_font_weight_options(): array
    {
        return [
            '300' => __('300 Light', 'premier-league-table'),
            '400' => __('400 Regular', 'premier-league-table'),
            '500' => __('500 Medium', 'premier-league-table'),
            '600' => __('600 Semibold', 'premier-league-table'),
            '700' => __('700 Bold', 'premier-league-table'),
        ];
    }

    private function get_font_family_css_map(): array
    {
        return [
            'theme' => 'inherit',
            'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'apex' => '"Apex New", sans-serif',
            'arial' => 'Arial, Helvetica, sans-serif',
            'georgia' => 'Georgia, "Times New Roman", serif',
        ];
    }

    private function get_fallback_favorite_team_options(): array
    {
        return [
            '' => __('Select team', 'premier-league-table'),
            'Arsenal' => 'Arsenal',
            'Aston Villa' => 'Aston Villa',
            'Bournemouth' => 'Bournemouth',
            'Brentford' => 'Brentford',
            'Brighton & Hove Albion' => 'Brighton & Hove Albion',
            'Burnley' => 'Burnley',
            'Chelsea' => 'Chelsea',
            'Crystal Palace' => 'Crystal Palace',
            'Everton' => 'Everton',
            'Fulham' => 'Fulham',
            'Leeds United' => 'Leeds United',
            'Liverpool' => 'Liverpool',
            'Manchester City' => 'Manchester City',
            'Manchester United' => 'Manchester United',
            'Newcastle United' => 'Newcastle United',
            'Nottingham Forest' => 'Nottingham Forest',
            'Sunderland' => 'Sunderland',
            'Tottenham Hotspur' => 'Tottenham Hotspur',
            'West Ham United' => 'West Ham United',
            'Wolverhampton Wanderers' => 'Wolverhampton Wanderers',
        ];
    }

    private function get_favorite_team_options(): array
    {
        $fallback = $this->get_fallback_favorite_team_options();

        $cached = get_transient('plt_pl_standings_v1');
        if (! is_array($cached) || ! isset($cached['rows']) || ! is_array($cached['rows'])) {
            return $fallback;
        }

        $dynamic = ['' => __('Select team', 'premier-league-table')];
        foreach ($cached['rows'] as $row) {
            if (! is_array($row) || ! isset($row['team_name'])) {
                continue;
            }

            $name = trim((string) $row['team_name']);
            if ($name !== '') {
                $dynamic[$name] = $name;
            }
        }

        return count($dynamic) > 1 ? $dynamic : $fallback;
    }

    private function sanitize_favorite_team(string $favorite_team, string $fallback): string
    {
        $favorite_team = trim(preg_replace('/\s+/u', ' ', $favorite_team));
        $favorite_team = function_exists('mb_substr')
            ? mb_substr($favorite_team, 0, 80)
            : substr($favorite_team, 0, 80);

        if ($favorite_team === '') {
            return '';
        }

        $canonical_teams = $this->get_canonical_favorite_teams();
        $normalized_favorite_team = $this->normalize_team_name($favorite_team);
        if ($normalized_favorite_team === '' || ! isset($canonical_teams[$normalized_favorite_team])) {
            return $fallback;
        }

        return $canonical_teams[$normalized_favorite_team];
    }

    private function get_canonical_favorite_teams(): array
    {
        $canonical_teams = [];

        foreach ($this->get_favorite_team_options() as $team_name => $label) {
            unset($label);

            $team_name = (string) $team_name;
            if ($team_name === '') {
                continue;
            }

            $normalized = $this->normalize_team_name($team_name);
            if ($normalized !== '' && ! isset($canonical_teams[$normalized])) {
                $canonical_teams[$normalized] = $team_name;
            }
        }

        foreach ($this->get_fallback_favorite_team_options() as $team_name => $label) {
            unset($label);

            $team_name = (string) $team_name;
            if ($team_name === '') {
                continue;
            }

            $normalized = $this->normalize_team_name($team_name);
            if ($normalized !== '') {
                $canonical_teams[$normalized] = $team_name;
            }
        }

        return $canonical_teams;
    }

    private function normalize_team_name(string $name): string
    {
        $name = remove_accents(strtolower(trim($name)));
        $name = preg_replace('/\b(fc|afc|cf)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }

    private function sanitize_visual_preset(string $value, string $fallback): string
    {
        return in_array($value, [self::PRESET_LEGACY, self::PRESET_CUSTOM], true) ? $value : $fallback;
    }

    private function sanitize_color(string $color, string $fallback): string
    {
        $sanitized = sanitize_hex_color($color);
        if ($sanitized === null) {
            return $fallback;
        }

        return $sanitized;
    }

    private function sanitize_color_pair(string $background, string $text, string $fallback_background, string $fallback_text): array
    {
        $background = $this->sanitize_color($background, $fallback_background);
        $text = $this->sanitize_color($text, $fallback_text);

        if ($this->get_contrast_ratio($background, $text) < 4.5) {
            return [$fallback_background, $fallback_text];
        }

        return [$background, $text];
    }

    private function sanitize_font_family_key(string $value, string $fallback): string
    {
        $value = sanitize_text_field(trim($value));
        $options = $this->get_font_family_options();
        if (isset($options[$value])) {
            return $value;
        }

        $normalized = strtolower($value);
        if ($normalized === 'inherit' || $normalized === 'theme default') {
            return 'theme';
        }

        if (strpos($normalized, 'apex new') !== false) {
            return 'apex';
        }

        if (strpos($normalized, 'arial') !== false) {
            return 'arial';
        }

        if (strpos($normalized, 'georgia') !== false) {
            return 'georgia';
        }

        if (strpos($normalized, 'segoe ui') !== false || strpos($normalized, 'blinkmacsystemfont') !== false) {
            return 'system';
        }

        return $fallback;
    }

    private function sanitize_font_weight(string $value, string $fallback): string
    {
        $value = sanitize_text_field(trim($value));
        return isset($this->get_font_weight_options()[$value]) ? $value : $fallback;
    }

    private function get_custom_theme_variables(array $settings): array
    {
        $font_map = $this->get_font_family_css_map();
        $font_family = $font_map[(string) $settings['font_family']] ?? $font_map['theme'];
        $team_font_family = $font_map[(string) $settings['team_font_family']] ?? $font_map['theme'];
        $focus_team_font_family = $font_map[(string) $settings['focus_team_font_family']] ?? $font_map['theme'];
        $header_font_family = $font_map[(string) $settings['header_font_family']] ?? $font_map['theme'];

        return [
            '--plt-font-family' => $font_family,
            '--plt-team-font-family' => $team_font_family,
            '--plt-focus-team-font-family' => $focus_team_font_family,
            '--plt-header-font-family' => $header_font_family,
            '--plt-team-font-weight' => (string) $settings['team_font_weight'],
            '--plt-focus-team-font-weight' => (string) $settings['focus_team_font_weight'],
            '--plt-header-font-weight' => (string) $settings['header_font_weight'],
            '--plt-grid' => (string) $settings['grid_color'],
            '--plt-zebra-bg' => (string) $settings['zebra_row_bg'],
            '--plt-zebra-text' => (string) $settings['zebra_row_text'],
            '--plt-header-bg' => (string) $settings['header_bg_color'],
            '--plt-header-text' => (string) $settings['header_text_color'],
            '--plt-body-text' => (string) $settings['text_color'],
            '--plt-meta-text' => (string) $settings['text_color'],
            '--plt-favorite-bg' => (string) $settings['favorite_row_bg'],
            '--plt-favorite-text' => (string) $settings['favorite_row_text'],
        ];
    }

    private function build_style_attribute(array $variables): string
    {
        $chunks = [];
        foreach ($variables as $name => $value) {
            $name = trim((string) $name);
            $value = trim((string) $value);
            if ($name === '' || $value === '') {
                continue;
            }

            $chunks[] = $name . ': ' . $value;
        }

        return implode('; ', $chunks);
    }

    private function get_contrast_ratio(string $first_color, string $second_color): float
    {
        $first_luminance = $this->get_relative_luminance($first_color);
        $second_luminance = $this->get_relative_luminance($second_color);
        $lighter = max($first_luminance, $second_luminance);
        $darker = min($first_luminance, $second_luminance);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function get_relative_luminance(string $hex_color): float
    {
        $rgb = $this->hex_to_rgb($hex_color);
        $channels = [];

        foreach ($rgb as $channel) {
            $channel = $channel / 255;
            $channels[] = $channel <= 0.03928
                ? $channel / 12.92
                : pow(($channel + 0.055) / 1.055, 2.4);
        }

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function hex_to_rgb(string $hex_color): array
    {
        $hex_color = ltrim($hex_color, '#');
        if (strlen($hex_color) === 3) {
            $hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
        }

        return [
            hexdec(substr($hex_color, 0, 2)),
            hexdec(substr($hex_color, 2, 2)),
            hexdec(substr($hex_color, 4, 2)),
        ];
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style('wp-color-picker');

        $frontend_style_version = file_exists(PLT_PLUGIN_DIR . 'assets/css/frontend.css')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/css/frontend.css')
            : '1.1.0';
        $admin_style_version = file_exists(PLT_PLUGIN_DIR . 'assets/css/admin.css')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/css/admin.css')
            : '1.1.0';
        $admin_script_version = file_exists(PLT_PLUGIN_DIR . 'assets/js/admin.js')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/js/admin.js')
            : '1.1.0';

        wp_enqueue_style(
            'plt-frontend-preview',
            PLT_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            $frontend_style_version
        );

        wp_enqueue_style(
            'plt-admin',
            PLT_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-color-picker', 'plt-frontend-preview'],
            $admin_style_version
        );

        wp_enqueue_script(
            'plt-admin',
            PLT_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'wp-color-picker'],
            $admin_script_version,
            true
        );

        wp_localize_script(
            'plt-admin',
            'pltAdminPreview',
            [
                'fontFamilies' => $this->get_font_family_css_map(),
                'notes' => [
                    'legacy' => __('Legacy keeps the released Spurs-style table intact. Switch to Custom to apply the appearance controls.', 'premier-league-table'),
                    'custom' => __('Custom applies only validated design tokens. Layout width and safe table structure stay locked.', 'premier-league-table'),
                ],
                'focusFallback' => 'Tottenham Hotspur',
            ]
        );
    }

    public function render_text_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $type = isset($args['type']) ? sanitize_key($args['type']) : 'text';
        if (! in_array($type, ['text', 'password'], true)) {
            $type = 'text';
        }
        $placeholder = isset($args['placeholder']) ? (string) $args['placeholder'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';
        if ($key === 'api_key') {
            $value = '';
        }

        printf(
            '<input type="%1$s" class="regular-text" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s" %6$s />',
            esc_attr($type),
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value),
            esc_attr($placeholder),
            $key === 'api_key' ? 'autocomplete="off" spellcheck="false"' : ''
        );

        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_api_key_field(array $args): void
    {
        unset($args);
        $existing_api_key = $this->get_existing_api_key();
        $has_key = $existing_api_key !== '';

        printf(
            '<input type="password" class="regular-text" name="%1$s[api_key]" value="" placeholder="%2$s" autocomplete="off" spellcheck="false" />',
            esc_attr(self::OPTION_NAME),
            esc_attr('football-data.org API key')
        );

        printf(
            '<p class="description">%s</p>',
            esc_html__('Leave this field blank to keep the existing API key.', 'premier-league-table')
        );

        printf(
            '<p class="description">%s</p>',
            $has_key
                ? esc_html__('Status: API key saved.', 'premier-league-table')
                : esc_html__('Status: No API key saved yet.', 'premier-league-table')
        );

        printf(
            '<label><input type="checkbox" name="%1$s[clear_api_key]" value="1" /> %2$s</label>',
            esc_attr(self::OPTION_NAME),
            esc_html__('Clear the existing API key when saving', 'premier-league-table')
        );

        echo '<p class="description">';
        printf(
            wp_kses(
                __('Create your own API key at <a href="%1$s" target="_blank" rel="noopener noreferrer">football-data.org</a>. Quickstart docs are available <a href="%2$s" target="_blank" rel="noopener noreferrer">here</a>.', 'premier-league-table'),
                [
                    'a' => [
                        'href' => true,
                        'target' => true,
                        'rel' => true,
                    ],
                ]
            ),
            esc_url('https://www.football-data.org/client/register'),
            esc_url('https://www.football-data.org/documentation/quickstart')
        );
        echo '</p>';
    }

    public function render_color_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $defaults = $this->get_default_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '#111827';
        $default_color = isset($defaults[$key]) ? (string) $defaults[$key] : '#111827';

        printf(
            '<input type="text" class="plt-color-field" name="%1$s[%2$s]" value="%3$s" data-default-color="%4$s" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value),
            esc_attr($default_color)
        );

        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_select_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $options = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $current = isset($settings[$key]) ? (string) $settings[$key] : '';

        printf('<select name="%1$s[%2$s]">', esc_attr(self::OPTION_NAME), esc_attr($key));

        foreach ($options as $value => $label) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr((string) $value),
                selected($current, (string) $value, false),
                esc_html((string) $label)
            );
        }

        echo '</select>';

        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_checkbox_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '0';

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            checked($value, '1', false),
            esc_html($label)
        );

        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    private function get_existing_api_key(): string
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (! is_array($settings) || ! isset($settings['api_key'])) {
            return '';
        }

        return sanitize_text_field((string) $settings['api_key']);
    }

    public function render_api_section_intro(): void
    {
        echo '<p class="description">';
        echo esc_html__('You need your own football-data.org API key to fetch live standings. Keep API credentials out of public repositories and shared screenshots.', 'premier-league-table');
        echo '</p>';
    }

    public function render_appearance_section_intro(): void
    {
        echo '<p class="description">';
        echo esc_html__('Appearance is managed through presets. Legacy preserves the released frontend. Custom re-enables a limited, validated set of fonts and colors so styling stays predictable and readable.', 'premier-league-table');
        echo '</p>';
    }

    private function render_preview_panel(): void
    {
        $settings = $this->get_settings();
        $preview_focus_team = $settings['favorite_team'] !== '' ? (string) $settings['favorite_team'] : 'Tottenham Hotspur';
        if (! $this->preview_rows_include_team($preview_focus_team)) {
            $preview_focus_team = 'Tottenham Hotspur';
        }
        $theme_config = $this->get_frontend_theme_config($settings);
        $class_names = implode(' ', array_map('sanitize_html_class', $theme_config['classes']));
        $style_attribute = $theme_config['style'];
        ?>
        <div class="plt-preview-panel">
            <h2><?php echo esc_html__('Live preview', 'premier-league-table'); ?></h2>
            <p class="plt-preview-note" id="plt-preview-note">
                <?php echo esc_html__('Legacy keeps the released Spurs-style table intact. Switch to Custom to apply the appearance controls.', 'premier-league-table'); ?>
            </p>
            <div class="plt-preview-shell">
                <div
                    id="plt-live-preview"
                    class="<?php echo esc_attr($class_names); ?>"
                    <?php echo $style_attribute !== '' ? 'style="' . esc_attr($style_attribute) . '"' : ''; ?>
                >
                    <div class="plt-table__wrap">
                        <table class="plt-standings">
                            <thead>
                                <tr>
                                    <th scope="col" class="plt-col-pos"><?php echo esc_html__('P', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-team"><?php echo esc_html__('Club', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-played"><?php echo esc_html__('K', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-won"><?php echo esc_html__('V', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-draw"><?php echo esc_html__('U', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-lost"><?php echo esc_html__('T', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-gf"><?php echo esc_html__('M+', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-ga"><?php echo esc_html__('M-', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-gd"><?php echo esc_html__('MF', 'premier-league-table'); ?></th>
                                    <th scope="col" class="plt-col-points"><?php echo esc_html__('P', 'premier-league-table'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->get_preview_rows() as $row) : ?>
                                    <?php $is_favorite = $this->is_preview_focus_team((string) $row['team'], $preview_focus_team); ?>
                                    <tr class="<?php echo $is_favorite ? 'is-favorite' : ''; ?>" data-team="<?php echo esc_attr($this->normalize_team_name((string) $row['team'])); ?>">
                                        <th scope="row" class="plt-col-pos"><?php echo esc_html((string) $row['position']); ?></th>
                                        <td class="plt-team plt-col-team">
                                            <span class="plt-team__crest plt-team__crest--placeholder" aria-hidden="true"></span>
                                            <span class="plt-team__name"><?php echo esc_html((string) $row['team']); ?></span>
                                        </td>
                                        <td><?php echo esc_html((string) $row['played']); ?></td>
                                        <td><?php echo esc_html((string) $row['won']); ?></td>
                                        <td><?php echo esc_html((string) $row['draw']); ?></td>
                                        <td><?php echo esc_html((string) $row['lost']); ?></td>
                                        <td><?php echo esc_html((string) $row['goals_for']); ?></td>
                                        <td><?php echo esc_html((string) $row['goals_against']); ?></td>
                                        <td><?php echo esc_html((string) $row['goal_diff']); ?></td>
                                        <td class="plt-col-points"><?php echo esc_html((string) $row['points']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="plt-table__meta"><?php echo esc_html__('Preview only. The live shortcode keeps the same safe layout width.', 'premier-league-table'); ?></p>
                </div>
            </div>
            <div class="plt-help-card">
                <strong><?php echo esc_html__('Safety guardrails', 'premier-league-table'); ?></strong>
                <ul>
                    <li><?php echo esc_html__('Only trusted font choices are allowed.', 'premier-league-table'); ?></li>
                    <li><?php echo esc_html__('Header and focus-row colors fall back if contrast becomes unreadable.', 'premier-league-table'); ?></li>
                    <li><?php echo esc_html__('Custom mode changes design tokens, not the table structure.', 'premier-league-table'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    private function get_preview_rows(): array
    {
        return [
            [
                'position' => 1,
                'team' => 'Arsenal',
                'played' => 31,
                'won' => 21,
                'draw' => 7,
                'lost' => 3,
                'goals_for' => 61,
                'goals_against' => 22,
                'goal_diff' => 39,
                'points' => 70,
            ],
            [
                'position' => 3,
                'team' => 'Manchester United',
                'played' => 31,
                'won' => 15,
                'draw' => 10,
                'lost' => 6,
                'goals_for' => 56,
                'goals_against' => 43,
                'goal_diff' => 13,
                'points' => 55,
            ],
            [
                'position' => 17,
                'team' => 'Tottenham Hotspur',
                'played' => 31,
                'won' => 7,
                'draw' => 9,
                'lost' => 15,
                'goals_for' => 40,
                'goals_against' => 50,
                'goal_diff' => -10,
                'points' => 30,
            ],
            [
                'position' => 20,
                'team' => 'Wolverhampton Wanderers',
                'played' => 31,
                'won' => 3,
                'draw' => 8,
                'lost' => 20,
                'goals_for' => 24,
                'goals_against' => 54,
                'goal_diff' => -30,
                'points' => 17,
            ],
        ];
    }

    private function is_preview_focus_team(string $team_name, string $favorite_team): bool
    {
        $team_name = $this->normalize_team_name($team_name);
        $favorite_team = $this->normalize_team_name($favorite_team);

        if ($team_name === '' || $favorite_team === '') {
            return false;
        }

        if ($team_name === $favorite_team) {
            return true;
        }

        return strpos($team_name, $favorite_team) !== false || strpos($favorite_team, $team_name) !== false;
    }

    private function preview_rows_include_team(string $favorite_team): bool
    {
        foreach ($this->get_preview_rows() as $row) {
            if ($this->is_preview_focus_team((string) $row['team'], $favorite_team)) {
                return true;
            }
        }

        return false;
    }
}
