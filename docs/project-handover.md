# Project Handover

## Current state
- Project: `Premier League Table Embed`
- Current maintained baseline: `2.1.1`
- Current release zip: `.release/premier-league-table-2.1.1-wp.zip`
- Repository status at handoff: active hybrid-expansion branch moving from single-competition Premier League support toward combined PL + WSL support
- Data providers in current working branch:
  - `football-data.org` for Premier League standings and Premier League next-match
  - WSL Football's own feed (`api-sdp.wslfootball.com`, undocumented but public/Opta-backed) for Women's Super League standings and next-match, as the primary source since 2026-07-30
  - `TheSportsDB` kept as the automatic WSL fallback if the primary feed errors or changes shape

## Why the project was paused here before
- The `2.1.1` plugin is the current release-candidate baseline for the hybrid PL + WSL direction.
- Appearance controls, legacy styling, admin preview, reset, preset import/export, next-match rendering, season-aware standings caching, Premier League competition-feed next-match fetching, the hybrid PL + WSL architecture, and the latest UI polish fixes are now in place in `2.1.1`.
- Follow-up provider experiments after `1.2.0` were originally rolled back because they did not meet the project requirements for full current-season tables on an acceptable free tier.

## Live API re-verification (2026-07-30)
- Ran all `scripts/*.mjs` checks against live provider responses (previously blocked locally by a missing `FOOTBALL_DATA_API_KEY` in `.env.local`; a working key was added for this session).
- `football-data.org`: PL endpoint confirmed healthy (200 on `/competitions/PL/standings`). 2026/27 PL season starts `2026-08-21`, currently preseason.
- `football-data.org`: confirmed to have no WSL/BWSL competition on this plan (404 on both codes, zero England-women matches in competition discovery). `TheSportsDB` is therefore not just the chosen WSL provider but the only one available on the current plan — there is no fallback if it degrades.
- `TheSportsDB` WSL: season-mode detection correctly reports `2026-2027` as active and `preseason` (0 events) for today's date, so the live widget currently shows a correct empty table rather than wrong data.
- `TheSportsDB` WSL: `search_all_teams.php` roster is still incomplete (10/12 expected clubs for 2025-26), but the fallback roster and alias-based `searchteams.php` lookups were re-verified to correctly resolve every "missing" club individually — this known gap remains well mitigated.
- New risk identified: the derived-table approach (`eventsseason.php`-based), which is the designated path for producing the full live WSL table once matches are underway, only found ~15 completed events for the entire already-finished 2025-26 season (expected ~130+), and disagreed sharply with the provider's own partial-but-authoritative `lookuptable.php` result. Not visible to users yet because the site is in preseason mode, but this needs to be rechecked as soon as the 2026-27 WSL season produces real fixtures, since it directly affects whether the "live" WSL table will be accurate rather than just empty. **Superseded by the primary-provider change below** — this risk now only matters if the plugin falls back to `TheSportsDB`.

