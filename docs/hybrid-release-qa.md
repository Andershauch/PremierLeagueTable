# Hybrid PL + WSL Release QA

## Scope
- This QA note belongs to the current hybrid working branch, not the older `1.2.0` or pre-hybrid stable baselines.
- Goal: verify that the new PL + WSL architecture is release-candidate safe before any version bump.

## Required shortcode checks
- `[pl_table]`
  - Loads Premier League standings with no PHP warning or visible API error.
  - Highlights the configured focus team row correctly.
- `[pl_table competition="wsl"]`
  - Loads WSL standings via the primary WSL Football (WPLL) feed, with the expected season behavior:
    - `preseason` should show a 0-table (all clubs, zero played)
    - `live` should show the real standings from the feed
  - If the primary feed is unreachable or errors, confirm the table still renders via the `TheSportsDB` fallback rather than showing an error box (see "Fallback checks" below).
- `[pl_table competition="all"]`
  - Renders both `PL` and `WSL` tabs.
  - Switching tabs does not break layout or lose state.
  - One provider failing should not blank the other tab.
- `[pl_next_match]`
  - Shows the Premier League card when PL data is available.
  - Shows the WSL card when a future WSL fixture is available.
  - Shows a clear offseason-style empty state for WSL when no future match exists.

## Focus-team checks
- `Tottenham Hotspur` resolves to:
  - `Tottenham Hotspur` for PL
  - `Tottenham Women` for WSL
- At least 3 more shared clubs should be spot-checked end to end:
  - `Arsenal`
  - `Chelsea`
  - `Manchester City`
- A PL-only club should degrade safely:
  - no slug-like display value
  - no fatal WSL lookup behavior

## Data-mode checks
- WSL preseason mode:
  - the WPLL feed's own standings response already returns a full 0-table (all expected clubs, zero played) for a season before it starts — no client-side fallback roster is needed on the primary path
  - no invalid-response error when the WPLL matches endpoint 500s for a season with no fixtures published yet (expected; treated as "no upcoming match")
- WSL live mode:
  - table rank order and points/GD/GF match the WPLL feed's own `rank` field and stats exactly (no client-side derivation needed on the primary path)
  - if forced onto the `TheSportsDB` fallback: no invalid-response error when TheSportsDB returns empty `eventsseason.php` or empty `eventsnext.php`; expected team count comes from internal roster, not only provider list; derived table rank order matches points, goal difference, then goals scored

## Fallback checks
- The primary WSL source (`api-sdp.wslfootball.com`, undocumented) can change or go down without notice — `TheSportsDB` must still work as a silent fallback.
- To force the fallback path for testing, temporarily break the WPLL client (e.g. point `PLT_WPLL_Client::BASE_URL` at an invalid host, or via a `pre_http_request` filter that fails only requests to `api-sdp.wslfootball.com`) and confirm:
  - `[pl_table competition="wsl"]` still renders a table (via `TheSportsDB`), not an error box.
  - `[pl_next_match]`'s WSL card still renders or shows the normal offseason empty state, not a fatal or blank card.
  - Revert the forced break afterward.
- The fallback-loop logic itself is covered by a committed, repeatable test (`tests/unit/StandingsServiceFallbackTest.php`, runs in CI) — but that's still mocked providers in a PHP harness, not a real forced outage inside a running WordPress site. That specific check is still outstanding.

## Verification scripts
- Preferred runner:
  - `.\scripts\run-hybrid-qa.ps1`
- Run:
  - `node .\scripts\check-wpll-standings.mjs` (primary WSL source)
  - `node .\scripts\check-thesportsdb-wsl.mjs` (fallback WSL source)
  - `node .\scripts\prototype-thesportsdb-wsl-table.mjs`
  - `node .\scripts\verify-thesportsdb-wsl-mode.mjs`
  - `node .\scripts\verify-club-map.mjs`
  - `node .\scripts\verify-thesportsdb-next-match.mjs`
- The PowerShell runner executes the same checks in sequence and stops on the first failing script.
- Review:
  - WPLL feed's resolved current season and phase (`preseason`/`live`) against the calendar
  - provider team counts
  - expected fallback roster sizes
  - missing clubs
  - alias behavior for WSL next-match lookups

