# Milestone 6 QA-checkliste

## Security pass
- Bekraeft at settings kun kan gemmes som admin (`manage_options`).
- Bekraeft at alle frontend-vaerdier renderes escaped.
- Bekraeft at kun gyldige farver/numre accepteres i style-felter.
- Bekraeft at `favorite_team` kun kan gemmes fra gyldige dropdown-valg.

## Performance pass
- Bekraeft at standings caches i transients.
- Bekraeft at cache invalideres ved settings-save.
- Bekraeft at cache lock reducerer dublette API-kald ved samtidige hits.
- Bekraeft TTL opfoersel ved 1 min og 10 min.

## Cross-device test
- Desktop: tabel grid og favorit-raekke ser korrekt ud.
- Tablet: vandret scroll virker uden layout-brud.
- Mobil: card-lignende raekker med `data-label` vises korrekt.

## WordPress compatibility
- Test paa aktivt standardtema (fx Twenty Twenty-Four).
- Test med Gutenberg-side og shortcode `[pl_table]`.
- Bekraeft at publicering/REST ikke fejler.

## Debug validation
- Med `WP_DEBUG=true`, bekraeft `[PLT]` loglinjer ved API-fejl i `wp-content/debug.log`.
- Med `WP_DEBUG=false`, bekraeft at pluginet ikke skriver debug-loglinjer.
