<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Shortcode
{
    private PLT_Settings $settings;
    private PLT_Standings_Service $standings_service;

    public function __construct(PLT_Settings $settings, PLT_Standings_Service $standings_service)
    {
        $this->settings = $settings;
        $this->standings_service = $standings_service;
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
        $script_version = file_exists(PLT_PLUGIN_DIR . 'assets/js/frontend-tabs.js')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/js/frontend-tabs.js')
            : '1.0.0';

        wp_register_style(
            'plt-frontend',
            PLT_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            $style_version
        );
        wp_register_script(
            'plt-frontend-tabs',
            PLT_PLUGIN_URL . 'assets/js/frontend-tabs.js',
            [],
            $script_version,
            true
        );
    }

    public function render_shortcode($atts = []): string
    {
        wp_enqueue_style('plt-frontend');

        $atts = shortcode_atts(
            [
                'focus_team' => '',
                'favorite_team' => '',
                'competition' => 'pl',
            ],
            is_array($atts) ? $atts : [],
            'pl_table'
        );

        $settings = $this->settings->get_settings();
        $favorite_team = trim((string) ($atts['focus_team'] ?: $atts['favorite_team'] ?: ($settings['favorite_team'] ?? '')));
        $competition_key = $this->sanitize_competition_key((string) $atts['competition']);
        $cache_ttl_minutes = isset($settings['cache_ttl_minutes']) ? absint($settings['cache_ttl_minutes']) : 10;
        if (! in_array($cache_ttl_minutes, [1, 5, 10, 15, 30, 60], true)) {
            $cache_ttl_minutes = 10;
        }
        $focus_team_context = $this->standings_service->resolve_focus_team_context($favorite_team);

        $theme_config = $this->settings->get_frontend_theme_config($settings);
        $class_names = implode(' ', array_map('sanitize_html_class', $theme_config['classes']));
        $style_attribute = isset($theme_config['style']) ? (string) $theme_config['style'] : '';

        $standings_results = $this->get_standings_results(
            $competition_key,
            $settings,
            $cache_ttl_minutes,
            $focus_team_context,
            $favorite_team
        );
        $title = $this->get_shortcode_title($competition_key);
        $has_tabs = count($standings_results) > 1;
        if ($has_tabs) {
            wp_enqueue_script('plt-frontend-tabs');
        }
        $tabs_id = wp_unique_id('plt-tabs-');

        ob_start();
        ?>
        <div
            class="<?php echo esc_attr($class_names); ?>"
            <?php echo $style_attribute !== '' ? 'style="' . esc_attr($style_attribute) . '"' : ''; ?>
        >
            <div class="plt-table__header">
                <h3><?php echo esc_html($title); ?></h3>
            </div>
            <?php if ($has_tabs) : ?>
                <div class="plt-tabs" data-plt-tabs>
                    <div class="plt-tabs__list" role="tablist" aria-label="<?php echo esc_attr__('Standings competitions', 'premier-league-table'); ?>">
                        <?php foreach ($standings_results as $index => $result) : ?>
                            <?php
                            $tab_id = $tabs_id . '-tab-' . $index;
                            $panel_id = $tabs_id . '-panel-' . $index;
                            $label = isset($result['tab_label']) ? (string) $result['tab_label'] : strtoupper((string) ($result['competition_key'] ?? ''));
                            ?>
                            <button
                                type="button"
                                id="<?php echo esc_attr($tab_id); ?>"
                                class="plt-tabs__tab<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                role="tab"
                                aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                                aria-controls="<?php echo esc_attr($panel_id); ?>"
                                data-plt-tab
                            >
                                <?php echo esc_html($label); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($standings_results as $index => $result) : ?>
                        <?php
                        $tab_id = $tabs_id . '-tab-' . $index;
                        $panel_id = $tabs_id . '-panel-' . $index;
                        $this->render_standings_result($result, $cache_ttl_minutes, $panel_id, $tab_id, $index === 0);
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <?php foreach ($standings_results as $result) : ?>
                    <?php $this->render_standings_result($result, $cache_ttl_minutes); ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    private function render_error_box(WP_Error $error, int $cache_ttl_minutes): void
    {
        $message = $error->get_error_message();
        if (! is_string($message) || $message === '') {
            $message = __('An unknown error occurred while loading the standings table.', 'premier-league-table');
        }

        ?>
        <div class="plt-table__error">
            <p><?php echo esc_html($message); ?></p>
            <?php if (current_user_can('manage_options')) : ?>
                <p class="plt-table__error-help">
                    <?php echo esc_html__('Check the API key under Settings -> Premier League Table.', 'premier-league-table'); ?>
                </p>
                <p class="plt-table__error-help">
                    <?php
                    printf(
                        esc_html__('Current cache: %d min.', 'premier-league-table'),
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
        $competition = isset($table_data['competition_label']) ? (string) $table_data['competition_label'] : 'Premier League';
        $source_label = isset($table_data['source_label']) ? (string) $table_data['source_label'] : 'Football-Data.org';
        $table_id = wp_unique_id('plt-standings-');
        $caption_id = $table_id . '-caption';
        $labels = [
            'position' => __('Pos.', 'premier-league-table'),
            'team' => __('Club', 'premier-league-table'),
            'played' => __('Played', 'premier-league-table'),
            'won' => __('Won', 'premier-league-table'),
            'draw' => __('Drawn', 'premier-league-table'),
            'lost' => __('Lost', 'premier-league-table'),
            'goals_for' => __('Goals for', 'premier-league-table'),
            'goals_against' => __('Goals against', 'premier-league-table'),
            'goal_diff' => __('Goal difference', 'premier-league-table'),
            'points' => __('Point', 'premier-league-table'),
        ];

        if (empty($rows)) {
            ?>
            <div class="plt-table__error">
                <p><?php echo esc_html__('No standings data is available right now.', 'premier-league-table'); ?></p>
            </div>
            <?php
            return;
        }
        ?>
        <section class="plt-table__section">
        <div class="plt-table__wrap" tabindex="0">
            <table id="<?php echo esc_attr($table_id); ?>" class="plt-standings" aria-describedby="<?php echo esc_attr($caption_id); ?>">
                <caption id="<?php echo esc_attr($caption_id); ?>" class="plt-visually-hidden">
                    <?php
                    printf(
                        esc_html__('%s standings table.', 'premier-league-table'),
                        esc_html($competition)
                    );
                    ?>
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
                        <th scope="col" class="plt-col-team"><?php echo esc_html__('Club', 'premier-league-table'); ?></th>
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
                            <span class="plt-team__name">
                                <?php echo esc_html($team_name_display); ?>
                                <?php if ($is_favorite) : ?>
                                    <span class="plt-visually-hidden"><?php echo esc_html__(' (focus team)', 'premier-league-table'); ?></span>
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
                esc_html__('Football data provided by %1$s. Competition: %2$s. Refreshes every %3$d minutes.', 'premier-league-table'),
                esc_html($source_label),
                esc_html($competition),
                $cache_ttl_minutes
            );
            ?>
        </p>
        </section>
        <?php
    }

    private function render_standings_result(
        array $result,
        int $cache_ttl_minutes,
        string $panel_id = '',
        string $tab_id = '',
        bool $is_active = true
    ): void
    {
        $table_data = isset($result['table_data']) ? $result['table_data'] : null;
        $focus_team_name = isset($result['focus_team_name']) ? (string) $result['focus_team_name'] : '';
        $fallback_focus_team = isset($result['fallback_focus_team']) ? (string) $result['fallback_focus_team'] : '';
        $panel_attrs = '';

        if ($panel_id !== '' && $tab_id !== '') {
            $panel_attrs = sprintf(
                ' id="%1$s" class="plt-tabs__panel%2$s" role="tabpanel" aria-labelledby="%3$s"%4$s',
                esc_attr($panel_id),
                $is_active ? ' is-active' : '',
                esc_attr($tab_id),
                $is_active ? '' : ' hidden'
            );
            echo '<div' . $panel_attrs . '>';
        }

        if (is_wp_error($table_data)) {
            $this->render_error_box($table_data, $cache_ttl_minutes);
            if ($panel_id !== '' && $tab_id !== '') {
                echo '</div>';
            }
            return;
        }

        $resolved_focus_team = $focus_team_name !== '' ? $focus_team_name : $fallback_focus_team;
        $this->render_table($table_data, $resolved_focus_team, $cache_ttl_minutes);

        if ($panel_id !== '' && $tab_id !== '') {
            echo '</div>';
        }
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

    private function format_team_display_name(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/\s*&\s*/u', ' & ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);
        $name = preg_replace('/\s+(FC|AFC|CF)$/iu', '', (string) $name);
        $name = preg_replace('/^AFC\s+/iu', '', (string) $name);
        $name = trim((string) $name);

        $name_map = [
            'Brighton & Hove Albion' => 'Brighton',
            'Newcastle United' => 'Newcastle',
            'West Ham United' => 'West Ham',
            'Wolverhampton Wanderers' => 'Wolves',
            'Tottenham Hotspur' => 'Tottenham',
            'Leeds United' => 'Leeds',
        ];
        if (isset($name_map[$name])) {
            return $name_map[$name];
        }

        return trim((string) $name);
    }

    private function sanitize_competition_key(string $competition_key): string
    {
        $competition_key = strtolower(trim($competition_key));

        return in_array($competition_key, ['pl', 'wsl', 'all'], true) ? $competition_key : 'pl';
    }

    private function get_competition_title(string $competition_key): string
    {
        if ($competition_key === 'wsl') {
            return __('Women\'s Super League Table', 'premier-league-table');
        }

        return __('Premier League Table', 'premier-league-table');
    }

    private function get_shortcode_title(string $competition_key): string
    {
        if ($competition_key === 'all') {
            return __('Club Tables', 'premier-league-table');
        }

        return $this->get_competition_title($competition_key);
    }

    private function get_standings_results(
        string $competition_key,
        array $settings,
        int $cache_ttl_minutes,
        array $focus_team_context,
        string $favorite_team
    ): array {
        $competition_keys = $competition_key === 'all' ? ['pl', 'wsl'] : [$competition_key];
        $results = [];

        foreach ($competition_keys as $current_competition_key) {
            $results[] = [
                'competition_key' => $current_competition_key,
                'tab_label' => strtoupper($current_competition_key),
                'table_data' => $this->standings_service->get_standings(
                    $current_competition_key,
                    [
                        'api_key' => (string) ($settings['api_key'] ?? ''),
                        'cache_ttl_seconds' => $cache_ttl_minutes * MINUTE_IN_SECONDS,
                    ]
                ),
                'focus_team_name' => $this->standings_service->get_focus_team_name_for_competition(
                    $focus_team_context,
                    $current_competition_key
                ),
                'fallback_focus_team' => $favorite_team,
            ];
        }

        return $results;
    }
}
