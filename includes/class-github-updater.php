<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Serves plugin updates from this repository's GitHub Releases.
 *
 * The plugin is distributed from GitHub rather than wordpress.org, so WordPress
 * has no update source of its own for it. This class supplies one: it reads the
 * newest published release, and if its tag is a higher version than the running
 * plugin, hands WordPress a normal update offer. From the user's point of view
 * the plugin then updates exactly like any other one — Dashboard -> Updates, or
 * the "update now" link on the Plugins screen.
 *
 * The release asset must be a zip whose single top-level folder is the plugin
 * slug. `.github/workflows/release.yml` builds exactly that. GitHub's own
 * auto-generated source zipball is accepted as a fallback, but its root folder
 * is named after the repo and tag, so `fix_source_directory()` renames it
 * before WordPress installs it — without that, an update would silently create
 * a second, deactivated plugin folder instead of replacing this one.
 */
class PLT_GitHub_Updater
{
    private const RELEASE_CACHE_KEY = 'plt_github_release_v1';
    private const CACHE_TTL_SECONDS = 43200; // 12 hours.
    private const FAILURE_CACHE_TTL_SECONDS = 3600; // Back off for an hour after an API failure.
    private const RELEASE_ASSET_NAME = 'premier-league-table.zip';

    private string $plugin_file;
    private string $plugin_basename;
    private string $slug;
    private string $repo;
    private string $version;

    public function __construct(string $plugin_file, string $repo, string $version)
    {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->slug = dirname($this->plugin_basename);
        $this->repo = trim($repo, '/ ');
        $this->version = $version;

        if ($this->slug === '.' || $this->slug === '') {
            $this->slug = basename($plugin_file, '.php');
        }
    }

