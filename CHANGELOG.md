# Changelog

## Unreleased

## 2.3.0 - 2026-08-19
- Added GitHub-based plugin updates (`includes/class-github-updater.php`). The plugin reads the newest published release from its own repository and offers it through the normal WordPress update screens, so installed sites update in one click instead of by manual zip upload. Drafts and pre-releases are ignored, the check is cached for 12 hours, and a failed or rate-limited check degrades quietly to "no update available".
- Added `.github/workflows/release.yml`, which builds `premier-league-table.zip` and publishes it as a GitHub Release when a `v*` tag is pushed. The workflow refuses to release if the tag, the plugin header, `PLT_VERSION`, and the readme `Stable tag` do not all agree, so a version mismatch cannot reach a site as a silent no-op update.
- Fixed the Premier League table rendering every club as position 1 before the season starts. `football-data.org` legitimately reports all clubs tied on position 1 during preseason; the table now detects that no matches have been played, lists the clubs alphabetically, shows a dash instead of a position, and adds a note naming the first matchday date.
- Applied the same preseason presentation to the Women's Super League table, which had the same underlying problem in a milder form (a sequential-looking order that was really just alphabetical).
- Fixed next-match cards presenting an unconfirmed kickoff time as if it were final. The WSL feed publishes fixtures with a placeholder time plus `isUnknownKickOffTime`; those now render as the date plus "tidspunkt bekræftes senere".
- Release and test scripts no longer hardcode one machine's Local by Flywheel paths. `scripts/lib/local-site.ps1` resolves the target WordPress install from an explicit parameter, `PLT_LOCAL_PLUGIN_PATH`, `PLT_LOCAL_SITE_NAME`, or auto-discovery, and fails with an actionable message instead of mirroring the plugin into a folder no WordPress install reads.
- Added `scripts/publish-github-release.ps1` to validate versions and push the release tag in one step.

## 2.2.0 - 2026-07-30
- Added a new primary WSL standings and next-match source: the JSON feed behind wslfootball.com's own site (`api-sdp.wslfootball.com`, Opta-backed, unauthenticated, publicly CORS-open). It returns the full official table (all 12/14 clubs, exact scores) and complete season fixtures, replacing the previous derived-table approach that undercounted matches.
- Kept `TheSportsDB` as an automatic fallback: if the new feed errors or its shape changes, WSL standings and next-match both fall through to the existing `TheSportsDB` path with no site-visible failure.
- WSL season detection now uses the new feed's exact season start/end dates instead of a month-based heuristic, so preseason/live mode switching is precise rather than guessed.

## 2.1.1 - 2026-06-25
- Fixed the combined PL/WSL tabs so plugin hover styling overrides theme-level red button hover states.
- Fixed the admin focus-team dropdown so the saved selection remains visible even when dynamic provider-backed team options are loaded.

## 2.1.0 - 2026-06-25
- Added a hybrid standings architecture so Premier League data can continue to use `football-data.org` while Women\'s Super League standings can be sourced from `TheSportsDB`.
- Added provider-aware club mapping scaffolding so one saved focus team can resolve differently for the men\'s and women\'s datasets.
- Extended `[pl_table]` to support:
  - Premier League only
  - Women\'s Super League only
  - combined PL + WSL rendering
- Added a first frontend tabs experience for combined PL/WSL table rendering.
- Extended `[pl_next_match]` to render separate Premier League and WSL cards side by side.
- Documented that WSL next-match data may be empty during offseason periods because the provider does not always publish the next fixture immediately between seasons.
- Replaced the fragile WSL `lookuptable.php` dependency with a derived standings table built from season events plus team metadata, so the plugin can show the full league instead of the provider\'s partial 5-row response.
- Kept the WSL standings logic dynamic so league-size changes, including the planned move from 12 to 14 clubs in `2026/27`, do not require a frontend table rewrite.
- Switched the default WSL season into preseason mode from June onward, so the widget prefers the upcoming season and shows a 0-table before the first fixtures are played instead of misleading historical fragments.
- Added an internal WSL roster fallback so incomplete `search_all_teams.php` responses do not shrink the preseason table when TheSportsDB omits clubs.
- Added explicit WSL data modes (`preseason` vs `live`) plus a verification script so provider gaps and season-state behavior can be checked outside WordPress.
- Hardened club mapping so shared PL/WSL clubs resolve through a structured catalog, while unsupported WSL pairs fall back safely instead of degrading into slug-like names.
- Hardened WSL next-match lookup so empty preseason responses no longer surface as invalid API errors, and team discovery now tries multiple aliases plus a league-roster fallback.

## 2.0.6 - 2026-06-14
- Updated the Premier League next-match empty-state message to show the configured team name instead of the generic `focus team` label.
- Restored proper Danish characters in that next-match empty-state copy.

## 2.0.5 - 2026-06-14
- Clarified the next-match empty-state message so offseason periods and unpublished future Premier League fixtures are explained more accurately.

## 2.0.4 - 2026-06-14
- Fixed Premier League next-match requests after the competition endpoint rejected `dateFrom` without `dateTo`.
- Next-match fetching now relies on the scheduled Premier League feed without the invalid date filter pair.

## 2.0.3 - 2026-06-14
- Reworked next-match fetching to use the Premier League competition matches endpoint instead of the restricted team matches endpoint.
- Filters the scheduled Premier League fixture list locally to the configured focus team before rendering the next match.
- Updated next-match error copy to reflect Premier League-scoped match fetching.

