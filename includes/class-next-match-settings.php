<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Next_Match_Settings
{
    private const OPTION_NAME = 'plt_next_match_settings';
    private const PAGE_SLUG = 'plt-next-match-settings';

    public function register_hooks(): void
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_settings_page(): void
    {
        add_options_page(
            __('Premier League Next Match', 'premier-league-table'),
            __('PL Next Match', 'premier-league-table'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'plt_next_match_settings_group',
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->get_default_settings(),
            ]
        );

        add_settings_section(
            'plt_next_match_data_section',
            __('Data and time', 'premier-league-table'),
            [$this, 'render_data_section_intro'],
            self::PAGE_SLUG
        );

        add_settings_field(
            'timezone',
            __('Timezone', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_next_match_data_section',
            [
                'key' => 'timezone',
                'options' => $this->get_timezone_options(),
                'description' => __('Default is Europe/Copenhagen. Kickoff is always formatted in this timezone.', 'premier-league-table'),
            ]
        );

        add_settings_field(
            'datetime_format',
            __('Kickoff format', 'premier-league-table'),
            [$this, 'render_text_field'],
            self::PAGE_SLUG,
            'plt_next_match_data_section',
            [
                'key' => 'datetime_format',
                'description' => __('Default: d.m.Y H:i (example: 21.08.2026 20:00). Uses PHP date tokens.', 'premier-league-table'),
            ]
        );

        add_settings_field(
            'cache_ttl_minutes',
            __('Cache lifetime (minutes)', 'premier-league-table'),
            [$this, 'render_select_field'],
            self::PAGE_SLUG,
            'plt_next_match_data_section',
            [
                'key' => 'cache_ttl_minutes',
                'options' => [
                    '1' => __('1 minute', 'premier-league-table'),
                    '5' => __('5 minutes', 'premier-league-table'),
                    '10' => __('10 minutes', 'premier-league-table'),
                    '15' => __('15 minutes', 'premier-league-table'),
                    '30' => __('30 minutes', 'premier-league-table'),
                ],
            ]
        );

        add_settings_section(
            'plt_next_match_design_section',
            __('Design', 'premier-league-table'),
            [$this, 'render_design_section_intro'],
            self::PAGE_SLUG
        );

        add_settings_field('bar_bg_color', __('Bar background', 'premier-league-table'), [$this, 'render_color_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'bar_bg_color']);
        add_settings_field('text_color', __('Primary text color', 'premier-league-table'), [$this, 'render_color_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'text_color']);
        add_settings_field('meta_color', __('Secondary text color', 'premier-league-table'), [$this, 'render_color_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'meta_color']);
        add_settings_field('accent_color', __('Accent color (VS)', 'premier-league-table'), [$this, 'render_color_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'accent_color']);
        add_settings_field('font_family', __('Font family', 'premier-league-table'), [$this, 'render_select_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'font_family', 'options' => $this->get_font_family_options()]);
        add_settings_field('logo_size', __('Logo size (px)', 'premier-league-table'), [$this, 'render_number_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'logo_size', 'min' => 20, 'max' => 96]);
        add_settings_field('vertical_padding', __('Vertical padding (px)', 'premier-league-table'), [$this, 'render_number_field'], self::PAGE_SLUG, 'plt_next_match_design_section', ['key' => 'vertical_padding', 'min' => 10, 'max' => 48]);
    }

    public function sanitize_settings($input): array
    {
        $defaults = $this->get_default_settings();
        $output = $defaults;
        if (! is_array($input)) {
            return $output;
        }

        $allowed_ttls = [1, 5, 10, 15, 30];
        $ttl = isset($input['cache_ttl_minutes']) ? absint($input['cache_ttl_minutes']) : (int) $defaults['cache_ttl_minutes'];
        $output['cache_ttl_minutes'] = in_array($ttl, $allowed_ttls, true) ? $ttl : (int) $defaults['cache_ttl_minutes'];

        $timezone = isset($input['timezone']) ? sanitize_text_field((string) $input['timezone']) : (string) $defaults['timezone'];
        $output['timezone'] = in_array($timezone, timezone_identifiers_list(), true) ? $timezone : (string) $defaults['timezone'];

        $format = isset($input['datetime_format']) ? sanitize_text_field((string) $input['datetime_format']) : (string) $defaults['datetime_format'];
        $output['datetime_format'] = $format !== '' ? $format : (string) $defaults['datetime_format'];

        $output['bar_bg_color'] = $this->sanitize_color((string) ($input['bar_bg_color'] ?? ''), (string) $defaults['bar_bg_color']);
        $output['text_color'] = $this->sanitize_color((string) ($input['text_color'] ?? ''), (string) $defaults['text_color']);
        $output['meta_color'] = $this->sanitize_color((string) ($input['meta_color'] ?? ''), (string) $defaults['meta_color']);
        $output['accent_color'] = $this->sanitize_color((string) ($input['accent_color'] ?? ''), (string) $defaults['accent_color']);

        $font_family = isset($input['font_family']) ? sanitize_text_field((string) $input['font_family']) : (string) $defaults['font_family'];
        $output['font_family'] = isset($this->get_font_family_options()[$font_family]) ? $font_family : (string) $defaults['font_family'];

        $output['logo_size'] = $this->sanitize_number($input['logo_size'] ?? $defaults['logo_size'], 20, 96, (int) $defaults['logo_size']);
        $output['vertical_padding'] = $this->sanitize_number($input['vertical_padding'] ?? $defaults['vertical_padding'], 10, 48, (int) $defaults['vertical_padding']);

        return $output;
    }

    public function get_settings(): array
    {
        $defaults = $this->get_default_settings();
        $settings = get_option(self::OPTION_NAME, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        return wp_parse_args($settings, $defaults);
    }

    public function get_theme_style_attribute(?array $settings = null): string
    {
        $settings = is_array($settings) ? wp_parse_args($settings, $this->get_settings()) : $this->get_settings();
        $font_map = [
            'theme' => 'inherit',
            'system' => '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
            'apex' => '"Apex New", sans-serif',
            'arial' => 'Arial, Helvetica, sans-serif',
            'georgia' => 'Georgia, "Times New Roman", serif',
        ];

        $styles = [
            '--plt-next-bg' => (string) $settings['bar_bg_color'],
            '--plt-next-text' => (string) $settings['text_color'],
            '--plt-next-meta' => (string) $settings['meta_color'],
            '--plt-next-accent' => (string) $settings['accent_color'],
            '--plt-next-logo-size' => absint($settings['logo_size']) . 'px',
            '--plt-next-padding-y' => absint($settings['vertical_padding']) . 'px',
            '--plt-next-font' => $font_map[(string) $settings['font_family']] ?? $font_map['theme'],
        ];

        $chunks = [];
        foreach ($styles as $key => $value) {
            $chunks[] = $key . ': ' . trim((string) $value);
        }

        return implode('; ', $chunks);
    }

    public function render_settings_page(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <div class="plt-settings-wrap">
                <h1><?php echo esc_html__('Next Match Settings', 'premier-league-table'); ?></h1>
                <p><?php echo esc_html__('This module uses the same global Focus team from Premier League Table settings and now renders one Premier League card plus one Women\'s Super League card when data is available. During offseason periods the WSL side may be empty if the provider has not published the next fixture yet.', 'premier-league-table'); ?></p>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('plt_next_match_settings_group');
                    do_settings_sections(self::PAGE_SLUG);
                    submit_button(__('Save next match settings', 'premier-league-table'));
                    ?>
                </form>
                <p><code>[pl_next_match]</code></p>
            </div>
        </div>
        <?php
    }

    public function render_data_section_intro(): void
    {
        echo '<p class="description">' . esc_html__('Kickoff defaults to Danish formatting. These settings apply to both the Premier League and WSL next-match cards.', 'premier-league-table') . '</p>';
    }

    public function render_design_section_intro(): void
    {
        echo '<p class="description">' . esc_html__('Design settings here affect the next-match module only. The shortcode can now show separate Premier League and WSL cards side by side when both datasets are available.', 'premier-league-table') . '</p>';
    }

    public function render_select_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $options = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';

        printf('<select name="%1$s[%2$s]">', esc_attr(self::OPTION_NAME), esc_attr($key));
        foreach ($options as $option_value => $label) {
            printf('<option value="%1$s" %2$s>%3$s</option>', esc_attr((string) $option_value), selected($value, (string) $option_value, false), esc_html((string) $label));
        }
        echo '</select>';

        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_text_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $description = isset($args['description']) ? (string) $args['description'] : '';
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : '';

        printf('<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" />', esc_attr(self::OPTION_NAME), esc_attr($key), esc_attr($value));
        if ($description !== '') {
            printf('<p class="description">%s</p>', esc_html($description));
        }
    }

    public function render_number_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $min = isset($args['min']) ? (int) $args['min'] : 0;
        $max = isset($args['max']) ? (int) $args['max'] : 200;
        $settings = $this->get_settings();
        $value = isset($settings[$key]) ? (int) $settings[$key] : $min;

        printf(
            '<input type="number" min="%1$d" max="%2$d" name="%3$s[%4$s]" value="%5$d" />',
            $min,
            $max,
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            $value
        );
    }

    public function render_color_field(array $args): void
    {
        $key = isset($args['key']) ? sanitize_key($args['key']) : '';
        $settings = $this->get_settings();
        $defaults = $this->get_default_settings();
        $value = isset($settings[$key]) ? (string) $settings[$key] : (string) $defaults[$key];

        printf(
            '<input type="text" class="plt-color-field" name="%1$s[%2$s]" value="%3$s" data-default-color="%4$s" />',
            esc_attr(self::OPTION_NAME),
            esc_attr($key),
            esc_attr($value),
            esc_attr((string) $defaults[$key])
        );
    }

    private function get_default_settings(): array
    {
        return [
            'timezone' => 'Europe/Copenhagen',
            'datetime_format' => 'd.m.Y H:i',
            'cache_ttl_minutes' => 10,
            'bar_bg_color' => '#0a1c54',
            'text_color' => '#ffffff',
            'meta_color' => '#dce6ff',
            'accent_color' => '#ffffff',
            'font_family' => 'apex',
            'logo_size' => 56,
            'vertical_padding' => 20,
        ];
    }

    private function get_timezone_options(): array
    {
        return [
            'Europe/Copenhagen' => 'Europe/Copenhagen',
            'Europe/London' => 'Europe/London',
            'UTC' => 'UTC',
            'Europe/Berlin' => 'Europe/Berlin',
            'America/New_York' => 'America/New_York',
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

    private function sanitize_color(string $color, string $fallback): string
    {
        $sanitized = sanitize_hex_color($color);
        return $sanitized === null ? $fallback : $sanitized;
    }

    private function sanitize_number($value, int $min, int $max, int $fallback): int
    {
        $number = absint($value);
        if ($number < $min || $number > $max) {
            return $fallback;
        }

        return $number;
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        $admin_style_version = file_exists(PLT_PLUGIN_DIR . 'assets/css/admin.css')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/css/admin.css')
            : '1.3.0';
        wp_enqueue_style('plt-admin-shared', PLT_PLUGIN_URL . 'assets/css/admin.css', ['wp-color-picker'], $admin_style_version);
        wp_add_inline_script(
            'wp-color-picker',
            'jQuery(function($){if(typeof $.fn.wpColorPicker==="function"){$(".plt-color-field").wpColorPicker();}});'
        );
    }
}
