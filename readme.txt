=== Premier League Table Embed ===
Contributors: HansenDjurhuus, White Hart Danes, Andershauch
Tags: football, premier league, table, standings, shortcode
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Embed Premier League and Women's Super League table widgets with one shared focus-team concept, a dual next-match module, a legacy Spurs-style frontend preset, and safe custom appearance controls.

== Description ==

Premier League Table Embed adds shortcodes that render live football tables and upcoming-match cards on your WordPress site.

Features:
- Shortcodes:
  - `[pl_table]`
  - `[pl_next_match]`
- Premier League standings from football-data.org
- Women's Super League standings from WSL Football's own official feed, with automatic fallback to TheSportsDB if that feed is ever unavailable
- WSL standings derived from season fixtures plus team metadata on the fallback path, to avoid partial provider tables
- Combined PL/WSL table rendering with tabs
- Side-by-side next-match cards for PL and WSL
- Favorite team highlight
- Cache TTL settings and fallback error handling
- Responsive table behavior on desktop and mobile
- Admin-only settings page for API/configuration changes
- Masked API key workflow that keeps the stored key unless you explicitly replace or clear it
- Appearance presets with a live admin preview
- Safe custom styling controls for font family, font size, density, grid, header, and focus-row colors
- Separate custom font-family and font-weight controls for regular team names and the highlighted focus team
- Separate custom font-family/font-weight controls for the table header plus custom zebra-row background and text colors
- Grouped appearance settings with collapsible sections for easier editing
- Appearance reset plus preset export/import tools
- Whitelist validation for favorite team selection and appearance options
- Transient-based cache with a short lock to reduce duplicate upstream API requests
- Season-aware standings cache that automatically refreshes after the known season end date
- Legacy frontend skin tuned to match the original Spurs table more closely
- One shared focus-team concept that is intended to map a men\'s club and its women\'s equivalent together

Project status:
- The current stable packaged baseline is now `2.2.0`.
- The current working branch extends the plugin toward a hybrid PL + WSL setup.
- Premier League remains on `football-data.org`.
- Women's Super League now uses WSL Football's own official feed as the primary source, with `TheSportsDB` kept as an automatic fallback.
- The WSL standings path is designed to stay dynamic as the league size changes, including the planned move from 12 to 14 clubs in `2026/27`.
- WSL next-match data can legitimately be empty between seasons even when the integration is otherwise working.
- If work resumes later, read `roadmap.md` and `docs/project-handover.md` first.

Note:
- You must add a valid football-data.org API key in plugin settings for Premier League data.
- Register for your own API key at `https://www.football-data.org/client/register`
- Football-data quickstart docs: `https://www.football-data.org/documentation/quickstart`
- WSL data uses WSL Football's own public feed, with no API key required; `TheSportsDB`'s public free API endpoints are used only if that feed is unavailable.

Security and operations:
- Plugin settings are registered through the WordPress Settings API and intended for users with `manage_options`.
- Frontend output is escaped before rendering.
- Favorite team values are restricted to trusted dropdown options.
- Standings responses are cached in transients, include provider season metadata, and are flushed when settings are updated.
- After the provider-reported season end date, the plugin periodically checks whether football-data.org has switched to the next current season.
- WSL data currently depends on a second provider, so team naming and fixture availability can differ from Premier League behavior.
- The plugin expects source files and frontend assets to be stored as UTF-8 without BOM.
- Keep API credentials out of public repositories.
- Frontend output includes the required Football-Data attribution.

Maintenance handoff:
- Current stable zip: `.release/premier-league-table-2.2.0-wp.zip`
- Recommended local test stack: WordPress in `Local` on Windows
- Preferred hybrid QA runner: `.\scripts\run-hybrid-qa.ps1`
- Before changing provider logic again, verify:
  - current-season standings access
  - full-table access on the chosen plan
  - competition coverage
  - rate limits and attribution requirements
- If a new provider is introduced later, treat that as an architectural change and update README, roadmap, changelog, and QA notes in the same pass.

== Installation ==

This plugin is distributed from GitHub, not from the WordPress.org plugin directory.

1. Download `premier-league-table.zip` from the latest release: https://github.com/Andershauch/PremierLeagueTable/releases/latest
2. In WordPress, go to `Plugins -> Add New -> Upload Plugin` and upload that zip.
3. Activate the plugin through the `Plugins` menu in WordPress.
4. Go to `Settings -> Premier League Table`.
5. Add your API key and preferred settings.
6. Add shortcode `[pl_table]`, `[pl_table competition="all"]`, or `[pl_next_match]` to any page or post.

