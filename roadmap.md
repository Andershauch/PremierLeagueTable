# Premier League Table Embed Roadmap

## Current architecture
- WordPress plugin entrypoint: `premier-league-table.php`
- Settings and admin UX: `includes/class-settings.php`
- Football-data client and caching: `includes/class-api-client.php`
- Frontend shortcode rendering: `includes/class-shortcode.php`
- Frontend styles: `assets/css/frontend.css`
- Admin preview/styles: `assets/css/admin.css`, `assets/js/admin.js`

## Release baseline
- Version `1.0.9` shipped the English settings page, Football-Data attribution, and stable legacy frontend skin.
- Version `1.1.0` introduces a documented appearance system with safe presets and live admin preview.
- Version `1.2.0` adds grouped appearance UX, reset/export/import preset tools, header typography controls, and customizable zebra-row colors.
- Version `2.0.1` adds season-aware Football-Data caching so current-season data can refresh automatically after a provider-reported season change.
- Version `2.0.2` restores Premier League-only filtering for next-match lookups to stay inside narrower football-data.org access scopes.
- Version `2.0.3` switches next-match lookups to the Premier League competition feed so the module no longer depends on restricted team match access.
- Version `2.0.4` removes the invalid one-sided date filter from the Premier League next-match competition feed request.
- Version `2.0.5` improves the offseason empty-state messaging for the Premier League next-match module.
- Version `2.0.6` replaces the generic `focus team` label in the next-match empty state with the configured club name and proper Danish copy.
- Version `2.1.0` introduces the hybrid PL + WSL architecture, combined tabs, dual next-match cards, and hardened WSL provider behavior.

## Pause status
- The project is currently being prepared for release as version `2.1.0`.
- Post-`1.2.0` provider experiments were rolled back because they did not satisfy the requirement for full current-season tables on a viable free tier.
- Resume from this baseline unless a new provider has been validated first.
- See `docs/project-handover.md` before restarting development.

## Appearance system goals
- Keep `Legacy` as the safe default so the released Spurs-style table remains stable.
- Allow `Custom` styling only through validated design tokens.
- Never allow arbitrary CSS injection or layout-breaking controls from settings.
- Keep color choices readable by enforcing contrast fallbacks for header and focus rows.
- Keep all source files as UTF-8 without BOM.

## Appearance phase 1 scope
- `Visual preset` selector with `Legacy` and `Custom`.
- Whitelisted font-family choices.
- Font-size and density controls.
- Validated text, grid, header, and focus-row colors.
- Optional zebra rows.
- Live preview in admin using the same frontend table CSS.

## Appearance phase 2 status
- Grouped/collapsible appearance sections in the settings UI.
- Separate typography controls for:
  - table base
  - header
  - regular team names
  - focus-team name
- Reset-to-legacy appearance action.
- Appearance preset export/import as JSON.
- Live preview kept in sync with the expanded appearance token set.

## Follow-up ideas
1. Add more curated presets instead of exposing more raw controls.
2. Add translation files so the plugin can ship English-first while still supporting localized admin copy.
3. Add a lightweight connection-status panel for the football-data API key and cache state.
4. Add a preset preview thumbnail/gallery to the import/export workflow.
5. Revisit multi-competition support only after provider coverage, quotas, and full-table access are confirmed in writing.

## Hybrid PL + WSL expansion plan

### Goal
- Keep the existing stable Premier League baseline intact.
- Add Women's Super League support without replacing the current provider.
- End with one plugin experience where:
  - the table shortcode still renders one widget
  - the widget has two tabs: `PL` and `WSL`
  - one focus-team choice maps to both the men's and women's club when applicable
  - the next-match shortcode still exists separately, but shows both the men's and women's upcoming fixtures

### Product direction
- Final target UX:
  - `[pl_table]` becomes a two-tab standings module for Premier League and Women's Super League.
  - `[pl_next_match]` remains a dedicated shortcode, but renders upcoming fixtures for both squads.
  - Choosing `Tottenham Hotspur` as the focus team should also imply `Tottenham Women` / `Tottenham Hotspur W` in the women's dataset.
- Delivery strategy:
  - do not jump straight to the final UI
  - first validate data coverage and build a safe adapter architecture
  - only then merge the outputs into shared frontend modules

### Confirmed provider direction
- `football-data.org` remains the provider for Premier League standings and Premier League next-match data.
- `TheSportsDB` is the candidate provider for Women's Super League data.
- Current validation status:
  - `football-data.org` remains the stable source for PL in the current plugin baseline.
  - `TheSportsDB` free API has been verified to return WSL teams and WSL league-table data.
  - `TheSportsDB` WSL next-league fixtures were empty during testing, so next-match coverage still needs explicit validation before implementation.

