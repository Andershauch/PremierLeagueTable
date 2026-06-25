import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const envPath = path.join(repoRoot, '.env.local');

const DEFAULT_API_KEY = '123';
const DEFAULT_BASE_URL = 'https://www.thesportsdb.com/api/v1/json';
const DEFAULT_LEAGUE_NAME = 'English_Womens_Super_League';
const DEFAULT_LEAGUE_ID = '4849';
const DEFAULT_SEASON = '2025-2026';
const DEFAULT_FOCUS_TEAM = 'Tottenham';
const DEFAULT_FOCUS_ALIASES = [
  'Tottenham',
  'Tottenham Women',
  'Tottenham Hotspur Women',
  'Tottenham WFC',
];

function loadEnvFile(filePath) {
  const env = {};

  if (!existsSync(filePath)) {
    return env;
  }

  const lines = readFileSync(filePath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    if (!line || /^\s*#/.test(line)) {
      continue;
    }

    const separatorIndex = line.indexOf('=');
    if (separatorIndex === -1) {
      continue;
    }

    const key = line.slice(0, separatorIndex).trim();
    const value = line.slice(separatorIndex + 1).trim();
    env[key] = value;
  }

  return env;
}

function getConfig(env) {
  return {
    apiKey: env.THESPORTSDB_API_KEY || DEFAULT_API_KEY,
    baseUrl: env.THESPORTSDB_BASE_URL || DEFAULT_BASE_URL,
    leagueName: env.THESPORTSDB_WSL_LEAGUE_NAME || DEFAULT_LEAGUE_NAME,
    leagueId: env.THESPORTSDB_WSL_LEAGUE_ID || DEFAULT_LEAGUE_ID,
    season: env.THESPORTSDB_WSL_SEASON || DEFAULT_SEASON,
    focusTeam: env.THESPORTSDB_WSL_FOCUS_TEAM || DEFAULT_FOCUS_TEAM,
  };
}

async function fetchJson(url) {
  const response = await fetch(url);
  const text = await response.text();

  let body;
  try {
    body = JSON.parse(text);
  } catch {
    body = { raw: text };
  }

  return {
    status: response.status,
    ok: response.ok,
    url,
    body,
  };
}

function buildUrl(baseUrl, apiKey, endpoint, query = {}) {
  const url = new URL(`${baseUrl}/${apiKey}/${endpoint}`);

  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') {
      continue;
    }

    url.searchParams.set(key, String(value));
  }

  return url.toString();
}

function summarizeTeams(teams) {
  return teams.slice(0, 10).map((team) => ({
    idTeam: team.idTeam ?? null,
    strTeam: team.strTeam ?? null,
    strLeague: team.strLeague ?? null,
    strGender: team.strGender ?? null,
    strBadge: team.strBadge ?? null,
  }));
}

function summarizeTableRows(rows) {
  return rows.slice(0, 5).map((row) => ({
    rank: row.intRank ?? null,
    team: row.strTeam ?? null,
    played: row.intPlayed ?? null,
    won: row.intWin ?? null,
    draw: row.intDraw ?? null,
    lost: row.intLoss ?? null,
    goalsFor: row.intGoalsFor ?? null,
    goalsAgainst: row.intGoalsAgainst ?? null,
    goalDifference: row.intGoalDifference ?? null,
    points: row.intPoints ?? null,
    updated: row.dateUpdated ?? null,
  }));
}

function summarizeEvents(events) {
  return events.slice(0, 5).map((event) => ({
    idEvent: event.idEvent ?? null,
    league: event.strLeague ?? null,
    season: event.strSeason ?? null,
    dateEvent: event.dateEvent ?? null,
    strTime: event.strTime ?? null,
    strTimestamp: event.strTimestamp ?? null,
    homeTeam: event.strHomeTeam ?? null,
    awayTeam: event.strAwayTeam ?? null,
    status: event.strStatus ?? null,
  }));
}

function findTottenhamWomen(teams) {
  return teams.find((team) => {
    const name = String(team.strTeam || '').toLowerCase();
    return name.includes('tottenham');
  }) || null;
}

async function runTeamSearches(config) {
  const aliases = [
    config.focusTeam,
    ...DEFAULT_FOCUS_ALIASES.filter((alias) => alias !== config.focusTeam),
  ];
  const uniqueAliases = [...new Set(aliases)];
  const results = [];

  for (const alias of uniqueAliases) {
    const url = buildUrl(config.baseUrl, config.apiKey, 'searchteams.php', {
      t: alias,
    });
    const response = await fetchJson(url);
    const teams = Array.isArray(response.body?.teams) ? response.body.teams : [];

    results.push({
      alias,
      url: response.url,
      status: response.status,
      count: teams.length,
      sample: summarizeTeams(teams),
    });
  }

  return results;
}

