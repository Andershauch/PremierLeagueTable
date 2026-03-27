=== Premier League Table Embed ===
Contributors: HansenDjurhuus, White Hart Danes, Andershauch
Tags: football, premier league, table, standings, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed a live Premier League standings table with favorite-team highlight, a legacy Spurs-style frontend preset, and safe custom appearance controls.

== Description ==

Premier League Table Embed adds a shortcode that renders a live Premier League table on your WordPress site.

Features:
- Shortcode: `[pl_table]`
- Live standings from football-data.org
- Favorite team highlight
- Cache TTL settings and fallback error handling
- Responsive table behavior on desktop and mobile
- Admin-only settings page for API/configuration changes
- Masked API key workflow that keeps the stored key unless you explicitly replace or clear it
- Appearance presets with a live admin preview
- Safe custom styling controls for font family, font size, density, grid, header, and focus-row colors
- Whitelist validation for favorite team selection and appearance options
- Transient-based cache with a short lock to reduce duplicate upstream API requests
- Legacy frontend skin tuned to match the original Spurs table more closely

Note:
- You must add a valid football-data.org API key in plugin settings.
- Register for your own API key at `https://www.football-data.org/client/register`
- Football-data quickstart docs: `https://www.football-data.org/documentation/quickstart`

Security and operations:
- Plugin settings are registered through the WordPress Settings API and intended for users with `manage_options`.
- Frontend output is escaped before rendering.
- Favorite team values are restricted to trusted dropdown options.
- Standings responses are cached in transients, and the cache is flushed when settings are updated.
- The plugin expects source files and frontend assets to be stored as UTF-8 without BOM.
- Keep API credentials out of public repositories.
- Frontend output includes the required Football-Data attribution.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/premier-league-table`.
2. Activate the plugin through the `Plugins` menu in WordPress.
3. Go to `Settings -> Premier League Table`.
4. Add your API key and preferred settings.
5. Add shortcode `[pl_table]` to any page or post.

== Frequently Asked Questions ==

= Which API is used? =

This plugin uses football-data.org (`/v4/competitions/PL/standings`).

= How do I change cache behavior? =

Use `Cache lifetime (minutes)` in plugin settings.

= Why is no table shown? =

Check:
- API key is set and valid
- Cache has refreshed
- Site can reach football-data.org

= How is the API key handled? =

The API key is stored in plugin settings, masked in the admin UI, and not printed back into the field after save. Leave the field blank to keep the existing key, or use the clear checkbox to remove it.

= What is validated before settings are saved? =

The plugin sanitizes API key and design inputs, restricts `favorite_team` to allowed dropdown values, validates appearance presets and font choices against whitelists, and falls back to safe color pairs if header or focus-row contrast becomes unreadable.

= How do appearance presets work? =

`Legacy` keeps the released Spurs-style table unchanged. `Custom` unlocks a controlled set of design tokens in settings:
- whitelisted font families
- font size
- row density
- text, grid, header, and focus-row colors
- optional zebra rows

The admin page includes a live preview, and the plugin keeps the frontend table structure locked so appearance changes do not break the layout.

= Does shortcode focus override plugin settings? =

Yes. If you use `[pl_table focus_team="Tottenham"]` or `[pl_table favorite_team="Tottenham"]`, that explicit shortcode value takes priority over the saved plugin setting.

= Where do I get an API key? =

Create your own account at `https://www.football-data.org/client/register` and review the API quickstart at `https://www.football-data.org/documentation/quickstart`.

== Changelog ==

= 1.1.0 =
- Added a safe appearance preset system with `Legacy` and `Custom` modes.
- Added a live admin preview for font and color changes.
- Reintroduced validated custom styling controls without reopening arbitrary frontend CSS overrides.

= 1.0.9 =
- Translated the public settings page to English.
- Added football-data.org registration/help links in admin.
- Added visible Football-Data attribution to the frontend meta line.

= 1.0.8 =
- Fixed favorite-team saving so canonical team names persist correctly in plugin settings, even when API labels vary.

= 1.0.4 =
- Refined the legacy-style table presentation with closer typography, color balance, compact sizing, and team-name display rules.

= 1.0.5 =
- Locked the frontend standings table to a fixed legacy skin so saved design settings no longer override the old-table look.

= 1.0.6 =
- Reworked the legacy skin to follow the old Spurs table structure more closely by inheriting theme typography and simplifying the table layout.

= 1.0.7 =
- Restored reliable focus-row highlighting with shortcode support for an explicit focus team and refined the legacy table centering/alignment.

= 1.0.3 =
- Updated the frontend table styling to more closely match the legacy plugin layout.
- Switched frontend CSS asset versioning to file modification time to reduce stale-cache issues after deployment.

= 1.0.2 =
- Stabilized narrow-layout rendering with fixed 470px table minimum width and horizontal scrolling.
- Prevented mobile column break-up after the club-name column.

= 1.0.1 =
- Tightened table cell spacing and numeric column width for denser, centered stats presentation.

= 1.0 =
- Production release with refined responsive table layout and compact team-column behavior.
- Improved visual parity with design reference (title scale, spacing, points weight).
- Added compact display aliases for long club names on constrained widths.

= 0.6.1 =
- Improved responsive table scaling for very narrow viewports.
- Added sticky right points column on narrow screens.
- Improved admin settings UI styling consistency.
- Improved API key UX: masked input, keep existing key on blank save, explicit clear option.

= 0.6.0 =
- Added full live standings integration with caching.
- Added favorite team selection and improved match handling.
- Added extensive style controls in settings.
- Added responsive behavior improvements for very narrow viewports.
- Added hardening improvements (sanitization, cache lock, QA checklist).

== Upgrade Notice ==

= 1.1.0 =
Recommended update if you want safe custom font/color controls with a live admin preview while keeping the legacy table layout stable.

= 1.0.9 =
Recommended release-prep update for English admin settings and Football-Data attribution/compliance guidance.

= 1.0.4 =
Recommended update for a closer visual match to the legacy table widget.

= 1.0.5 =
Recommended update when you want the frontend table to consistently use the legacy White Hart Danes look.

= 1.0.6 =
Recommended update for a closer match to the original Spurs table markup and typography.

= 1.0.7 =
Recommended update if you want stable focus-row highlighting and tighter legacy-table alignment.

= 1.0.8 =
Recommended update if your saved favorite team did not persist correctly in plugin settings.

= 1.0.3 =
Recommended update for legacy-style visual parity and more reliable CSS refresh after plugin replacement.

= 1.0.2 =
Recommended update for stable narrow-screen table rendering and preserved column structure.

= 1.0.1 =
- Tightened table cell spacing and numeric column width for denser, centered stats presentation.

= 1.0 =
Recommended production release with responsive and layout parity improvements.

= 0.6.1 =
Recommended maintenance release with responsive and settings UX improvements.

= 0.6.0 =
Initial stable milestone release for local QA and pre-distribution testing.