Always use the `premier-league-table.zip` asset attached to a release. GitHub's own "Source code (zip)" download has a differently-named top-level folder; the plugin can repair that during an automatic update, but a manual upload of it would install under the wrong folder name.

== Updates ==

Once installed, the plugin checks its own GitHub releases for new versions and reports them through the normal WordPress update screens — `Dashboard -> Updates` and the `Plugins` page — so updating is a one-click operation like any other plugin.

- The release check is cached for 12 hours, so a new release can take up to that long to appear. `Dashboard -> Updates -> Check again` does not clear this cache; if you need it immediately, deactivate and reactivate the plugin or wait out the cache.
- Only full published releases are offered. Drafts and pre-releases are deliberately ignored.
- No token or account is needed: the check is an unauthenticated call to the public GitHub API.
- If GitHub is unreachable or rate-limited, the check fails quietly and the site keeps running the installed version.

== Frequently Asked Questions ==

= Which API is used? =

This plugin currently uses:
- `football-data.org` for Premier League standings and Premier League next-match data
- WSL Football's own official feed for Women's Super League standings and next-match data, with `TheSportsDB` as an automatic fallback if that feed is ever unavailable

= How do I change cache behavior? =

Use `Cache lifetime (minutes)` in plugin settings.

= Why is no table shown? =

Check:
- API key is set and valid
- Cache has refreshed
- Site can reach football-data.org

= Which shortcode should I use for both leagues? =

Use:
- `[pl_table]` for the default Premier League output
- `[pl_table competition="wsl"]` for Women's Super League only
- `[pl_table competition="all"]` for the combined PL + WSL tab view

= Why is the WSL next-match card empty? =

The WSL card can be empty between seasons if the next Women's Super League fixture has not yet been published by either WSL Football's feed or the TheSportsDB fallback. This can be normal offseason behavior rather than a plugin failure.

= Why does the table show dashes instead of positions? =

Because the season has not started. Before the first matchday every club is genuinely tied on nothing, and the upstream feed reports them all as position 1. Rendering that as-is would show twenty clubs all ranked first, so the table instead lists the clubs alphabetically, replaces the position numbers with a dash, and shows a short note with the first matchday date. Real positions appear as soon as the first matches have been played.

= Why does a next-match card say "tidspunkt bekræftes senere"? =

The WSL feed publishes fixtures before broadcasters have settled the exact kickoff time. Those fixtures carry a placeholder time plus a flag saying it is not final, so the card shows the confirmed date and states that the time is still to be confirmed, rather than presenting a placeholder hour as if it were fixed.

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

= Can I move a preset between sites? =

Yes. The settings page now includes:
- a reset button that restores safe legacy appearance defaults
- preset export to JSON
- preset import from JSON

Import/export affects appearance settings only. API key, focus team, and cache settings remain outside the preset file.

= Does shortcode focus override plugin settings? =

Yes. If you use `[pl_table focus_team="Tottenham"]` or `[pl_table favorite_team="Tottenham"]`, that explicit shortcode value takes priority over the saved plugin setting.

= How does one focus team work across PL and WSL? =

The plugin is being extended toward one shared club identity. In practice, a saved club such as `Tottenham Hotspur` is intended to map to the men's side for Premier League data and the women's side for WSL data when that mapping is supported.

= Where do I get an API key? =

Create your own account at `https://www.football-data.org/client/register` and review the API quickstart at `https://www.football-data.org/documentation/quickstart`.

== Changelog ==

= 2.3.0 =
- Added GitHub-based plugin updates: the plugin now reads its own GitHub releases and offers new versions through the normal WordPress update screens.
- Added a release workflow that builds and publishes the installable zip automatically when a version tag is pushed.
- Fixed the Premier League table showing every club as position 1 before the season starts; preseason tables are now listed alphabetically with dashes instead of positions, plus a note naming the first matchday.
- Added the same preseason presentation to the Women's Super League table.
- Next-match cards no longer present an unconfirmed kickoff time as final; those fixtures now show the date plus a "time to be confirmed" note.

= 2.2.0 =
- Added a new primary Women's Super League data source: WSL Football's own official feed (used by wslfootball.com itself), which returns the full official table and complete season fixtures.
- Kept `TheSportsDB` as an automatic fallback if the new feed ever errors or changes shape, so WSL standings and next-match keep working with no site-visible failure.
- WSL preseason/live detection now uses the new feed's exact season dates instead of an estimated month-based cutoff.

= 2.1.1 =
- Fixed the combined table tab hover state so theme button hover colors no longer bleed into the PL/WSL tabs.
- Fixed the admin focus-team dropdown so the saved team remains visible as the selected option even when dynamic provider-based options are loaded.

