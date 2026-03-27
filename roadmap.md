# Premier League Table Embed Plugin - Roadmap

## Projektbeskrivelse
Målet er at bygge et sikkert, responsivt og velstruktureret WordPress-plugin, der kan embedde en live Premier League-stilling på en WordPress-side.

Pluginet skal:
- Lade brugeren vælge et yndlingshold, som fremhæves i tabellen.
- Give et simpelt interface til look and feel (farver, fonte, størrelser, spacing og kanter).
- Være sikkert (sanitering, escaping, nonce- og capability-checks).
- Være performant (caching af API-data og færre unødige kald).
- Være let at bruge via shortcode (block kan tilføjes senere).

## Scope (Fase 1)
- Admin-indstillinger i WordPress.
- API-integration til liga-stilling.
- Frontend-render via shortcode, fx `[pl_table]`.
- Fremhævning af yndlingshold i tabellen.
- Basis design-tilpasning.
- Caching og pæn fallback ved API-fejl.

## Beslutning (20. februar 2026)
- Primær data-provider: `football-data.org`.
- Endpoint: `GET /v4/competitions/PL/standings`.

## Arkitektur-overblik
- `premier-league-table.php`
- `includes/`
  - `class-plugin.php`
  - `class-settings.php`
  - `class-api-client.php`
  - `class-cache.php`
  - `class-renderer.php`
  - `class-shortcode.php`
- `assets/`
  - `css/frontend.css`
  - `css/admin.css`
  - `js/admin.js`
- `templates/`
  - `table.php`

## Status
- Dato: 22. februar 2026
- Milestone 0-7: `DONE`
- Lokal testmiljø (WordPress + Docker): `DONE`
- Lokal testmiljø (LocalWP, Windows): `DONE/ANBEFALET`
- Fast LocalWP plugin-sti:
  `C:\Users\ander\Local Sites\whitehartdanes\app\public\wp-content\plugins\premier-league-table`

### Post-release (live hardening + UI-paritet)
- Plugin aktivering verificeret på live-host efter korrekt mappe-/zip-struktur.
- Release-zip pipeline opdateret til WordPress-kompatible zip-paths (`/` i stedet for `\`).
- Frontend-layout justeret mod reference:
  - mindre overskrift i tabel-header
  - reduceret luft i klubkolonnen og tættere tabelspacing
  - point-typografi sat til `Apex New` med `font-weight: 300`
- Mobil/narrow viewport forbedringer:
  - bedre balance mellem klubkolonne og statistik-kolonner (`K`, `V`, `U`, `T`, `M+`, `M-`, `MF`)
  - kompakte visningsnavne for lange klubnavne (fx `Brighton`, `West Ham`, `Wolves`)
- Release version bump til `1.0` i plugin-header, assets versionering, readme og changelog.
- Ny WordPress-upload zip genereret: `.release/premier-league-table-1.0-wp.zip`

## Næste konkrete step
1. Kør smoke test af `1.0` på et rent WordPress-site (upload, aktivering, shortcode, settings-save).
2. Verificer frontend-paritet på live (desktop + mobil) efter cache purge/CDN purge.
3. Beslut næste feature-prioritet (fx block-support eller forbedret settings-UX) og planlæg `1.1`.

---

Dette roadmap er et levende dokument og opdateres efter hver milestone.
