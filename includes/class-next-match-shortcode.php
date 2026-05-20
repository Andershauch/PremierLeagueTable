<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Next_Match_Shortcode
{
    private PLT_Settings $settings;
    private PLT_Next_Match_Settings $next_match_settings;
    private PLT_Api_Client $api_client;

    public function __construct(
        PLT_Settings $settings,
        PLT_Next_Match_Settings $next_match_settings,
        PLT_Api_Client $api_client
    ) {
        $this->settings = $settings;
        $this->next_match_settings = $next_match_settings;
        $this->api_client = $api_client;
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

        $team_id = $this->resolve_focus_team_id($favorite_team, $api_key, $cache_ttl_minutes);
        $match = $team_id > 0
            ? $this->api_client->get_next_premier_league_match($team_id, $api_key, $cache_ttl_minutes * MINUTE_IN_SECONDS)
            : new WP_Error('plt_missing_focus_team', __('Focus team is not configured.', 'premier-league-table'));

        $style = $this->next_match_settings->get_theme_style_attribute($module_settings);
        ob_start();
        ?>
        <section class="plt-next-match" style="<?php echo esc_attr($style); ?>">
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
        </section>
        <?php
        return (string) ob_get_clean();
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
