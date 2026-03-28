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
