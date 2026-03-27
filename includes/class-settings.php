<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Settings
{
    private const OPTION_NAME = 'plt_settings';
    private const PAGE_SLUG = 'plt-settings';

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
            __('API-indstillinger', 'premier-league-table'),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'api_key',
            __('API-noegle', 'premier-league-table'),
            [$this, 'render_api_key_field'],
            self::PAGE_SLUG,
            'plt_api_section',
            []
        );

        add_settings_field(
            'favorite_team',
            __('Yndlingshold', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_api_section',
            [
                'key' => 'favorite_team',
                'options' => $this->get_favorite_team_options(),
            ]
        );

        add_settings_field(
            'cache_ttl_minutes',
            __('Cache levetid (minutter)', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_api_section',
            [
                'key' => 'cache_ttl_minutes',
                'options' => [
                    '1' => __('1 minut', 'premier-league-table'),
                    '5' => __('5 minutter', 'premier-league-table'),
                    '10' => __('10 minutter', 'premier-league-table'),
                    '15' => __('15 minutter', 'premier-league-table'),
                    '30' => __('30 minutter', 'premier-league-table'),
                    '60' => __('60 minutter', 'premier-league-table'),
                ],
            ]
        );

        add_settings_section(
            'plt_style_section',
            __('Design', 'premier-league-table'),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'primary_color',
            __('Primaer farve', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            ['key' => 'primary_color']
        );

        add_settings_field(
            'accent_color',
            __('Accent-farve', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            ['key' => 'accent_color']
        );

        add_settings_field(
            'text_color',
            __('Tekstfarve', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            ['key' => 'text_color']
        );

        add_settings_field(
            'font_family',
            __('Font family', 'premier-league-table'),
            [$this, 'render_text_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            [
                'key' => 'font_family',
                'placeholder' => '"Apex New", sans-serif',
            ]
        );

        add_settings_field(
            'font_scale',
            __('Font-skala', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            [
                'key' => 'font_scale',
                'options' => [
                    'small' => __('Lille', 'premier-league-table'),
                    'medium' => __('Mellem', 'premier-league-table'),
                    'large' => __('Stor', 'premier-league-table'),
                ],
            ]
        );

        add_settings_field(
            'density',
            __('Tabel-density', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_style_section',
            [
                'key' => 'density',
                'options' => [
                    'compact' => __('Kompakt', 'premier-league-table'),
                    'comfortable' => __('Komfortabel', 'premier-league-table'),
                ],
            ]
        );

        add_settings_section(
            'plt_advanced_style_section',
            __('Udseende (avanceret)', 'premier-league-table'),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field(
            'header_bg_color',
            __('Header baggrund', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            ['key' => 'header_bg_color']
        );

        add_settings_field(
            'header_text_color',
            __('Header tekstfarve', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            ['key' => 'header_text_color']
        );

        add_settings_field(
            'favorite_row_bg',
            __('Yndlingshold baggrund', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            ['key' => 'favorite_row_bg']
        );

        add_settings_field(
            'favorite_row_text',
            __('Yndlingshold tekst', 'premier-league-table'),
            [$this, 'render_color_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            ['key' => 'favorite_row_text']
        );

        add_settings_field(
            'border_radius',
            __('Border radius (px)', 'premier-league-table'),
            [$this, 'render_number_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            [
                'key' => 'border_radius',
                'min' => 0,
                'max' => 20,
                'step' => 1,
            ]
        );

        add_settings_field(
            'row_padding',
            __('Række padding (px)', 'premier-league-table'),
            [$this, 'render_number_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            [
                'key' => 'row_padding',
                'min' => 4,
                'max' => 20,
                'step' => 1,
            ]
        );

        add_settings_field(
            'zebra_rows',
            __('Zebra rækker', 'premier-league-table'),
            [$this, 'render_checkbox_field'],
            self::PAGE_SLUG,
            'plt_advanced_style_section',
            [
                'key' => 'zebra_rows',
                'label' => __('Aktiver alternate række-farve', 'premier-league-table'),
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
        $output['primary_color'] = $this->sanitize_color($input['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color']);
        $output['accent_color'] = $this->sanitize_color($input['accent_color'] ?? $defaults['accent_color'], $defaults['accent_color']);
        $output['text_color'] = $this->sanitize_color($input['text_color'] ?? $defaults['text_color'], $defaults['text_color']);
        $output['font_family'] = $this->sanitize_font_family((string) ($input['font_family'] ?? $defaults['font_family']), $defaults['font_family']);

        $font_scale = isset($input['font_scale']) ? sanitize_key($input['font_scale']) : $defaults['font_scale'];
        $output['font_scale'] = in_array($font_scale, ['small', 'medium', 'large'], true) ? $font_scale : $defaults['font_scale'];

        $density = isset($input['density']) ? sanitize_key($input['density']) : $defaults['density'];
        $output['density'] = in_array($density, ['compact', 'comfortable'], true) ? $density : $defaults['density'];

        $output['header_bg_color'] = $this->sanitize_color($input['header_bg_color'] ?? $defaults['header_bg_color'], $defaults['header_bg_color']);
        $output['header_text_color'] = $this->sanitize_color($input['header_text_color'] ?? $defaults['header_text_color'], $defaults['header_text_color']);
        $output['favorite_row_bg'] = $this->sanitize_color($input['favorite_row_bg'] ?? $defaults['favorite_row_bg'], $defaults['favorite_row_bg']);
        $output['favorite_row_text'] = $this->sanitize_color($input['favorite_row_text'] ?? $defaults['favorite_row_text'], $defaults['favorite_row_text']);

        $output['border_radius'] = $this->sanitize_int_range($input['border_radius'] ?? $defaults['border_radius'], 0, 20, (int) $defaults['border_radius']);
        $output['row_padding'] = $this->sanitize_int_range($input['row_padding'] ?? $defaults['row_padding'], 4, 20, (int) $defaults['row_padding']);
        $output['zebra_rows'] = ! empty($input['zebra_rows']) ? '1' : '0';

        $allowed_cache_ttls = [1, 5, 10, 15, 30, 60];
        $cache_ttl_minutes = isset($input['cache_ttl_minutes']) ? absint($input['cache_ttl_minutes']) : (int) $defaults['cache_ttl_minutes'];
        $output['cache_ttl_minutes'] = in_array($cache_ttl_minutes, $allowed_cache_ttls, true) ? $cache_ttl_minutes : (int) $defaults['cache_ttl_minutes'];

        // Flush cached standings when settings change.
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
                <p><?php echo esc_html__('Tip: Saet cache levetid hoejere for faerre API-kald, lavere for hurtigere opdateringer.', 'premier-league-table'); ?></p>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('plt_settings_group');
                    do_settings_sections(self::PAGE_SLUG);
                    submit_button(__('Gem indstillinger', 'premier-league-table'));
                    ?>
                </form>
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

        return wp_parse_args($settings, $this->get_default_settings());
    }

    private function get_default_settings(): array
    {
        return [
            'api_key' => '',
            'favorite_team' => '',
            'primary_color' => '#0a1c54',
            'accent_color' => '#f6fe08',
            'text_color' => '#7a7a7a',
            'font_family' => '"Apex New", sans-serif',
            'font_scale' => 'medium',
            'density' => 'comfortable',
            'header_bg_color' => '#f5f6f8',
            'header_text_color' => '#0a1c54',
            'favorite_row_bg' => '#0a1c54',
            'favorite_row_text' => '#ffffff',
            'border_radius' => 0,
            'row_padding' => 8,
            'zebra_rows' => '0',
            'cache_ttl_minutes' => 10,
        ];
    }

    private function get_favorite_team_options(): array
    {
        $fallback = [
            '' => __('Vaelg hold', 'premier-league-table'),
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

        $cached = get_transient('plt_pl_standings_v1');
        if (! is_array($cached) || ! isset($cached['rows']) || ! is_array($cached['rows'])) {
            return $fallback;
        }

        $dynamic = ['' => __('Vaelg hold', 'premier-league-table')];
        foreach ($cached['rows'] as $row) {
            if (! is_array($row) || ! isset($row['team_name'])) {
                continue;
            }

            $name = trim((string) $row['team_name']);
            if ($name !== '') {
                $dynamic[$name] = $name;
            }
        }

        if (count($dynamic) > 1) {
            return $dynamic;
        }

        return $fallback;
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

        // Only persist teams that exist in the trusted dropdown data.
        $allowed_teams = array_keys($this->get_favorite_team_options());
        if (! in_array($favorite_team, $allowed_teams, true)) {
            return $fallback;
        }

        return $favorite_team;
    }

    private function sanitize_color(string $color, string $fallback): string
    {
        $sanitized = sanitize_hex_color($color);
        if ($sanitized === null) {
            return $fallback;
        }

        return $sanitized;
    }

    private function sanitize_int_range($value, int $min, int $max, int $fallback): int
    {
        $int_value = absint((string) $value);
        if ($int_value < $min || $int_value > $max) {
            return $fallback;
        }

        return $int_value;
    }

    private function sanitize_font_family(string $value, string $fallback): string
    {
        $value = sanitize_text_field($value);
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        // Allow basic CSS font-family tokens only.
        if (! preg_match('/^[a-zA-Z0-9\\s,\\-"\']+$/', $value)) {
            return $fallback;
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 120)
            : substr($value, 0, 120);
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_style(
            'plt-admin',
            PLT_PLUGIN_URL . 'assets/css/admin.css',
            [],
            '0.6.1'
        );
        wp_enqueue_script(
            'plt-admin',
            PLT_PLUGIN_URL . 'assets/js/admin.js',
            ['wp-color-picker'],
            '0.1.0',
            true
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
            // Never print the stored API key in plain text back into the UI.
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
            esc_html__('Lad feltet vaere tomt for at beholde eksisterende API-noegle.', 'premier-league-table')
        );

        printf(
            '<p class="description">%s</p>',
            $has_key
                ? esc_html__('Status: API-noegle er sat.', 'premier-league-table')
                : esc_html__('Status: Ingen API-noegle gemt endnu.', 'premier-league-table')
        );

        printf(
            '<label><input type="checkbox" name="%1$s[clear_api_key]" value="1" /> %2$s</label>',
            esc_attr(self::OPTION_NAME),
            esc_html__('Ryd eksisterende API-noegle ved gem', 'premier-league-table')
        );
    }

    public function render_color_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '#111827';

        printf(
            '<input type="text" class="plt-color-field" name="%1$s[%2$s]" value="%3$s" data-default-color="#111827" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value)
        );
    }

    public function render_select_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $options = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
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
    }

    public function render_number_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $min = isset($args['min']) ? (int) $args['min'] : 0;
        $max = isset($args['max']) ? (int) $args['max'] : 100;
        $step = isset($args['step']) ? (int) $args['step'] : 1;
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (int) $settings[$key] : $min;

        printf(
            '<input type="number" name="%1$s[%2$s]" min="%3$d" max="%4$d" step="%5$d" value="%6$d" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            $min,
            $max,
            $step,
            $value
        );
    }

    public function render_checkbox_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '0';

        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            checked($value, '1', false),
            esc_html($label)
        );
    }

    private function get_existing_api_key(): string
    {
        $settings = get_option(self::OPTION_NAME, []);
        if (! is_array($settings) || ! isset($settings['api_key'])) {
            return '';
        }

        return sanitize_text_field((string) $settings['api_key']);
    }
}