## WSL primary provider replaced with WSL Football's own feed (2026-07-30)
- User asked about scraping `wslfootball.com/standings/wsl` as an alternative to the unreliable `TheSportsDB` derived table. Investigation found something better than scraping: the page's Next.js frontend calls `api-sdp.wslfootball.com/v1/wpll/football/*` directly — an undocumented but public, unauthenticated, CORS-open JSON API (`Access-Control-Allow-Origin: *`), Opta-backed, operated by WPLL (the WSL's operator) for their own site.
- Verified this feed end-to-end against live data:
  - `GET /v1/wpll/football/competitions` → find the competition with `shortName: "WSL"` (careful: `"WSL 2"` is a distinct second-tier competition in the same list).
  - `GET /v1/wpll/football/competitions/{competitionId}/seasons` → exact `startDateUtc`/`endDateUtc` per season, enabling precise (not heuristic) preseason/live detection.
  - `GET /v1/wpll/football/seasons/{seasonId}/standings` → full table, correct even for a season that hasn't started (returns all clubs at 0 played/0 points — a real "0-table", not an empty response).
  - `GET /v1/wpll/football/seasons/{seasonId}/matches` → full fixture list (132/132 for the finished 2025-26 season, vs. 15/~130 from `TheSportsDB`'s `eventsseason.php`); returns HTTP 500 for a season with no fixtures published yet, which the client treats as "no upcoming match", not a hard error.
  - Club/team `officialName` values match `football-data.org`'s PL naming exactly (`Manchester City`, `Chelsea`, `Tottenham Hotspur`, etc.), so next-match team resolution tries the PL-style name first and only falls back to the existing WSL alias name.
  - Crest images live at `media-sdp.wslfootball.com/{imagery.teamLogo}`; empty (`imagery: {}`) for a season before it starts — confirmed on 2026-27 preseason data — but the frontend already renders gracefully with no crest, so this is cosmetic and expected to resolve once the provider populates it closer to kickoff.
- Implementation:
  - `includes/class-wpll-client.php` — low-level HTTP client: competition/season discovery (cached ~6h), standings fetch, match/fixture fetch and team-name matching, all with WP transient caching mirroring the existing `TheSportsDB` client's patterns.
  - `includes/class-wpll-standings-provider.php` — implements `PLT_Standings_Provider`, normalizes rows into the existing shape the shortcode renderer already expects.
  - `includes/class-standings-service.php` — constructor now takes an **ordered array** of WSL providers instead of a single one; `get_wsl_standings()` tries each in turn and only returns an error if all of them fail. Wired as `[new PLT_WPLL_Standings_Provider(...), new PLT_TheSportsDB_Provider()]` in `includes/class-plugin.php`, so `TheSportsDB` is an automatic, silent fallback.
  - `includes/class-next-match-shortcode.php` — WSL next-match resolution now tries the WPLL client first (with both the PL-style and WSL-alias team names as match candidates), then falls back to the `TheSportsDB` client on any error.
  - `scripts/check-wpll-standings.mjs` — new verification script following the existing `scripts/*.mjs` convention, wired into `.\scripts\run-hybrid-qa.ps1` as the first check.
- Testing performed without a WordPress environment: found a bundled PHP 8.2 CLI at `C:\Users\ander\AppData\Roaming\Local\lightning-services\php-8.2.30+1\bin\win64\php.exe` (from Local by Flywheel) and used it to (a) lint all changed/new PHP files (`php -l`, all clean — note `-l` alone works with no extensions loaded), and (b) run a standalone harness that stubs the handful of WordPress functions used (`wp_remote_get` via `file_get_contents` with `-d extension=php_openssl.dll`, `get_transient`/`set_transient`, `WP_Error`, etc.) to exercise the real client against the live feed, and a second harness that verifies the provider-fallback loop in `PLT_Standings_Service` with mock providers (primary fails → fallback succeeds; both fail → clean error; primary succeeds → fallback never called). **This is not a substitute for actual WordPress/Local QA**, which still has not been done — see "Next best step" in `roadmap.md`.
- Caveat carried forward: this is an undocumented internal API with no published contract or stability guarantee — exactly why it was added as primary-with-fallback rather than a full replacement of `TheSportsDB`.

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
  - `includes/class-wpll-standings-provider.php` (primary WSL source since 2026-07-30)
  - `includes/class-wpll-client.php`
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
- WSL standings and next-match now go through the WSL Football (WPLL) feed first, with `TheSportsDB` as an automatic fallback
- The old "derive standings from raw events" problem is gone for the primary path — the WPLL feed already serves a complete, correct table and fixture list; it only resurfaces if the fallback to `TheSportsDB` is triggered

## Known constraints
- WSL next-match can be empty during offseason windows even when the integration itself is working.
- `TheSportsDB` team discovery is less clean than `football-data.org`; direct alias mapping is safer than generic search. (Still true, but now only matters on the fallback path.)
- WSL must stay dynamic in team count because the league is planned to expand from 12 to 14 clubs in the `2026/27` season. Confirmed handled correctly by the WPLL feed (returns 14 teams already).
- The primary WSL feed (`api-sdp.wslfootball.com`) is an undocumented internal API with no published contract — it could change shape or disappear without notice. This is why `TheSportsDB` was kept as an automatic fallback instead of a full replacement.
- The current provider mix is intentionally hybrid and therefore more complex than the original PL-only baseline.
- API credentials must never be committed to files or repository history.
- Provider changes are high-impact because they affect standings format, team-name mapping, caching, attribution, documentation, and release packaging.
- PHP CLI is available locally after all via Local by Flywheel's bundled binary: `C:\Users\ander\AppData\Roaming\Local\lightning-services\php-8.2.30+1\bin\win64\php.exe` (no `php.ini` is loaded by default — pass `-d extension_dir=... -d extension=php_openssl.dll` etc. explicitly if a script needs HTTPS or other extensions). Use it for `php -l` linting and one-off verification harnesses going forward instead of assuming PHP CLI is unavailable.

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