function printSection(title, payload) {
  console.log(`\n=== ${title} ===`);
  console.log(JSON.stringify(payload, null, 2));
}

async function main() {
  const env = loadEnvFile(envPath);
  const config = getConfig(env);

  const teamsUrl = buildUrl(config.baseUrl, config.apiKey, 'search_all_teams.php', {
    l: config.leagueName,
  });
  const tableUrl = buildUrl(config.baseUrl, config.apiKey, 'lookuptable.php', {
    l: config.leagueId,
    s: config.season,
  });
  const nextLeagueEventsUrl = buildUrl(config.baseUrl, config.apiKey, 'eventsnextleague.php', {
    id: config.leagueId,
  });

  const teamsResponse = await fetchJson(teamsUrl);
  const tableResponse = await fetchJson(tableUrl);
  const nextEventsResponse = await fetchJson(nextLeagueEventsUrl);
  const focusTeamSearchResults = await runTeamSearches(config);

  const teams = Array.isArray(teamsResponse.body?.teams) ? teamsResponse.body.teams : [];
  const table = Array.isArray(tableResponse.body?.table) ? tableResponse.body.table : [];
  const nextEvents = Array.isArray(nextEventsResponse.body?.events) ? nextEventsResponse.body.events : [];

  const tottenhamWomen = findTottenhamWomen(teams);
  const tableFocusRow = table.find((row) => {
    const teamName = String(row.strTeam || '').toLowerCase();
    return teamName.includes('tottenham');
  }) || null;
  const explicitWomenSearch = focusTeamSearchResults.find((result) =>
    result.sample.some((team) => {
      const name = String(team.strTeam || '').toLowerCase();
      const league = String(team.strLeague || '').toLowerCase();
      const gender = String(team.strGender || '').toLowerCase();

      return name.includes('tottenham') && gender === 'female' && league.includes('womens super league');
    })
  ) || null;

  printSection('Config', {
    baseUrl: config.baseUrl,
    apiKeySource: config.apiKey === DEFAULT_API_KEY ? 'free default key 123' : 'custom env key',
    leagueName: config.leagueName,
    leagueId: config.leagueId,
    season: config.season,
    focusTeam: config.focusTeam,
  });

  printSection('League Teams', {
    url: teamsResponse.url,
    status: teamsResponse.status,
    count: teams.length,
    tottenhamWomen: tottenhamWomen
      ? {
          idTeam: tottenhamWomen.idTeam ?? null,
          strTeam: tottenhamWomen.strTeam ?? null,
          strLeague: tottenhamWomen.strLeague ?? null,
        }
      : null,
    sample: summarizeTeams(teams),
  });

  printSection('League Table', {
    url: tableResponse.url,
    status: tableResponse.status,
    count: table.length,
    focusTeamRow: tableFocusRow
      ? {
          rank: tableFocusRow.intRank ?? null,
          team: tableFocusRow.strTeam ?? null,
          points: tableFocusRow.intPoints ?? null,
        }
      : null,
    sample: summarizeTableRows(table),
  });

  printSection('Next League Events', {
    url: nextEventsResponse.url,
    status: nextEventsResponse.status,
    count: nextEvents.length,
    sample: summarizeEvents(nextEvents),
  });

  printSection('Focus Team Search', focusTeamSearchResults);

  const nextEventTeamId = tottenhamWomen?.idTeam || explicitWomenSearch?.sample?.[0]?.idTeam || null;

  if (nextEventTeamId) {
    const nextTeamEventsUrl = buildUrl(config.baseUrl, config.apiKey, 'eventsnext.php', {
      id: nextEventTeamId,
    });
    const nextTeamEventsResponse = await fetchJson(nextTeamEventsUrl);
    const nextTeamEvents = Array.isArray(nextTeamEventsResponse.body?.events)
      ? nextTeamEventsResponse.body.events
      : [];

    printSection('Tottenham Women Next Events', {
      url: nextTeamEventsResponse.url,
      status: nextTeamEventsResponse.status,
      count: nextTeamEvents.length,
      teamId: nextEventTeamId,
      sample: summarizeEvents(nextTeamEvents),
    });
  } else {
    printSection('Tottenham Women Next Events', {
      skipped: true,
      reason: 'No Tottenham Women team found in league-team discovery.',
    });
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.stack || error.message : String(error));
  process.exit(1);
});
