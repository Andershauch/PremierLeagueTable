# Changelog

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

