# Project Handover

## Current state
- Project: `Premier League Table Embed`
- Current maintained baseline: `1.2.0`
- Current release zip: `.release/premier-league-table-1.2.0-wp.zip`
- Repository status at handoff: stable single-competition WordPress plugin for Premier League only
- Data provider in current baseline: `football-data.org`

## Why the project was paused here
- The plugin itself is stable enough to pause safely.
- Appearance controls, legacy styling, admin preview, reset, and preset import/export are all in place in `1.2.0`.
- Follow-up provider experiments after `1.2.0` were rolled back because they did not meet the project requirements for full current-season tables on an acceptable free tier.

## What was tried after 1.2.0
- `API-Football`
  - Looked promising for Premier League and women's coverage.
  - Free-tier access was not sufficient for current-season production needs.
- `TheSportsDB`
  - Returned only partial league-table data on the free tier.
  - This was not acceptable because the project needs full tables.
- `Sportmonks`
  - The tested token only had access to Danish Superliga and Scottish Premiership content.
  - It did not expose Premier League or Women's Super League on that plan.

## Safe baseline to resume from
- Start from git commit `38ae8bf`
- Or install the packaged release `.release/premier-league-table-1.2.0-wp.zip`
- Do not resume from `1.3.x` or `1.4.x` ideas unless provider requirements are revalidated first

## Architecture snapshot
- Main plugin bootstrap: `premier-league-table.php`
- Settings and admin UX: `includes/class-settings.php`
- API client and caching: `includes/class-api-client.php`
- Frontend shortcode rendering: `includes/class-shortcode.php`
- Frontend CSS: `assets/css/frontend.css`
- Admin preview and UX: `assets/css/admin.css`, `assets/js/admin.js`

## What works in 1.2.0
- Stable Premier League shortcode output via `[pl_table]`
- Favorite-team highlighting
- Legacy Spurs-style frontend baseline
- Safe `Legacy` and `Custom` appearance presets
- Grouped appearance settings
- Live preview in admin
- Appearance reset
- Appearance preset export/import
- Cache TTL settings and transient-based caching

## Known constraints
- The current plugin only targets Premier League.
- The current provider choice should be treated as provisional if broader competition support is needed later.
- API credentials must never be committed to files or repository history.
- Provider changes are high-impact because they affect standings format, team-name mapping, caching, attribution, documentation, and release packaging.

## If work resumes later
1. Confirm the active goal first.
2. If the goal is visual/UI work only, stay on `1.2.0` and avoid provider changes.
3. If the goal is new league support or live-data expansion, start with provider research before writing code.
4. Verify full-table access, current-season access, pricing, quotas, attribution, and logo/licensing constraints.
5. Only then branch into implementation.

## Recommended resume checklist
1. Read `readme.txt`
2. Read `roadmap.md`
3. Read `docs/milestone-6-qa.md`
4. Read this handover file
5. Install/test `.release/premier-league-table-1.2.0-wp.zip` in `Local`
6. Confirm the baseline still behaves correctly before new changes

## Documentation rule for future changes
- Any future milestone should update:
  - `readme.txt`
  - `CHANGELOG.md`
  - `roadmap.md`
  - relevant `docs/*.md` notes
- Any provider experiment must also document:
  - tested plan
  - tested date
  - competitions confirmed
  - current-season access result
  - full-table access result