## 2.0.2 - 2026-06-14
- Fixed next-match requests to query only Premier League fixtures again by passing the official `competitions=PL` filter to the football-data team matches endpoint.
- Improved the next-match 403 error message so restricted football-data.org keys fail with a clearer explanation.

## 2.0.1 - 2026-06-14
- Added season-aware standings caching based on football-data.org season metadata.
- Automatically refreshes cached standings after the known season end date so the plugin can switch to the new current season when the provider does.
- Versioned next-match caches by active season to avoid stale team schedules after a Premier League season change.
- Updated settings cache handling so focus-team options can refresh from the new season table.

## 2.0.0 - 2026-05-20
- Added `PL Next Match` module with new `[pl_next_match]` shortcode for upcoming focus-team match rendering.
- Added separate next-match admin settings page with independent design tokens plus timezone/date-format controls.
- Kept focus-team selection centralized in existing table settings and reused it in the next-match module.
- Updated next-match provider query to select the first upcoming scheduled match without a Premier League-only competition filter.

## 1.2.0 - 2026-03-28
- Grouped the appearance controls into clearer collapsible sections on the settings page.
- Added reset-to-legacy appearance plus preset export/import tools.
- Added separate header typography controls and customizable zebra-row background/text colors.
- Kept the live admin preview in sync with the expanded appearance controls.

## 1.1.3 - 2026-03-28
- Added separate font-family and font-weight controls for the table header in custom mode.
- Added customizable zebra-row background and text colors.
- Updated the live admin preview so header typography and zebra-row colors are visible immediately.

## 1.1.2 - 2026-03-28
- Added separate font-family and font-weight controls for regular team names and the highlighted focus team.
- Updated the live admin preview so team-name typography changes are visible before saving.

## 1.1.1 - 2026-03-28
- Hardened the custom preset CSS so custom fonts and colors override theme table styles more reliably.
- Widened the admin preview area and aligned the live preview to the real 480px frontend width.

## 1.1.0 - 2026-03-27
- Added a safe appearance preset system with `Legacy` and `Custom` modes.
- Added a live admin preview so font and color changes can be reviewed before saving.
- Reintroduced validated custom font/color controls without reopening arbitrary frontend style injection.

## 1.0.9 - 2026-03-27
- Translated the public settings page to English and added football-data.org help links in admin.
- Added visible Football-Data attribution to the frontend meta line for release-readiness.

## 1.0.8 - 2026-03-27
- Fixed favorite-team persistence in settings by canonicalizing API-derived team labels before save and display.
- Clarified the precedence of shortcode focus-team overrides versus saved plugin settings.

## 1.0.7 - 2026-03-27
- Added explicit shortcode support for a focus team so the highlighted row no longer depends only on saved settings.
- Refined table centering and club-cell alignment to better match the legacy widget.

## 1.0.6 - 2026-03-27
- Reworked the legacy frontend skin to behave more like the original Spurs table: inherited theme font, simpler table layout, inline crest alignment, and a plain white header row.
- Reduced the custom CSS opinions that were making the replacement widget look more styled than the legacy table.

## 1.0.5 - 2026-03-27
- Locked the frontend standings table to a fixed legacy skin instead of inheriting saved frontend color and font overrides.
- Removed inline frontend style-variable output so the legacy CSS can render consistently across sites.

## 1.0.4 - 2026-03-27
- Refined the legacy-style frontend CSS with tighter typography, grid color, spacing, and badge sizing.
- Adjusted displayed club names to stay closer to the legacy widget labels on narrow widths.

## 1.0.3 - 2026-03-27
- Updated the frontend table CSS to match the legacy plugin layout more closely, including compact fixed columns and the legacy highlight treatment.
- Switched frontend stylesheet versioning to `filemtime()` so CSS changes invalidate cached assets during deployment.

## 1.0.2 - 2026-02-22
- Stabilized narrow-layout table rendering with fixed 470px minimum width.
- Ensured horizontal scroll is used instead of column break-up on smaller screens.

## 1.0 - 2026-02-22
- Production release.
- Reduced title scale and tightened table spacing to better match reference layout.
- Improved narrow-screen column balance so key stats columns remain visible.
- Added compact display aliases for long club names (for constrained widths).
- Updated points styling to Apex New with weight 300.

## 0.6.1 - 2026-02-21
- Improved responsive behavior for narrow viewports without aggressive column hiding.
- Added sticky right points column so the final points column remains visible while scrolling.
- Added settings UI polish with brand-consistent admin styling.
- Improved API key settings UX:
  - masked input
  - preserve existing key when field is left blank
  - explicit checkbox to clear existing key
- Added additional table compactness tuning and column width balancing.

## 0.6.0 - 2026-02-21
- Completed Milestone 2-6 implementation baseline.
- Added football-data.org standings integration for Premier League.
- Added caching with configurable TTL and cache invalidation on settings save.
- Added favorite-team highlight with robust team name matching.
- Added dropdown selection for favorite team in admin settings.
- Added advanced style controls (colors, typography, row spacing, zebra rows).
- Added responsive layout tuning for narrow viewports and sticky right points column.
- Added accessibility improvements (caption, labels, semantic table structure).
- Added hardening improvements:
  - inline style defense-in-depth sanitization
  - API request lock to reduce duplicate calls under concurrency
- Added QA checklist documentation for Milestone 6.