### Assumptions
- The plugin should keep one shared club identity across the product, not separate focus-team selectors for PL and WSL.
- Team mapping must be explicit and deterministic; fuzzy string matching alone is not strong enough for a multi-provider setup.
- WSL support should be additive and must not destabilize the released PL flow.

### Risks
- Two providers means two schemas, two season formats, two cache strategies, and two failure modes.
- `TheSportsDB` free API uses the older v1 URL model and has tighter limits.
- WSL standings are confirmed, but WSL next-match data is not yet proven reliable enough for production behavior.
- A tabbed UI sounds simple, but it increases rendering state, caching, empty states, and fallback complexity.
- One global focus-team setting becomes fragile if club-to-club mapping is not modeled explicitly.
- WSL expands from 12 to 14 clubs from the `2026/27` season, so any implicit team-count assumptions will break quickly.

### Recommended solution
- Implement a hybrid provider architecture instead of migrating the plugin wholesale.
- Add provider-specific adapters with one normalized internal shape for:
  - standings rows
  - team identity
  - next-match cards
  - season metadata
- Add a deterministic club mapping layer so one saved focus team can resolve to:
  - PL provider team identity
  - WSL provider team identity
- Deliver WSL in phases:
  - first standings support
  - then fixture validation
  - then unified frontend presentation

### Target architecture changes
- Keep the plugin bootstrap and shortcode entrypoints stable where possible.
- Refactor the current PL-only API client into provider-aware services or adapters.
- Separate these concerns:
  - provider fetch logic
  - response normalization
  - club mapping
  - cache key generation
  - shortcode rendering
- Avoid scattering provider checks throughout template or shortcode code.

### Sprint 1
- Validate and document `TheSportsDB` WSL coverage.
- Create local verification tooling for:
  - WSL league table
  - WSL teams
  - WSL next fixtures if available
- Document the verified identifiers and constraints:
  - WSL league name
  - WSL league id
  - season format
  - rate limits
  - gaps or empty responses

### Sprint 2
- Introduce a provider adapter layer.
- Keep the existing PL path working through the new abstraction.
- Add a `TheSportsDB` adapter for WSL standings.
- Define a normalized standings shape that the renderer can consume without caring about provider details.
- Do not trust `lookuptable.php` as the primary WSL source if it returns only a partial table; derive the standings from season events instead.

### Sprint 3
- Add explicit club mapping for focus-team resolution.
- Start with a trusted internal mapping for supported clubs instead of broad fuzzy matching.
- Prove the Tottenham mapping end-to-end:
  - `Tottenham Hotspur` -> PL team
  - `Tottenham Women` / equivalent WSL team record

### Sprint 4
- Add WSL table rendering behind a separate experimental path first.
- Prefer a temporary separate shortcode or internal toggle while validating data and caching behavior.
- Do not merge into the final two-tab frontend until standings output is stable.

### Sprint 5
- Build the final shared standings shortcode experience.
- `[pl_table]` should render:
  - `PL` tab
  - `WSL` tab
- Keep a clear empty/error state per tab so one provider failing does not blank the whole widget.

### Sprint 6
- Expand the next-match shortcode to support both squads.
- Keep `[pl_next_match]` as the public shortcode.
- Render one card/module per squad once WSL fixture coverage is verified strongly enough.
- If WSL fixture data remains inconsistent, keep the PL next-match flow stable and ship WSL standings first.

### Decisions we have already made
- Do not replace `football-data.org` for Premier League.
- Do not continue with API-Football / API-Sports for this initiative.
- Use `TheSportsDB` only as the WSL provider candidate.
- Keep one shared focus-team concept as the desired end state.
- Preserve the existing shortcode model instead of introducing a new public API surface immediately.

### Decisions still to make
- Whether the interim WSL release should ship behind:
  - a separate shortcode
  - an internal feature flag
  - or a settings toggle
- Whether WSL next-match can ship in the same milestone as WSL standings.
- Whether focus-team mapping should be stored:
  - as a canonical club key
  - as explicit provider ids
  - or both

### Next best step
- Replace the current WSL standings fetch with a derived standings builder based on `eventsseason.php` plus `search_all_teams.php`.
- Then validate that the Local WordPress output now shows the full league table and remains safe when WSL moves to 14 clubs.
- Follow immediately with WSL data hardening:
  - explicit `preseason` vs `live` mode handling
  - authoritative fallback rosters per season
  - lightweight verification scripts that compare provider team counts against expected league size
- Then harden shared club mapping:
  - structured canonical club catalog
  - explicit PL/WSL provider names per supported club
  - safe fallback for PL clubs that do not have a WSL counterpart
- Then harden WSL next-match lookups:
  - alias-aware team discovery
  - graceful offseason empty-state handling
  - reduced dependence on a single TheSportsDB search endpoint
- Then do release hardening:
  - create a hybrid QA checklist for the PL + WSL branch
  - run Local shortcode QA before any version bump
  - only then prepare a release candidate package
