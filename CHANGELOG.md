# Changelog

## 1.4.0 - 2026-03-29
- Migrated the live standings provider from API-Football to TheSportsDB.
- Added competition-aware standings support for Premier League and Women's Super League.
- Added separate saved focus-team settings per competition and shortcode competition override support.

## 1.3.0 - 2026-03-29
- Migrated the live standings integration from football-data.org to API-Football.
- Added safer provider error handling, including a clearer message when the API-Football account is suspended.
- Updated settings help text, frontend attribution, and release documentation for the new provider.

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
- Added the original football-data.org standings integration for Premier League.
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
