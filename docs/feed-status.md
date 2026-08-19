# Data feed status

Living record of what each provider actually serves, so a change in the feeds
can be told apart from a change in the plugin. Update the date and the findings
whenever the feeds are re-audited.

_Last audited: 2026-08-19_

## Summary

Both competitions now have real 2026-27 data published, which is new since the
last audit on 2026-07-30 — at that point the WSL fixture endpoint returned HTTP
500 and crest images were empty. Two presentation problems surfaced as a direct
result of that data arriving; both are fixed in 2.3.0.

| Feed | Competition | Status | Season | Phase on audit date |
| --- | --- | --- | --- | --- |
| `football-data.org` | Premier League | Healthy | 2026-08-21 → 2027-05-30 | Preseason |
| `api-sdp.wslfootball.com` (WPLL) | Women's Super League | Healthy | 2026-09-04 → 2027-05-22 | Preseason |
| `TheSportsDB` | WSL fallback only | Unchanged | — | Not exercised |

## Premier League — football-data.org

- `GET /v4/competitions/PL` → 200. Current season id `2502`, starts
  **2026-08-21**, `currentMatchday: 1`.
- `GET /v4/competitions/PL/standings` → 200, all 20 clubs, crests populated.
- `GET /v4/competitions/PL/matches?status=SCHEDULED` → 200, full fixture list
  published. Tottenham open away at Brentford, 2026-08-22 16:30 UTC.

**Finding: every club is reported on `position: 1` during preseason.** This is
correct upstream data — nobody has played, so nobody is ahead — but rendered
verbatim it produced a table showing twenty clubs all ranked first, in
alphabetical order, which reads as broken data rather than as an unstarted
season.

Fixed in 2.3.0: `PLT_Football_Data_Provider` now flags a table with zero matches
played as `data_mode: preseason`, clears the tied positions, and sorts
alphabetically; the renderer shows a dash in the position column plus a note
naming the first matchday.

Note that `currentMatchday` is **1** during preseason, not 0 — which is why the
preseason check keys off matches actually played, not off the matchday counter
or the season start date. A postponed opening weekend would otherwise flip the
table to "live" with nothing in it.

## Women's Super League — WPLL feed

- `GET /v1/wpll/football/competitions` → 200, 8 competitions. WSL id resolves
  correctly and is still distinct from the separate `WSL 2` entry.
- `GET /v1/wpll/football/competitions/{id}/seasons` → 200, 17 seasons. The
  2026/2027 season runs **2026-09-04 → 2027-05-22**.
- `GET /v1/wpll/football/seasons/{id}/standings` → 200, **14 clubs** (the
  confirmed 12 → 14 expansion), all on 0 played / 0 points.
- `GET /v1/wpll/football/seasons/{id}/matches` → 200, **182 matches**. This
  endpoint returned HTTP 500 at the last audit because no fixtures had been
  published yet.

**Resolved since last audit: crest images are now populated.** `imagery` was
`{}` on 2026-07-30 and was logged as a cosmetic issue expected to fix itself. It
did. Logos now resolve to `media-sdp.wslfootball.com/clubLogos/*.webp`.

**Verified: the match payload matches what the plugin expects.** The fixture
parser in `includes/class-wpll-client.php` was written against the finished
2025-26 season and had never seen live 2026-27 fixture data. Re-checked field by
field against the real payload:

| Plugin expects | Feed serves | Match |
| --- | --- | --- |
| `matches[]` under a top-level `matches` key | `{competition, matches, apiCallRequestTime}` | Yes |
| `home` / `away` objects | `home` / `away` | Yes |
| `home.officialName` | `officialName`, PL-style (`Tottenham Hotspur`) | Yes |
| `home.imagery.teamLogo` | `clubLogos/*.webp` | Yes |
| `matchDateUtc` | ISO-8601 UTC, present on all 182 | Yes |

No parser change was needed. 26 Tottenham fixtures are published; the first is
2026-09-06 11:00 UTC at home to West Ham United.

**Finding: 7 of the 182 fixtures carry `isUnknownKickOffTime: true`.** These
have a placeholder hour because broadcasters have not settled the slot yet. The
plugin was not reading the flag, so the next-match card would have presented a
placeholder time as a confirmed one.

Fixed in 2.3.0: the client now returns `kickoff_time_confirmed`, and the card
shows the date plus "tidspunkt bekræftes senere" when it is false.

## Re-audit checklist

Re-run when a season starts, or if a table looks wrong:

```powershell
.\scripts\run-hybrid-qa.ps1              # Node checks against live providers
.\scripts\run-php-tests.ps1 -IncludeLive # unit suite + live smoke test
```

Still time-gated:

- **2026-08-21** — PL season starts. Confirm the table switches from preseason
  (dashes) to real positions after the first matches finish.
- **2026-09-04** — WSL season starts. Confirm the same switch, and that the
  in-season standings stay accurate once real results feed in.
- Watch whether the 7 unconfirmed WSL kickoff times get settled, and that the
  card switches to showing a real time when they do.
