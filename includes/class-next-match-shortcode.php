<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Next_Match_Shortcode
{
    private PLT_Settings $settings;
    private PLT_Next_Match_Settings $next_match_settings;
    private PLT_Api_Client $api_client;
    private PLT_Standings_Service $standings_service;
    private PLT_TheSportsDB_Client $thesportsdb_client;
    private PLT_WPLL_Client $wpll_client;

    public function __construct(
        PLT_Settings $settings,
        PLT_Next_Match_Settings $next_match_settings,
        PLT_Api_Client $api_client,
        PLT_Standings_Service $standings_service,
        PLT_TheSportsDB_Client $thesportsdb_client,
        PLT_WPLL_Client $wpll_client
    ) {
        $this->settings = $settings;
        $this->next_match_settings = $next_match_settings;
        $this->api_client = $api_client;
        $this->standings_service = $standings_service;
        $this->thesportsdb_client = $thesportsdb_client;
        $this->wpll_client = $wpll_client;
    }

    public function register_hooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_shortcode('pl_next_match', [$this, 'render_shortcode']);
    }

    public function register_assets(): void
    {
        $style_version = file_exists(PLT_PLUGIN_DIR . 'assets/css/next-match.css')
            ? (string) filemtime(PLT_PLUGIN_DIR . 'assets/css/next-match.css')
            : '1.3.0';

        wp_register_style(
            'plt-next-match',
            PLT_PLUGIN_URL . 'assets/css/next-match.css',
            [],
            $style_version
        );
    }

    public function render_shortcode($atts = []): string
    {
        unset($atts);
        wp_enqueue_style('plt-next-match');

        $global_settings = $this->settings->get_settings();
        $module_settings = $this->next_match_settings->get_settings();
        $favorite_team = isset($global_settings['favorite_team']) ? (string) $global_settings['favorite_team'] : '';
        $api_key = isset($global_settings['api_key']) ? (string) $global_settings['api_key'] : '';
        $cache_ttl_minutes = isset($module_settings['cache_ttl_minutes']) ? absint($module_settings['cache_ttl_minutes']) : 10;
        if (! in_array($cache_ttl_minutes, [1, 5, 10, 15, 30], true)) {
            $cache_ttl_minutes = 10;
        }

        $focus_team_context = $this->standings_service->resolve_focus_team_context($favorite_team);
        $pl_focus_team = isset($focus_team_context['pl_name']) ? (string) $focus_team_context['pl_name'] : $favorite_team;
        $wsl_focus_team = isset($focus_team_context['wsl_name']) ? (string) $focus_team_context['wsl_name'] : '';

        $team_id = $this->resolve_focus_team_id($pl_focus_team, $api_key, $cache_ttl_minutes);
        $pl_match = $team_id > 0
            ? $this->api_client->get_next_premier_league_match($team_id, $api_key, $cache_ttl_minutes * MINUTE_IN_SECONDS, $pl_focus_team)
            : new WP_Error('plt_missing_focus_team', __('Focus team is not configured.', 'premier-league-table'));
        $wsl_match = $wsl_focus_team !== ''
            ? $this->resolve_wsl_match($pl_focus_team, $wsl_focus_team, $cache_ttl_minutes)
            : new WP_Error('plt_missing_wsl_focus_team', __('WSL focus team is not configured.', 'premier-league-table'));

        $style = $this->next_match_settings->get_theme_style_attribute($module_settings);
        ob_start();
        ?>
        <section class="plt-next-match" style="<?php echo esc_attr($style); ?>">
            <div class="plt-next-match__grid">
                <?php $this->render_match_card(__('Next Match PL', 'premier-league-table'), $pl_match, $module_settings); ?>
                <?php $this->render_match_card(__('Next Match WSL', 'premier-league-table'), $wsl_match, $module_settings); ?>
            </div>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function render_match_card(string $title, $match, array $module_settings): void
    {
        ?>
        <article class="plt-next-match__card">
            <h3 class="plt-next-match__title"><?php echo esc_html($title); ?></h3>
            <?php if (is_wp_error($match)) : ?>
                <p class="plt-next-match__error"><?php echo esc_html($match->get_error_message()); ?></p>
            <?php else : ?>
                <?php
                $home = isset($match['home_team']) && is_array($match['home_team']) ? $match['home_team'] : [];
                $away = isset($match['away_team']) && is_array($match['away_team']) ? $match['away_team'] : [];
                $kickoff = $this->format_kickoff((string) ($match['utc_date'] ?? ''), $module_settings);
                $focus_side = isset($match['focus_side']) ? (string) $match['focus_side'] : '';
                ?>
                <p class="plt-next-match__kickoff"><?php echo esc_html($kickoff); ?></p>
                <div class="plt-next-match__row">
                    <?php $this->render_team($home, $focus_side === 'home'); ?>
                    <div class="plt-next-match__vs">VS</div>
                    <?php $this->render_team($away, $focus_side === 'away'); ?>
                </div>
            <?php endif; ?>
        </article>
        <?php
    }

    private function render_team(array $team, bool $is_focus): void
    {
        $name = isset($team['name']) ? (string) $team['name'] : '';
        $crest = isset($team['crest']) ? (string) $team['crest'] : '';
        ?>
        <div class="plt-next-match__team<?php echo $is_focus ? ' is-focus' : ''; ?>">
            <?php if ($crest !== '') : ?>
                <img class="plt-next-match__logo" src="<?php echo esc_url($crest); ?>" alt="" loading="lazy" decoding="async" />
            <?php else : ?>
                <span class="plt-next-match__logo plt-next-match__logo--fallback" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="plt-next-match__name"><?php echo esc_html($name); ?></span>
        </div>
        <?php
    }

    /**
     * @return array|WP_Error
     */
    private function resolve_wsl_match(string $pl_focus_team, string $wsl_focus_team, int $cache_ttl_minutes)
    {
        $cache_ttl_seconds = $cache_ttl_minutes * MINUTE_IN_SECONDS;

        $wpll_match = $this->wpll_client->get_next_wsl_match($pl_focus_team, $wsl_focus_team, $cache_ttl_seconds);
        if (! is_wp_error($wpll_match)) {
            return $wpll_match;
        }

        return $this->thesportsdb_client->get_next_wsl_match($wsl_focus_team, $cache_ttl_seconds);
    }

    private function resolve_focus_team_id(string $favorite_team, string $api_key, int $cache_ttl_minutes): int
    {
        if (trim($favorite_team) === '' || trim($api_key) === '') {
            return 0;
        }

        $table = $this->api_client->get_premier_league_table($api_key, max(60, $cache_ttl_minutes * MINUTE_IN_SECONDS));
        if (is_wp_error($table) || ! isset($table['rows']) || ! is_array($table['rows'])) {
            return 0;
        }

        $favorite = $this->normalize_team_name($favorite_team);
        foreach ($table['rows'] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = isset($row['team_name']) ? (string) $row['team_name'] : '';
            $team_id = isset($row['team_id']) ? (int) $row['team_id'] : 0;
            if ($team_id <= 0) {
                continue;
            }

            $normalized = $this->normalize_team_name($name);
            if ($normalized === $favorite || strpos($normalized, $favorite) !== false || strpos($favorite, $normalized) !== false) {
                return $team_id;
            }
        }

        return 0;
    }

    private function format_kickoff(string $utc_date, array $module_settings): string
    {
        $timestamp = strtotime($utc_date);
        if ($timestamp === false) {
            return __('Kickoff TBD', 'premier-league-table');
        }

        $timezone = isset($module_settings['timezone']) ? (string) $module_settings['timezone'] : 'Europe/Copenhagen';
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = 'Europe/Copenhagen';
        }

        $format = isset($module_settings['datetime_format']) ? trim((string) $module_settings['datetime_format']) : 'd.m.Y H:i';
        if ($format === '') {
            $format = 'd.m.Y H:i';
        }

        $date = new DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone(new DateTimeZone($timezone));
        return $date->format($format);
    }

    private function normalize_team_name(string $name): string
    {
        $name = remove_accents(strtolower(trim($name)));
        $name = preg_replace('/\b(fc|afc|cf)\b/u', ' ', $name);
        $name = preg_replace('/[^a-z0-9 ]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', (string) $name);

        return trim((string) $name);
    }
}
