import { readFileSync, existsSync } from 'node:fs';
import path from 'node:path';

const repoRoot = process.cwd();
const envPath = path.join(repoRoot, '.env.local');
const baseUrl = 'https://api.football-data.org/v4';
const defaultCompetitionCandidates = ['WSL', 'BWSL'];

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

function getFootballDataKey(env) {
  const candidateKeys = [
    'FOOTBALL_DATA_API_KEY',
    'FOOTBALL_DATA_ORG_KEY',
  ];

  for (const keyName of candidateKeys) {
    const value = env[keyName];
    if (typeof value === 'string' && value.trim() !== '') {
      return value.trim();
    }
  }

  return '';
}

async function apiGet(apiKey, resourcePath, query = {}) {
  const url = new URL(baseUrl + resourcePath);
  for (const [key, value] of Object.entries(query)) {
    if (value === undefined || value === null || value === '') {
      continue;
    }

    url.searchParams.set(key, String(value));
  }

  const response = await fetch(url, {
    headers: {
      'X-Auth-Token': apiKey,
      Accept: 'application/json',
    },
  });

  const bodyText = await response.text();
  let body;

  try {
    body = JSON.parse(bodyText);
  } catch {
    body = bodyText;
  }

  return {
    ok: response.ok,
    status: response.status,
    url: url.toString(),
    body,
  };
}

function printSection(title) {
  console.log(`\n=== ${title} ===`);
}

function summarizeCompetition(competition) {
  return {
    id: competition?.id ?? null,
    code: competition?.code ?? null,
    name: competition?.name ?? null,
    type: competition?.type ?? null,
    area: competition?.area?.name ?? null,
    currentSeason: competition?.currentSeason
      ? {
          id: competition.currentSeason.id ?? null,
          startDate: competition.currentSeason.startDate ?? null,
          endDate: competition.currentSeason.endDate ?? null,
          currentMatchday: competition.currentSeason.currentMatchday ?? null,
        }
      : null,
  };
}

function findWomenCompetitionCandidates(competitions) {
  return competitions.filter((competition) => {
    const areaName = String(competition?.area?.name ?? '').toLowerCase();
    const name = String(competition?.name ?? '').toLowerCase();
    return areaName.includes('england') && name.includes('women');
  });
}

async function checkCompetition(apiKey, competitionCode) {
  const overview = await apiGet(apiKey, `/competitions/${competitionCode}`);
  const standings = await apiGet(apiKey, `/competitions/${competitionCode}/standings`);
  const matches = await apiGet(apiKey, `/competitions/${competitionCode}/matches`, {
    status: 'SCHEDULED',
  });

  return {
    code: competitionCode,
    overview: {
      status: overview.status,
      summary: typeof overview.body === 'object' && overview.body !== null
        ? summarizeCompetition(overview.body)
        : overview.body,
    },
    standings: {
      status: standings.status,
      rowCount: Array.isArray(standings.body?.standings)
        ? standings.body.standings.reduce((count, block) => {
            const rows = Array.isArray(block?.table) ? block.table.length : 0;
            return count + rows;
          }, 0)
        : 0,
      error: standings.body?.message ?? null,
    },
    matches: {
      status: matches.status,
      count: Array.isArray(matches.body?.matches) ? matches.body.matches.length : 0,
      firstMatch: Array.isArray(matches.body?.matches) && matches.body.matches[0]
        ? {
            utcDate: matches.body.matches[0].utcDate ?? null,
            homeTeam: matches.body.matches[0].homeTeam?.name ?? null,
            awayTeam: matches.body.matches[0].awayTeam?.name ?? null,
            competition: matches.body.matches[0].competition?.name ?? null,
          }
        : null,
      error: matches.body?.message ?? null,
    },
  };
}

async function main() {
  const env = loadEnvFile(envPath);
  const apiKey = getFootballDataKey(env);

  if (!apiKey) {
    console.error(
      'Missing football-data.org key in .env.local. Add FOOTBALL_DATA_API_KEY=... or FOOTBALL_DATA_ORG_KEY=...'
    );
    process.exit(1);
  }

  printSection('Football-Data Status');
  const plCheck = await apiGet(apiKey, '/competitions/PL/standings');
  console.log(
    JSON.stringify(
      {
        url: plCheck.url,
        status: plCheck.status,
        competition: plCheck.body?.competition?.name ?? null,
        season: plCheck.body?.season
          ? {
              id: plCheck.body.season.id ?? null,
              startDate: plCheck.body.season.startDate ?? null,
              endDate: plCheck.body.season.endDate ?? null,
              currentMatchday: plCheck.body.season.currentMatchday ?? null,
            }
          : null,
        standingsBlocks: Array.isArray(plCheck.body?.standings) ? plCheck.body.standings.length : 0,
        error: plCheck.body?.message ?? null,
      },
      null,
      2
    )
  );

  printSection('Competition Discovery');
  const competitionsResponse = await apiGet(apiKey, '/competitions');
  const competitions = Array.isArray(competitionsResponse.body?.competitions)
    ? competitionsResponse.body.competitions
    : [];
  const candidateCompetitions = findWomenCompetitionCandidates(competitions);

  console.log(
    JSON.stringify(
      {
        url: competitionsResponse.url,
        status: competitionsResponse.status,
        totalCompetitions: competitions.length,
        englandWomenCandidates: candidateCompetitions.map(summarizeCompetition),
      },
      null,
      2
    )
  );

  const explicitCodes = process.argv.slice(2).filter(Boolean);
  const discoveredCodes = candidateCompetitions
    .map((competition) => String(competition?.code ?? '').trim())
    .filter(Boolean);
  const codesToCheck = [...new Set([...explicitCodes, ...discoveredCodes, ...defaultCompetitionCandidates])];

  printSection('WSL Candidate Checks');

  for (const competitionCode of codesToCheck) {
    const result = await checkCompetition(apiKey, competitionCode);
    console.log(JSON.stringify(result, null, 2));
  }
}

main().catch((error) => {
  console.error(error instanceof Error ? error.message : String(error));
  process.exit(1);
});
