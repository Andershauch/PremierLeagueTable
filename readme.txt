=== Premier League Table Embed ===
Contributors: HansenDjurhuus, White Hart Danes, Andershauch
Tags: football, premier league, table, standings, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed a live Premier League standings table with favorite-team highlight and style controls.

== Description ==

Premier League Table Embed adds a shortcode that renders a live Premier League table on your WordPress site.

Features:
- Shortcode: `[pl_table]`
- Live standings from football-data.org
- Favorite team highlight
- Style controls (colors, typography, spacing, compactness)
- Cache TTL settings and fallback error handling
- Responsive table behavior on desktop and mobile
- Admin-only settings page for API/configuration changes
- Masked API key workflow that keeps the stored key unless you explicitly replace or clear it
- Sanitized style values and whitelist validation for favorite team selection
- Transient-based cache with a short lock to reduce duplicate upstream API requests

Note:
- You must add a valid football-data.org API key in plugin settings.

Security and operations:
- Plugin settings are registered through the WordPress Settings API and intended for users with `manage_options`.
- Frontend output is escaped before rendering.
- Favorite team values are restricted to trusted dropdown options.
- Standings responses are cached in transients, and the cache is flushed when settings are updated.
- The plugin expects source files and frontend assets to be stored as UTF-8 without BOM.

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

Use `Cache levetid (minutter)` in plugin settings.

= Why is no table shown? =

Check:
- API key is set and valid
- Cache has refreshed
- Site can reach football-data.org

= How is the API key handled? =

The API key is stored in plugin settings, masked in the admin UI, and not printed back into the field after save. Leave the field blank to keep the existing key, or use the clear checkbox to remove it.

= What is validated before settings are saved? =

The plugin sanitizes API key and design inputs, restricts `favorite_team` to allowed dropdown values, and constrains cache/style numeric settings to known safe ranges.

== Changelog ==

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

