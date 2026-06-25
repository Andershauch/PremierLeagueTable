import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const envPath = path.join(repoRoot, '.env.local');
const DEFAULT_API_KEY = '123';
const DEFAULT_BASE_URL = 'https://www.thesportsdb.com/api/v1/json';
const DEFAULT_LEAGUE_NAME = 'English_Womens_Super_League';
const DEFAULT_LEAGUE_ID = '4849';

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

    env[line.slice(0, separatorIndex).trim()] = line.slice(separatorIndex + 1).trim();
  }

  return env;
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

async function fetchJson(url) {
  const response = await fetch(url);
  const text = await response.text();

  return {
    status: response.status,
    body: text === '' ? null : JSON.parse(text),
  };
}

function toInt(value) {
  if (value === null || value === undefined || value === '') {
    return null;
  }

  const parsed = Number.parseInt(String(value), 10);
  return Number.isFinite(parsed) ? parsed : null;
}

function countCompletedEvents(events) {
  return events.filter((event) =>
    toInt(event?.intHomeScore) !== null && toInt(event?.intAwayScore) !== null
  ).length;
}

function getExpectedTeamsForSeason(season) {
  const startYear = Number.parseInt(String(season).slice(0, 4), 10);

  if (startYear >= 2026) {
    return [
      'Arsenal WFC',
      'Aston Villa WFC',
      'Birmingham City WFC',
      'Brighton WFC',
      'Chelsea Women',
      'Crystal Palace Women',
      'Everton FC Women',
      'Liverpool FC Women',
      'London City Lionesses',
      'Manchester City WFC',
      'Manchester United WFC',
      'Tottenham Women',
      'West Ham Women',
      'Charlton Athletic Women',
    ];
  }

  return [
    'Arsenal WFC',
    'Aston Villa WFC',
    'Brighton WFC',
    'Chelsea Women',
    'Everton FC Women',
    'Leicester City WFC',
    'Liverpool FC Women',
    'London City Lionesses',
    'Manchester City WFC',
    'Manchester United WFC',
    'Tottenham Women',
    'West Ham Women',
  ];
}

async function verifySeason(config, season) {
  const teamsResponse = await fetchJson(buildUrl(config.baseUrl, config.apiKey, 'search_all_teams.php', {
    l: config.leagueName,
  }));
  const eventsResponse = await fetchJson(buildUrl(config.baseUrl, config.apiKey, 'eventsseason.php', {
    id: config.leagueId,
    s: season,
  }));

  const teams = Array.isArray(teamsResponse.body?.teams) ? teamsResponse.body.teams : [];
  const events = Array.isArray(eventsResponse.body?.events) ? eventsResponse.body.events : [];
  const completedEventCount = countCompletedEvents(events);
  const dataMode = completedEventCount > 0 ? 'live' : 'preseason';
  const providerTeamNames = teams.map((team) => String(team?.strTeam || '').trim()).filter(Boolean);
  const expectedTeamNames = getExpectedTeamsForSeason(season);
  const missingFromProvider = expectedTeamNames.filter((team) => !providerTeamNames.includes(team));
  const unexpectedFromProvider = providerTeamNames.filter((team) => !expectedTeamNames.includes(team));

  return {
    season,
    dataMode,
    providerTeamCount: providerTeamNames.length,
    expectedTeamCount: expectedTeamNames.length,
    completedEventCount,
    missingFromProvider,
    unexpectedFromProvider,
  };
}

async function main() {
  const env = loadEnvFile(envPath);
  const config = {
    apiKey: env.THESPORTSDB_API_KEY || DEFAULT_API_KEY,
    baseUrl: env.THESPORTSDB_BASE_URL || DEFAULT_BASE_URL,
    leagueName: env.THESPORTSDB_WSL_LEAGUE_NAME || DEFAULT_LEAGUE_NAME,
    leagueId: env.THESPORTSDB_WSL_LEAGUE_ID || DEFAULT_LEAGUE_ID,
  };

  const seasons = ['2025-2026', '2026-2027'];
  const results = [];

  for (const season of seasons) {
    results.push(await verifySeason(config, season));
  }

  console.log(JSON.stringify({
    checkedAt: new Date().toISOString(),
    config: {
      baseUrl: config.baseUrl,
      leagueName: config.leagueName,
      leagueId: config.leagueId,
      apiKeySource: config.apiKey === DEFAULT_API_KEY ? 'free default key 123' : 'custom env key',
    },
    results,
  }, null, 2));
}

main().catch((error) => {
  console.error(error instanceof Error ? error.stack || error.message : String(error));
  process.exit(1);
});