## PHP unit tests
- Preferred runner: `.\scripts\run-php-tests.ps1` (add `-IncludeLive` to also run the live smoke test against the real WPLL feed).
- Dependency-free — no Composer/PHPUnit, just `php`. Runs in CI via `.github/workflows/php-tests.yml` on every push/PR to `main`.
- Covers: `PLT_Club_Map` resolution, `PLT_WPLL_Client` pure helpers, `PLT_WPLL_Standings_Provider` row-building against real captured API fixtures, WPLL season-phase resolution (live/preseason/latest-finished branches, via dates relative to test-run time so it never goes stale), next-match team/soonest-match matching, an end-to-end `get_standings()` round trip, and the `PLT_Standings_Service` fallback loop.
- Full detail in `docs/project-handover.md` under "One-off verification harnesses turned into a real test suite".
- This is what the fallback-loop test in the "Fallback checks" section above now runs as — no longer a throwaway scratchpad script.

## WordPress checks
- Test in Local WordPress with the active site theme.
- Confirm plugin settings save without clearing the stored football-data API key unintentionally.
- Confirm `favorite_team` still saves from the existing dropdown and drives both shortcodes.
- Confirm no admin warning or fatal appears after activating the updated plugin.

## Visual checks
- Desktop:
  - tabbed table layout looks intentional
  - next-match PL and WSL cards align side by side
- Mobile:
  - table still scrolls without column collapse
  - tabs remain tappable
  - next-match cards stack cleanly

## Remaining release blockers
- PHP CLI linting: done. Confirmed clean (`php -l`) across the entire plugin tree, including the WPLL integration files, via Local by Flywheel's bundled PHP 8.2.30 CLI (see `docs/project-handover.md` for the path).
- Manual WordPress QA: done for 2.2.0. User confirmed on 2026-07-30 that Local testing works and displays correct data with the new WPLL primary provider active.
- A real forced-outage test of the WSL fallback path inside WordPress is still outstanding (see "Fallback checks" above) — the fallback loop itself is only verified with mocked providers so far, not by actually breaking the live WPLL feed inside a running WordPress site.
- Version number and release notes should only be updated after this checklist passes — 2.2.0 was packaged and released after the above.

## Verification log

### 2026-07-30
- Ran `.\scripts\run-hybrid-qa.ps1`-equivalent checks (all five `scripts/*.mjs` verifiers) plus `check-football-data-wsl.mjs` against live provider APIs.
- `football-data.org`: PL confirmed healthy with a valid key; confirmed no WSL/BWSL competition exists on this plan (404 on both codes) — `TheSportsDB` has no fallback provider for WSL.
- `TheSportsDB` WSL: season-mode, club-map, and next-match alias checks all passed. Roster from `search_all_teams.php` is still incomplete but the fallback roster/alias lookups correctly cover the gap.
- Open item carried forward: derived-table event coverage (`eventsseason.php`) looks too sparse to trust once the 2026-27 WSL season goes live — re-run `prototype-thesportsdb-wsl-table.mjs` against real in-season fixtures as soon as they exist, and compare against `lookuptable.php` before trusting the derived table in production. See `docs/project-handover.md` for full detail. **Now only matters if the WSL fallback is triggered — see the entry below.**

### 2026-07-30 (implementation)
- Replaced the WSL derived-table approach with a new primary source: the JSON feed behind wslfootball.com's own site (`api-sdp.wslfootball.com`). Full detail and verification steps in `docs/project-handover.md`.
- `TheSportsDB` kept as an automatic fallback via an ordered-provider list in `PLT_Standings_Service`.
- Verified against live data: full 12-team 2025-26 table (matches `lookuptable.php` exactly), full 14-team 2026-27 preseason 0-table, 132/132 2025-26 season matches, correct preseason/live phase detection from exact season dates.
- Verified in isolation (mocked providers, no live network): the WSL provider fallback loop in `PLT_Standings_Service` correctly falls through on primary failure, surfaces an error only if every provider fails, and never calls the fallback when the primary succeeds.
- All new/changed PHP files pass `php -l` using Local by Flywheel's bundled PHP 8.2 CLI (path in `docs/project-handover.md`).
- **Update, same day:** user-run manual WordPress/Local QA confirmed passing on all points, with correct data displayed for `[pl_table]`, `[pl_table competition="wsl"]`, `[pl_table competition="all"]`, and `[pl_next_match]`. This closes the "real WordPress/Local QA" item. Still outstanding: a real (not mocked) forced-outage test of the fallback inside WordPress (see "Fallback checks" above).

### 2026-07-30 (test suite)
- Turned the one-off scratchpad verification harnesses above into a committed, repeatable, CI-run PHP test suite (`tests/`, dependency-free, no Composer/PHPUnit). 93 assertions, all passing; see "PHP unit tests" section above and `docs/project-handover.md` for full detail.
- Added `.github/workflows/php-tests.yml` so this actually runs automatically on every push/PR, not just when someone remembers to run it locally.
