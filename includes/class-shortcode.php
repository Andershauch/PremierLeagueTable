<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Shortcode
{
    private PLT_Settings $settings;
    private PLT_Api_Client $api_client;

    public function __construct(PLT_Settings $settings, PLT_Api_Client $api_client)
    {
        $this->settings = $settings;
        $this->api_client = $api_client;
    }

    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('pl_table', [$this, 'render_shortcode']);
    }

    public function register_assets(): void
    {
        $style_version = file_exists(PLT_PLUGIN_DIR . 'assets/css/frontend.css')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/css/frontend.css')
            : '1.0.2';

        wp_register_style(
            'plt-frontend',
            PLT_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            $style_version
        );
    }

    public function render_shortcode($atts = []): string
    {
        wp_enqueue_style('plt-frontend');

        $settings = $this->settings->get_settings();
        $favorite_team = trim((string) ($settings['favorite_team'] ?? ''));
        $cache_ttl_minutes = isset($settings['cache_ttl_minutes']) ? absint($settings['cache_ttl_minutes']) : 10;
        if (! in_array($cache_ttl_minutes, [1, 5, 10, 15, 30, 60], true)) {
            $cache_ttl_minutes = 10;
        }

        $font_scale = in_array(($settings['font_scale'] ?? 'medium'), ['small', 'medium', 'large'], true)
            ? (string) $settings['font_scale']
            : 'medium';
        $density = in_array(($settings['density'] ?? 'comfortable'), ['compact', 'comfortable'], true)
            ? (string) $settings['density']
            : 'comfortable';

        $class_names = implode(
            ' ',
            array_map(
                'sanitize_html_class',
                ['plt-table', 'plt-font-' . $font_scale, 'plt-density-' . $density]
            )
        );

        $primary_color = $this->safe_hex_color((string) ($settings['primary_color'] ?? ''), '#0a1c54');
        $accent_color = $this->safe_hex_color((string) ($settings['accent_color'] ?? ''), '#f6fe08');
        $text_color = $this->safe_hex_color((string) ($settings['text_color'] ?? ''), '#7a7a7a');
        $font_family = $this->safe_font_family((string) ($settings['font_family'] ?? '"Apex New", sans-serif'), '"Apex New", sans-serif');
        $header_bg = $this->safe_hex_color((string) ($settings['header_bg_color'] ?? ''), '#f5f6f8');
        $header_text = $this->safe_hex_color((string) ($settings['header_text_color'] ?? ''), '#0a1c54');
        $favorite_bg = $this->safe_hex_color((string) ($settings['favorite_row_bg'] ?? ''), '#0a1c54');
        $favorite_text = $this->safe_hex_color((string) ($settings['favorite_row_text'] ?? ''), '#ffffff');
        $row_padding = $this->clamp_int((int) ($settings['row_padding'] ?? 8), 4, 20, 8);
        $border_radius = $this->clamp_int((int) ($settings['border_radius'] ?? 0), 0, 20, 0);

        $style = sprintf(
            '--plt-primary:%1$s;--plt-accent:%2$s;--plt-text:%3$s;--plt-font-family:%4$s;--plt-header-bg:%5$s;--plt-header-text:%6$s;--plt-favorite-bg:%7$s;--plt-favorite-text:%8$s;--plt-row-padding:%9$dpx;--plt-border-radius:%10$dpx;',
            $primary_color,
            $accent_color,
            $text_color,
            $font_family,
            $header_bg,
            $header_text,
            $favorite_bg,
            $favorite_text,
            $row_padding,
            $border_radius
        );

        $zebra_class = (! empty($settings['zebra_rows']) && (string) $settings['zebra_rows'] === '1')
            ? 'plt-zebra-on'
            : 'plt-zebra-off';

        $table_data = $this->api_client->get_premier_league_table(
            (string) ($settings['api_key'] ?? ''),
            $cache_ttl_minutes * MINUTE_IN_SECONDS
        );

        ob_start();
        ?>
        <div class="<?php echo esc_attr($class_names . ' ' . $zebra_class); ?>" style="<?php echo esc_attr($style); ?>">
            <div class="plt-table__header">
                <h3><?php echo esc_html__('Premier League Stilling', 'premier-league-table'); ?></h3>
            </div>
            <?php
            if (is_wp_error($table_data)) {
                $this->render_error_box($table_data, $cache_ttl_minutes);
            } else {
                $this->render_table($table_data, $favorite_team, $cache_ttl_minutes);
            }
            ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_error_box(WP_Error $error, int $cache_ttl_minutes): void
    {
        $message = $error->get_error_message();
        if (! is_string($message) || $message === '') {
            $message = __('Der opstod en ukendt fejl ved hentning af stillingsdata.', 'premier-league-table');
        }

        ?>
        <div class="plt-table__error">
            <p><?php echo esc_html($message); ?></p>
            <?php if (current_user_can('manage_options')) : ?>
                <p class="plt-table__error-help">
                    <?php echo esc_html__('Tjek API-noegle under Indstillinger -> Premier League Table.', 'premier-league-table'); ?>
                </p>
                <p class="plt-table__error-help">
                    <?php
                    printf(
                        esc_html__('Aktuel cache: %d min.', 'premier-league-table'),
                        $cache_ttl_minutes
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_table(array $table_data, string $favorite_team, int $cache_ttl_minutes): void
    {
        $rows = isset($table_data['rows']) && is_array($table_data['rows']) ? $table_data['rows'] : [];
        $competition = isset($table_data['competition']) ? (string) $table_data['competition'] : 'Premier League';
        $table_id = wp_unique_id('plt-standings-');
        $caption_id = $table_id . '-caption';
        $labels = [
            'position' => __('Pos.', 'premier-league-table'),
            'team' => __('Klub', 'premier-league-table'),
            'played' => __('Kampe', 'premier-league-table'),
            'won' => __('Vundne', 'premier-league-table'),
            'draw' => __('Uafgjorte', 'premier-league-table'),
            'lost' => __('Tabte', 'premier-league-table'),
            'goals_for' => __('Maal scoret', 'premier-league-table'),
            'goals_against' => __('Maal imod', 'premier-league-table'),
            'goal_diff' => __('Maaldifference', 'premier-league-table'),
            'points' => __('Point', 'premier-league-table'),
        ];

        if (empty($rows)) {
            ?>
            <div class="plt-table__error">
                <p><?php echo esc_html__('Ingen stillingsdata tilgaengelige lige nu.', 'premier-league-table'); ?></p>
            </div>
            <?php
            return;
        }
        ?>
        <div class="plt-table__wrap" tabindex="0">
            <table id="<?php echo esc_attr($table_id); ?>" class="plt-standings" aria-describedby="<?php echo esc_attr($caption_id); ?>">
                <caption id="<?php echo esc_attr($caption_id); ?>" class="plt-visually-hidden">
                    <?php echo esc_html__('Live stillingstabel for Premier League.', 'premier-league-table'); ?>
                </caption>
                <colgroup>
                    <col class="plt-col-pos" />
                    <col class="plt-col-team" />
                    <col class="plt-col-played" />
                    <col class="plt-col-won" />
                    <col class="plt-col-draw" />
                    <col class="plt-col-lost" />
                    <col class="plt-col-gf" />
                    <col class="plt-col-ga" />
                    <col class="plt-col-gd" />
                    <col class="plt-col-points" />
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="plt-col-pos"><?php echo esc_html__('P', 'premier-league-table'); ?></th>
                        <th scope="col" class="plt-col-team"><?php echo esc_html__('Klub', 'premier-league-table'); ?></th>
                        <th scope="col" class="plt-col-played"><abbr title="<?php echo esc_attr($labels['played']); ?>"><?php echo esc_html__('K', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-won"><abbr title="<?php echo esc_attr($labels['won']); ?>"><?php echo esc_html__('V', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-draw"><abbr title="<?php echo esc_attr($labels['draw']); ?>"><?php echo esc_html__('U', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-lost"><abbr title="<?php echo esc_attr($labels['lost']); ?>"><?php echo esc_html__('T', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-gf"><abbr title="<?php echo esc_attr($labels['goals_for']); ?>"><?php echo esc_html__('M+', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-ga"><abbr title="<?php echo esc_attr($labels['goals_against']); ?>"><?php echo esc_html__('M-', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-gd"><abbr title="<?php echo esc_attr($labels['goal_diff']); ?>"><?php echo esc_html__('MF', 'premier-league-table'); ?></abbr></th>
                        <th scope="col" class="plt-col-points"><abbr title="<?php echo esc_attr($labels['points']); ?>"><?php echo esc_html__('P', 'premier-league-table'); ?></abbr></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row) : ?>
                    <?php
                    $team_name = isset($row['team_name']) ? (string) $row['team_name'] : '';
                    $team_name_display = $this->format_team_display_name($team_name);
                    $is_favorite = $this->is_favorite_match($team_name, $favorite_team);
                    ?>
                    <tr class="<?php echo $is_favorite ? 'is-favorite' : ''; ?>">
                        <th scope="row" class="plt-col-pos" data-label="<?php echo esc_attr($labels['position']); ?>"><?php echo esc_html((string) ((int) ($row['position'] ?? 0))); ?></th>
                        <td class="plt-team plt-col-team" data-label="<?php echo esc_attr($labels['team']); ?>">
                            <?php if (! empty($row['team_crest'])) : ?>
                                <img
                                    class="plt-team__crest"
                                    src="<?php echo esc_url((string) $row['team_crest']); ?>"
                                    alt=""
                                    loading="lazy"
                                    decoding="async"
                                />
                            <?php endif; ?>
                            <span>
                                <?php echo esc_html($team_name_display); ?>
                                <?php if ($is_favorite) : ?>
                                    <span class="plt-visually-hidden"><?php echo esc_html__(' (yndlingshold)', 'premier-league-table'); ?></span>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="plt-col-played" data-label="<?php echo esc_attr($labels['played']); ?>"><?php echo esc_html((string) ((int) ($row['played'] ?? 0))); ?></td>
                        <td class="plt-col-won" data-label="<?php echo esc_attr($labels['won']); ?>"><?php echo esc_html((string) ((int) ($row['won'] ?? 0))); ?></td>
                        <td class="plt-col-draw" data-label="<?php echo esc_attr($labels['draw']); ?>"><?php echo esc_html((string) ((int) ($row['draw'] ?? 0))); ?></td>
                        <td class="plt-col-lost" data-label="<?php echo esc_attr($labels['lost']); ?>"><?php echo esc_html((string) ((int) ($row['lost'] ?? 0))); ?></td>
                        <td class="plt-col-gf" data-label="<?php echo esc_attr($labels['goals_for']); ?>"><?php echo esc_html((string) ((int) ($row['goals_for'] ?? 0))); ?></td>
                        <td class="plt-col-ga" data-label="<?php echo esc_attr($labels['goals_against']); ?>"><?php echo esc_html((string) ((int) ($row['goals_against'] ?? 0))); ?></td>
                        <td class="plt-col-gd" data-label="<?php echo esc_attr($labels['goal_diff']); ?>"><?php echo esc_html((string) ((int) ($row['goal_diff'] ?? 0))); ?></td>
                        <td class="plt-points plt-col-points" data-label="<?php echo esc_attr($labels['points']); ?>"><?php echo esc_html((string) ((int) ($row['points'] ?? 0))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="plt-table__meta">
            <?php
            printf(
                esc_html__('Kilde: %1$s (opdateres hvert %2$d. minut)', 'premier-league-table'),
                esc_html($competition),
                $cache_ttl_minutes
            );
            ?>
        </p>
        <?php
    }

    private function is_favorite_match(string $team_name, string $favorite_team): bool
    {
        $team_name = $this->normalize_team_name($team_name);
        $favorite_team = $this->normalize_team_name($favorite_team);

        if ($team_name === '' || $favorite_team === '') {
            return false;
        }

        if ($team_name === $favorite_team) {
            return true;
        }

        // Allow partial matching for inputs like "Tottenham" vs "Tottenham Hotspur".
        if (strpos($team_name, $favorite_team) !== false || strpos($favorite_team, $team_name) !== false) {
            return true;
        }

        $aliases = [
            'spurs' => 'tottenham hotspur',
            'wolves' => 'wolverhampton wanderers',
            'man city' => 'manchester city',
            'man utd' => 'manchester united',
            'brighton' => 'brighton hove albion',
            'forest' => 'nottingham forest',
            'newcastle' => 'newcastle united',
            'west ham' => 'west ham united',
        ];

        foreach ($aliases as $short => $canonical) {
            if (
                ($favorite_team === $short && $team_name === $canonical) ||
                ($favorite_team === $canonical && $team_name === $short)
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalize_team_name(string $name): string
    {
        $name = remove_accents(strtolower(trim($name)));
        $name = preg_replace('/\b(fc|afc|cf)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }

    private function safe_hex_color(string $value, string $fallback): string
    {
        $sanitized = sanitize_hex_color($value);
        if (! is_string($sanitized) || $sanitized === '') {
            return $fallback;
        }

        return $sanitized;
    }

    private function clamp_int(int $value, int $min, int $max, int $fallback): int
    {
        if ($value < $min || $value > $max) {
            return $fallback;
        }

        return $value;
    }

    private function safe_font_family(string $value, string $fallback): string
    {
        $value = trim($value);
        if ($value === '') {
            return $fallback;
        }

        if (! preg_match('/^[a-zA-Z0-9\\s,\\-"\']+$/', $value)) {
            return $fallback;
        }

        $value = function_exists('mb_substr')
            ? mb_substr($value, 0, 120)
            : substr($value, 0, 120);

        return $value;
    }

    private function format_team_display_name(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name_map = [
            'Brighton & Hove Albion' => 'Brighton',
            'Newcastle United' => 'Newcastle',
            'West Ham United' => 'West Ham',
            'Wolverhampton Wanderers' => 'Wolves',
            'Tottenham Hotspur' => 'Tottenham',
            'Nottingham Forest' => 'Nottm Forest',
            'Manchester United' => 'Man United',
            'Manchester City' => 'Man City',
            'Leeds United' => 'Leeds',
        ];
        if (isset($name_map[$name])) {
            return $name_map[$name];
        }

        // Keep display labels compact and readable in narrow columns.
        $name = preg_replace('/\s*&\s*/u', ' & ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);
        $name = preg_replace('/\s+(FC|AFC|CF)$/iu', '', (string) $name);
        $name = preg_replace('/^AFC\s+/iu', '', (string) $name);

        return trim((string) $name);
    }
}

