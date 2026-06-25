# Hybrid PL + WSL Release QA

## Scope
- This QA note belongs to the current hybrid working branch, not the older `1.2.0` or pre-hybrid stable baselines.
- Goal: verify that the new PL + WSL architecture is release-candidate safe before any version bump.

## Required shortcode checks
- `[pl_table]`
  - Loads Premier League standings with no PHP warning or visible API error.
  - Highlights the configured focus team row correctly.
- `[pl_table competition="wsl"]`
  - Loads WSL standings with the expected season behavior:
    - `preseason` should show a 0-table
    - `live` should show derived standings from played matches
  - Uses the fallback roster when TheSportsDB returns too few teams.
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
  - no invalid-response error when TheSportsDB returns empty `eventsseason.php` or empty `eventsnext.php`
  - expected team count comes from internal roster, not only provider list
- WSL live mode:
  - derived table rank order matches points, goal difference, then goals scored
  - teams discovered through played fixtures are still included even if provider lists are incomplete

## Verification scripts
- Preferred runner:
  - `.\scripts\run-hybrid-qa.ps1`
- Run:
  - `node .\scripts\check-thesportsdb-wsl.mjs`
  - `node .\scripts\prototype-thesportsdb-wsl-table.mjs`
  - `node .\scripts\verify-thesportsdb-wsl-mode.mjs`
  - `node .\scripts\verify-club-map.mjs`
  - `node .\scripts\verify-thesportsdb-next-match.mjs`
- The PowerShell runner executes the same checks in sequence and stops on the first failing script.
- Review:
  - provider team counts
  - expected fallback roster sizes
  - missing clubs
  - alias behavior for WSL next-match lookups

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
- PHP CLI linting has still not been run in this environment.
- Manual WordPress QA is still required after every data-layer change.
- Version number and release notes should only be updated after this checklist passes.
