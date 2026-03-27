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

## Follow-up ideas
1. Add a reset-to-legacy button for appearance settings.
2. Add more curated presets instead of exposing more raw controls.
3. Add translation files so the plugin can ship English-first while still supporting localized admin copy.
4. Add a lightweight connection-status panel for the football-data API key and cache state.
