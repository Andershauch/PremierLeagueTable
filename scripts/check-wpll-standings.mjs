const BASE_URL = 'https://api-sdp.wslfootball.com/v1/wpll/football';
const IMAGE_BASE_URL = 'https://media-sdp.wslfootball.com/';
const COMPETITION_SHORT_NAME = 'WSL';

async function fetchJson(url) {
  const response = await fetch(url, { headers: { Accept: 'application/json' } });
  const text = await response.text();

  let body;
  try {
    body = JSON.parse(text);
  } catch {
    body = { raw: text };
  }

  return { status: response.status, ok: response.ok, url, body };
}

function printSection(title, payload) {
  console.log(`\n=== ${title} ===`);
  console.log(JSON.stringify(payload, null, 2));
}

function resolveCurrentSeason(seasons) {
  const now = Date.now();
  let live = null;
  let nearestUpcoming = null;
  let latestPast = null;

  for (const season of seasons) {
    const start = Date.parse(season.startDateUtc);
    const end = Date.parse(season.endDateUtc);
    if (Number.isNaN(start) || Number.isNaN(end)) {
      continue;
    }

    if (now >= start && now <= end) {
      live = { season, start, end };
      continue;
    }

    if (start > now && (!nearestUpcoming || start < nearestUpcoming.start)) {
      nearestUpcoming = { season, start, end };
    }

    if (end < now && (!latestPast || end > latestPast.end)) {
      latestPast = { season, start, end };
    }
  }

  if (live) return { ...live, phase: 'live' };
  if (nearestUpcoming) return { ...nearestUpcoming, phase: 'preseason' };
  if (latestPast) return { ...latestPast, phase: 'live' };
  return null;
}

async function main() {
  printSection('Config', {
    baseUrl: BASE_URL,
    imageBaseUrl: IMAGE_BASE_URL,
    note: 'Undocumented public feed behind wslfootball.com itself. No API key required.',
  });

  const competitionsResponse = await fetchJson(`${BASE_URL}/competitions`);
  const competitions = Array.isArray(competitionsResponse.body?.competitions)
    ? competitionsResponse.body.competitions
    : [];
  const wsl = competitions.find((c) => c.shortName === COMPETITION_SHORT_NAME) || null;

  printSection('Competition Discovery', {
    status: competitionsResponse.status,
    totalCompetitions: competitions.length,
    wslCompetitionId: wsl?.competitionId ?? null,
    allCompetitionNames: competitions.map((c) => c.shortName),
  });

  if (!wsl) {
    console.error('\nWSL competition not found in the feed. Treat this as a fallback trigger.');
    process.exit(1);
  }

  const seasonsResponse = await fetchJson(`${BASE_URL}/competitions/${wsl.competitionId}/seasons`);
  const seasons = Array.isArray(seasonsResponse.body?.seasons) ? seasonsResponse.body.seasons : [];
  const resolved = resolveCurrentSeason(seasons);

  printSection('Season Resolution', {
    status: seasonsResponse.status,
    seasonCount: seasons.length,
    chosenSeason: resolved
      ? {
          seasonId: resolved.season.seasonId,
          seasonName: resolved.season.seasonName,
          startDateUtc: resolved.season.startDateUtc,
          endDateUtc: resolved.season.endDateUtc,
          phase: resolved.phase,
        }
      : null,
  });

  if (!resolved) {
    console.error('\nCould not resolve a current WSL season. Treat this as a fallback trigger.');
    process.exit(1);
  }

  const standingsResponse = await fetchJson(
    `${BASE_URL}/seasons/${resolved.season.seasonId}/standings`
  );
  const teams = Array.isArray(standingsResponse.body?.teams) ? standingsResponse.body.teams : [];

  printSection('Standings', {
    status: standingsResponse.status,
    teamCount: teams.length,
    rows: teams.map((t) => {
      const get = (id) => t.stats.find((s) => s.statsId === id)?.statsValue ?? null;
      return {
        rank: get('rank'),
        team: t.officialName,
        played: get('matches-played'),
        points: get('points'),
        crest: t.imagery?.teamLogo ? IMAGE_BASE_URL + t.imagery.teamLogo : null,
      };
    }),
  });

  const matchesResponse = await fetchJson(`${BASE_URL}/seasons/${resolved.season.seasonId}/matches`);
  const matches = Array.isArray(matchesResponse.body?.matches) ? matchesResponse.body.matches : [];

  printSection('Matches', {
    status: matchesResponse.status,
    matchCount: matches.length,
    note:
      matchesResponse.status !== 200
        ? 'Non-200 is expected during preseason before fixtures are published; the PHP client treats this as "no upcoming match yet", not a hard error.'
        : undefined,
  });
}

main().catch((error) => {
  console.error(error instanceof Error ? error.stack || error.message : String(error));
  process.exit(1);
});
