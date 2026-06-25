const BASE_URL = 'https://www.thesportsdb.com/api/v1/json/123';

function buildUrl(endpoint, query = {}) {
  const url = new URL(`${BASE_URL}/${endpoint}`);

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') {
      continue;
    }

    url.searchParams.set(key, String(value));
  }

  return url.toString();
}

async function fetchJson(url) {
  const response = await fetch(url);
  const text = await response.text();
  return {
    status: response.status,
    body: text === '' ? null : JSON.parse(text),
  };
}

function normalize(name) {
  return String(name || '')
    .toLowerCase()
    .replace(/\b(fc|afc|cf|wfc)\b/g, ' ')
    .replace(/[^a-z0-9 ]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function matchTeam(teams, query) {
  const target = normalize(query);

  return teams.find((team) => {
    const league = String(team?.strLeague || '').toLowerCase();
    const gender = String(team?.strGender || '').toLowerCase();
    const current = normalize(team?.strTeam);

    if (gender !== 'female' || !league.includes('womens super league')) {
      return false;
    }

    return current === target || current.includes(target) || target.includes(current);
  }) || null;
}

async function checkAlias(alias) {
  const direct = await fetchJson(buildUrl('searchteams.php', { t: alias }));
  const directTeams = Array.isArray(direct.body?.teams) ? direct.body.teams : [];
  const directMatch = matchTeam(directTeams, alias);

  return {
    alias,
    directStatus: direct.status,
    directCount: directTeams.length,
    directMatch: directMatch ? {
      idTeam: directMatch.idTeam,
      strTeam: directMatch.strTeam,
    } : null,
  };
}

async function main() {
  const aliases = [
    'Tottenham Women',
    'Tottenham Hotspur Women',
    'West Ham Women',
    'Arsenal WFC',
    'Chelsea Women',
    'Manchester United WFC',
  ];

  const results = [];
  for (const alias of aliases) {
    results.push(await checkAlias(alias));
  }

  const leagueTeams = await fetchJson(buildUrl('search_all_teams.php', { l: 'English_Womens_Super_League' }));
  const leagueTeamNames = Array.isArray(leagueTeams.body?.teams)
    ? leagueTeams.body.teams.map((team) => team?.strTeam).filter(Boolean)
    : [];

  console.log(JSON.stringify({
    checkedAt: new Date().toISOString(),
    aliases: results,
    leagueTeamCount: leagueTeamNames.length,
    leagueTeamNames,
  }, null, 2));
}

main().catch((error) => {
  console.error(error instanceof Error ? error.stack || error.message : String(error));
  process.exit(1);
});