= 2.1.0 =
- Added hybrid Premier League and Women's Super League support with combined tab rendering in `[pl_table competition="all"]`.
- Added WSL standings via TheSportsDB with derived tables, preseason handling, fallback rosters, and season-aware team-count hardening.
- Added side-by-side Premier League and WSL cards in `[pl_next_match]` with stronger WSL alias lookup and clearer offseason empty states.

= 2.0.1 =
- Added season-aware standings caching so the plugin can automatically switch when football-data.org moves Premier League to a new current season.
- Versioned next-match caches by active season to reduce stale schedule data after season changes.
- Updated focus-team option cache handling for refreshed season tables.

= 2.0.6 =
- Updated the next-match empty-state message to use the selected team name and proper Danish characters.

= 2.0.5 =
- Clarified the empty next-match message for offseason periods and cases where the next Premier League fixtures are not yet published in the API.

= 2.0.4 =
- Fixed the Premier League next-match request after the competition feed rejected a one-sided `dateFrom` filter.

= 2.0.3 =
- Reworked next-match fetching to use the Premier League competition matches feed instead of the more restricted team matches endpoint.
- Filters the scheduled Premier League fixtures locally to the configured focus team before rendering the next match.

= 2.0.2 =
- Fixed next-match fetching to request only Premier League fixtures again, which reduces 403 errors on football-data.org keys without broader competition access.
- Improved the 403 next-match error copy to explain the access issue more clearly.

= 2.0.0 =
- Added new `[pl_next_match]` shortcode for the upcoming focus-team match card with kickoff time and club logos.
- Added separate `PL Next Match` settings page with independent design controls and timezone/date-format configuration.
- Reused the existing global focus-team selection (single source of truth) for both table and next-match module.
- Updated next-match provider logic to fetch the first scheduled upcoming match without forcing a Premier League-only filter.

= 1.2.0 =
- Grouped the appearance controls into clearer collapsible sections on the settings page.
- Added reset-to-legacy appearance, preset export, and preset import tools.
- Added separate header typography controls and customizable zebra-row background/text colors.
- Kept the live preview in sync with the expanded appearance controls.

= 1.1.3 =
- Added separate font-family and font-weight controls for the table header in custom mode.
- Added customizable alternate-row background and text colors for zebra rows.
- Updated the live preview so header typography and zebra-row colors are visible immediately.

= 1.1.2 =
- Added separate font-family and font-weight controls for regular team names and the highlighted focus team.
- Updated the live admin preview so these team-name typography controls are reflected immediately.

= 1.1.1 =
- Hardened the custom preset CSS so custom fonts and colors override theme table styles more reliably.
- Made the live admin preview reflect the real 480px frontend width more closely and gave the preview area more room.

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

= 2.2.0 =
Recommended update for more reliable and accurate Women's Super League standings and next-match data, now sourced from WSL Football's own official feed with automatic fallback to the previous provider.

= 2.1.1 =
Recommended patch update if the PL/WSL tab hover color was being overridden by your theme or if the selected focus team was not visible in the admin dropdown.

= 2.1.0 =
Recommended feature update if you want the new PL + WSL hybrid table flow, dual next-match cards, and the hardened WSL data layer.

= 2.0.1 =
Recommended maintenance update so Premier League data refreshes cleanly after each provider-reported season end.

= 2.0.6 =
Recommended maintenance update if you want the next-match empty-state to show the selected club name instead of `focus team`.

= 2.0.5 =
Recommended maintenance update if `[pl_next_match]` now returns no upcoming fixture and you need a clearer explanation during offseason periods.

= 2.0.4 =
Recommended hotfix update if `[pl_next_match]` started returning a 400 error about `dateFrom` and `dateTo`.

= 2.0.3 =
Recommended maintenance update if your football-data.org key can read Premier League data but `[pl_next_match]` still fails on the team matches endpoint.

= 2.0.2 =
Recommended maintenance update if the `[pl_next_match]` shortcode started failing with football-data.org 403 errors.

= 2.0.0 =
First major release that includes the new `PL Next Match` module with independent styling settings and upcoming-match rendering.

= 1.2.0 =
Recommended update if you want the expanded appearance system to stay manageable with grouped settings, reusable preset files, and a one-click reset back to the safe legacy look.

= 1.1.3 =
Recommended update if you want separate typography control for the table header and customizable zebra-row colors in both frontend output and the live admin preview.

= 1.1.2 =
Recommended update if you want separate typography control for regular team names and the highlighted focus team in both frontend output and the live admin preview.

= 1.1.1 =
Recommended update if custom preset fonts or colors were not applying reliably, or if the live preview felt too cramped compared with the real frontend width.

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
