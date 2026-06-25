# Project Handover

## Current state
- Project: `Premier League Table Embed`
- Current maintained baseline: `2.1.1`
- Current release zip: `.release/premier-league-table-2.1.1-wp.zip`
- Repository status at handoff: active hybrid-expansion branch moving from single-competition Premier League support toward combined PL + WSL support
- Data providers in current working branch:
  - `football-data.org` for Premier League standings and Premier League next-match
  - `TheSportsDB` for Women\'s Super League standings and experimental WSL next-match lookups

## Why the project was paused here before
- The `2.1.1` plugin is the current release-candidate baseline for the hybrid PL + WSL direction.
- Appearance controls, legacy styling, admin preview, reset, preset import/export, next-match rendering, season-aware standings caching, Premier League competition-feed next-match fetching, the hybrid PL + WSL architecture, and the latest UI polish fixes are now in place in `2.1.1`.
- Follow-up provider experiments after `1.2.0` were originally rolled back because they did not meet the project requirements for full current-season tables on an acceptable free tier.

## What was tried after 1.2.0
- `API-Football`
  - Looked promising for Premier League and women's coverage.
  - Free-tier access was not sufficient for current-season production needs.
- `TheSportsDB`
  - Earlier testing suggested partial or inconsistent table coverage.
  - Current revalidation in June 2026 confirmed usable WSL standings on the free tier for `English Womens Super League`.
  - `lookuptable.php` was later confirmed to return only 5 rows for WSL, so the safe path is to derive standings from `eventsseason.php` plus `search_all_teams.php`.
  - Current revalidation also confirmed that WSL next-match data can legitimately be empty between seasons.
- `Sportmonks`
  - The tested token only had access to Danish Superliga and Scottish Premiership content.
  - It did not expose Premier League or Women's Super League on that plan.

## Current architecture direction
- The codebase is no longer purely PL-only in working-branch intent.
- New scaffolding now exists for:
  - provider adapters
  - club mapping
  - WSL standings retrieval through `TheSportsDB`
  - combined PL/WSL table rendering
  - dual-card next-match rendering
- The public shortcodes are still:
  - `[pl_table]`
  - `[pl_next_match]`

## Safe baseline choices
- Stable release baseline:
  - install `.release/premier-league-table-2.1.1-wp.zip` if you need the latest packaged hybrid candidate
- Current feature branch baseline:
  - use the latest working tree if you want to continue PL + WSL expansion
- Do not assume the current working tree is release-ready until:
  - PHP validation has been run
  - WordPress local QA has been completed
  - WSL offseason empty states have been reviewed visually

## Architecture snapshot
- Main plugin bootstrap: `premier-league-table.php`
- Settings and admin UX: `includes/class-settings.php`
- Premier League API client and caching: `includes/class-api-client.php`
- Hybrid provider scaffolding:
  - `includes/interface-standings-provider.php`
  - `includes/class-football-data-provider.php`
  - `includes/class-thesportsdb-provider.php`
  - `includes/class-thesportsdb-client.php`
  - `includes/class-standings-service.php`
  - `includes/class-club-map.php`
- Frontend shortcode rendering: `includes/class-shortcode.php`
- Next-match shortcode rendering: `includes/class-next-match-shortcode.php`
- Frontend CSS: `assets/css/frontend.css`
- Next-match CSS: `assets/css/next-match.css`
- Admin preview and UX: `assets/css/admin.css`, `assets/js/admin.js`

## What works in the current working branch
- Existing Premier League table flow still points to `football-data.org`
- Existing Premier League next-match flow still points to `football-data.org`
- Standings service can now route:
  - `PL`
  - `WSL`
  - combined rendering flow
- `[pl_table]` can now be driven internally as:
  - PL
  - WSL
  - combined PL + WSL
- Combined table rendering now has a first tabs UI
- `[pl_next_match]` now renders separate PL and WSL cards side by side
- WSL standings are verified as working through `TheSportsDB`
- Full WSL standings should now be treated as a derived table problem, not a direct provider-table problem

## Known constraints
- WSL next-match can be empty during offseason windows even when the integration itself is working.
- `TheSportsDB` team discovery is less clean than `football-data.org`; direct alias mapping is safer than generic search.
- WSL must stay dynamic in team count because the league is planned to expand from 12 to 14 clubs in the `2026/27` season.
- The current provider mix is intentionally hybrid and therefore more complex than the original PL-only baseline.
- API credentials must never be committed to files or repository history.
- Provider changes are high-impact because they affect standings format, team-name mapping, caching, attribution, documentation, and release packaging.
- PHP CLI was not available in the working environment during this phase, so command-line PHP linting still needs to be run elsewhere.

## If work resumes later
1. Confirm the active goal first.
2. If the goal is release hardening, validate the current PL + WSL working branch instead of returning to older pre-hybrid baselines.
3. If the goal is further provider work, re-check current-season access, quotas, attribution, and offseason fixture behavior before more UI changes.
4. Verify the current shortcodes in Local WordPress before packaging anything.
5. Only then cut a new release candidate.

## Recommended resume checklist
1. Read `readme.txt`
2. Read `roadmap.md`
3. Read this handover file
4. Read `docs/hybrid-release-qa.md`
5. Run `.\scripts\run-hybrid-qa.ps1`
6. Test `[pl_table]`, `[pl_table competition="all"]`, and `[pl_next_match]` in `Local`
7. Confirm WSL offseason empty states are acceptable before new release work

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
