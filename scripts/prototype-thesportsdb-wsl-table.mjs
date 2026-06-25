const API_KEY = '123';
const BASE_URL = `https://www.thesportsdb.com/api/v1/json/${API_KEY}`;
const LEAGUE_ID = '4849';
const LEAGUE_NAME = 'English_Womens_Super_League';
const SEASON = '2025-2026';

async function fetchJson(url) {
  const response = await fetch(url);
  const body = await response.json();
  return body;
}

function createEmptyRow(team) {
  return {
    idTeam: team.idTeam,
    strTeam: team.strTeam,
    strBadge: team.strBadge,
    intRank: 0,
    intPlayed: 0,
    intWin: 0,
    intDraw: 0,
    intLoss: 0,
    intGoalsFor: 0,
    intGoalsAgainst: 0,
    intGoalDifference: 0,
    intPoints: 0,
  };
}

function ensureRow(tableMap, fallbackTeamId, fallbackTeamName) {
  if (!tableMap.has(fallbackTeamId)) {
    tableMap.set(fallbackTeamId, createEmptyRow({
      idTeam: fallbackTeamId,
      strTeam: fallbackTeamName,
      strBadge: '',
    }));
  }

  return tableMap.get(fallbackTeamId);
}

function toNumber(value) {
  const parsed = Number.parseInt(String(value), 10);
  return Number.isFinite(parsed) ? parsed : null;
}

function isCompletedEvent(event) {
  const home = toNumber(event.intHomeScore);
  const away = toNumber(event.intAwayScore);
  return home !== null && away !== null;
}

function applyEvent(tableMap, event) {
  const homeId = String(event.idHomeTeam || '').trim();
  const awayId = String(event.idAwayTeam || '').trim();
  const homeName = String(event.strHomeTeam || '').trim();
  const awayName = String(event.strAwayTeam || '').trim();
  const homeScore = toNumber(event.intHomeScore);
  const awayScore = toNumber(event.intAwayScore);

  if (!homeId || !awayId || homeScore === null || awayScore === null) {
    return;
  }

  const home = ensureRow(tableMap, homeId, homeName);
  const away = ensureRow(tableMap, awayId, awayName);

  home.intPlayed += 1;
  away.intPlayed += 1;

  home.intGoalsFor += homeScore;
  home.intGoalsAgainst += awayScore;
  away.intGoalsFor += awayScore;
  away.intGoalsAgainst += homeScore;

  if (homeScore > awayScore) {
    home.intWin += 1;
    away.intLoss += 1;
    home.intPoints += 3;
  } else if (homeScore < awayScore) {
    away.intWin += 1;
    home.intLoss += 1;
    away.intPoints += 3;
  } else {
    home.intDraw += 1;
    away.intDraw += 1;
    home.intPoints += 1;
    away.intPoints += 1;
  }
}

function finalizeTable(rows) {
  rows.forEach((row) => {
    row.intGoalDifference = row.intGoalsFor - row.intGoalsAgainst;
  });

  rows.sort((a, b) => {
    return (
      b.intPoints - a.intPoints ||
      b.intGoalDifference - a.intGoalDifference ||
      b.intGoalsFor - a.intGoalsFor ||
      a.strTeam.localeCompare(b.strTeam)
    );
  });

  rows.forEach((row, index) => {
    row.intRank = index + 1;
  });

  return rows;
}

async function main() {
  const [teamsPayload, eventsPayload, tablePayload] = await Promise.all([
    fetchJson(`${BASE_URL}/search_all_teams.php?l=${encodeURIComponent(LEAGUE_NAME)}`),
    fetchJson(`${BASE_URL}/eventsseason.php?id=${LEAGUE_ID}&s=${encodeURIComponent(SEASON)}`),
    fetchJson(`${BASE_URL}/lookuptable.php?l=${LEAGUE_ID}&s=${encodeURIComponent(SEASON)}`),
  ]);

  const teams = Array.isArray(teamsPayload.teams) ? teamsPayload.teams : [];
  const events = Array.isArray(eventsPayload.events) ? eventsPayload.events : [];
  const providerTable = Array.isArray(tablePayload.table) ? tablePayload.table : [];

  const completedEvents = events.filter(isCompletedEvent);
  const tableMap = new Map();

  teams.forEach((team) => {
    if (!team?.idTeam) {
      return;
    }

    tableMap.set(String(team.idTeam), createEmptyRow(team));
  });

  completedEvents.forEach((event) => applyEvent(tableMap, event));

  const derivedTable = finalizeTable(Array.from(tableMap.values()));

  console.log(JSON.stringify({
    leagueId: LEAGUE_ID,
    season: SEASON,
    providerTeamCount: teams.length,
    providerTableCount: providerTable.length,
    eventCount: events.length,
    completedEventCount: completedEvents.length,
    derivedTableCount: derivedTable.length,
    providerTableTeams: providerTable.map((row) => row.strTeam),
    derivedTop12: derivedTable.slice(0, 12).map((row) => ({
      rank: row.intRank,
      team: row.strTeam,
      played: row.intPlayed,
      won: row.intWin,
      draw: row.intDraw,
      lost: row.intLoss,
      goalsFor: row.intGoalsFor,
      goalsAgainst: row.intGoalsAgainst,
      goalDifference: row.intGoalDifference,
      points: row.intPoints,
    })),
  }, null, 2));
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