    public function register_hooks(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'inject_update']);
        add_filter('plugins_api', [$this, 'plugin_details'], 20, 3);
        add_filter('upgrader_source_selection', [$this, 'fix_source_directory'], 10, 4);
        add_action('upgrader_process_complete', [$this, 'flush_release_cache'], 10, 2);
    }

    /**
     * @param mixed $transient
     * @return mixed
     */
    public function inject_update($transient)
    {
        if (! is_object($transient)) {
            return $transient;
        }

        // WordPress calls this filter more than once per request; only the pass
        // that has already collected the installed set is worth acting on.
        if (! isset($transient->checked) || ! is_array($transient->checked)) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ($release === null) {
            return $transient;
        }

        if (! $this->is_newer_version($release['version'], $this->version)) {
            if (isset($transient->response[$this->plugin_basename])) {
                unset($transient->response[$this->plugin_basename]);
            }

            $transient->no_update[$this->plugin_basename] = $this->build_update_object($release, false);

            return $transient;
        }

        $transient->response[$this->plugin_basename] = $this->build_update_object($release, true);

        return $transient;
    }

    /**
     * Powers the "View version details" modal so the update offer is not a
     * blind one — the release notes from GitHub are shown in place of the
     * wordpress.org changelog that this plugin does not have.
     *
     * @param mixed $result
     * @param string $action
     * @param mixed $args
     * @return mixed
     */
    public function plugin_details($result, $action = '', $args = null)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        $requested_slug = is_object($args) && isset($args->slug) ? (string) $args->slug : '';
        if ($requested_slug !== $this->slug) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ($release === null) {
            return $result;
        }

        $info = new stdClass();
        $info->name = 'Premier League Table Embed';
        $info->slug = $this->slug;
        $info->version = $release['version'];
        $info->author = 'HansenDjurhuus';
        $info->homepage = 'https://github.com/' . $this->repo;
        $info->download_link = $release['package'];
        $info->trunk = $release['package'];
        $info->requires = '6.0';
        $info->requires_php = '7.4';
        $info->last_updated = $release['published_at'];
        $info->sections = [
            'description' => __('Embed live Premier League and Women\'s Super League standings and next-match cards.', 'premier-league-table'),
            'changelog' => $this->format_changelog($release),
        ];

        return $info;
    }

    /**
     * Renames the extracted folder to the plugin slug when the downloaded
     * archive was GitHub's source zipball (root folder `repo-tag`) rather than
     * the release asset built by CI.
     *
     * @param mixed $source
     * @param mixed $remote_source
     * @param mixed $upgrader
     * @param mixed $extra
     * @return mixed
     */
    public function fix_source_directory($source, $remote_source = '', $upgrader = null, $extra = [])
    {
        if (! is_string($source) || ! is_string($remote_source) || $remote_source === '') {
            return $source;
        }

        $is_our_plugin = is_array($extra)
            && isset($extra['plugin'])
            && (string) $extra['plugin'] === $this->plugin_basename;
        if (! $is_our_plugin) {
            return $source;
        }

        if (basename(untrailingslashit($source)) === $this->slug) {
            return $source;
        }

        global $wp_filesystem;
        if (! isset($wp_filesystem) || ! is_object($wp_filesystem)) {
            return $source;
        }

        $corrected = trailingslashit($remote_source) . $this->slug;
        if ($wp_filesystem->exists($corrected)) {
            $wp_filesystem->delete($corrected, true);
        }

        if (! $wp_filesystem->move($source, $corrected)) {
            return $source;
        }

        return trailingslashit($corrected);
    }

    /**
     * @param mixed $upgrader
     * @param mixed $options
     */
    public function flush_release_cache($upgrader = null, $options = []): void
    {
        unset($upgrader);

        if (! is_array($options)) {
            return;
        }

        $is_plugin_update = isset($options['type'], $options['action'])
            && $options['type'] === 'plugin'
            && $options['action'] === 'update';
        if (! $is_plugin_update) {
            return;
        }

        delete_transient(self::RELEASE_CACHE_KEY);
    }

    /**
     * @return array|null {version: string, package: string, notes: string, html_url: string, published_at: string}
     */
    private function get_latest_release(): ?array
    {
        $cached = get_transient(self::RELEASE_CACHE_KEY);
        if (is_array($cached)) {
            return isset($cached['version']) ? $cached : null;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . $this->repo . '/releases/latest',
            [
                'timeout' => 12,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            // Cache the miss so a rate-limited or offline site does not retry on
            // every admin page load.
            set_transient(self::RELEASE_CACHE_KEY, ['failed' => true], self::FAILURE_CACHE_TTL_SECONDS);
            return null;
        }

        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $release = is_array($payload) ? $this->parse_release($payload) : null;

        if ($release === null) {
            set_transient(self::RELEASE_CACHE_KEY, ['failed' => true], self::FAILURE_CACHE_TTL_SECONDS);
            return null;
        }

        set_transient(self::RELEASE_CACHE_KEY, $release, self::CACHE_TTL_SECONDS);

        return $release;
    }

    /**
     * @return array|null
     */
    private function parse_release(array $payload): ?array
    {
        if (! empty($payload['draft']) || ! empty($payload['prerelease'])) {
            return null;
        }

        $version = $this->normalize_version((string) ($payload['tag_name'] ?? ''));
        if ($version === '') {
            return null;
        }

        $package = $this->find_release_package($payload);
        if ($package === '') {
            return null;
        }

        return [
            'version' => $version,
            'package' => $package,
            'notes' => (string) ($payload['body'] ?? ''),
            'html_url' => (string) ($payload['html_url'] ?? ''),
            'published_at' => (string) ($payload['published_at'] ?? ''),
        ];
    }

    /**
     * Prefers the installable zip built by CI; falls back to GitHub's source
     * zipball, which `fix_source_directory()` then repairs.
     */
    private function find_release_package(array $payload): string
    {
        $assets = isset($payload['assets']) && is_array($payload['assets']) ? $payload['assets'] : [];
        foreach ($assets as $asset) {
            if (! is_array($asset)) {
                continue;
            }

            $name = strtolower((string) ($asset['name'] ?? ''));
            $url = (string) ($asset['browser_download_url'] ?? '');
            if ($name === self::RELEASE_ASSET_NAME && $url !== '') {
                return $url;
            }
        }

        return (string) ($payload['zipball_url'] ?? '');
    }

    private function normalize_version(string $tag): string
    {
        $tag = trim($tag);
        if ($tag === '') {
            return '';
        }

        $tag = ltrim($tag, 'vV');

        return preg_match('/^\d+(\.\d+)*([A-Za-z0-9.+-]*)$/', $tag) === 1 ? $tag : '';
    }

    private function is_newer_version(string $remote_version, string $current_version): bool
    {
        return version_compare($remote_version, $current_version, '>');
    }

    private function build_update_object(array $release, bool $is_update): stdClass
    {
        $item = new stdClass();
        $item->id = 'github.com/' . $this->repo;
        $item->slug = $this->slug;
        $item->plugin = $this->plugin_basename;
        $item->new_version = $is_update ? $release['version'] : $this->version;
        $item->url = 'https://github.com/' . $this->repo;
        $item->package = $is_update ? $release['package'] : '';
        $item->tested = '';
        $item->requires_php = '7.4';
        $item->icons = [];
        $item->banners = [];
        $item->banners_rtl = [];

        return $item;
    }

    private function format_changelog(array $release): string
    {
        $notes = trim((string) $release['notes']);
        if ($notes === '') {
            $notes = __('No release notes were published for this version.', 'premier-league-table');
        }

        $html = '<pre style="white-space:pre-wrap;">' . esc_html($notes) . '</pre>';

        if ($release['html_url'] !== '') {
            $html .= '<p><a href="' . esc_url($release['html_url']) . '" target="_blank" rel="noopener noreferrer">'
                . esc_html__('View this release on GitHub', 'premier-league-table')
                . '</a></p>';
        }

        return $html;
    }
}
