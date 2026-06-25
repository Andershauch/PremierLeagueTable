const clubCatalog = {
  'arsenal': { pl: 'Arsenal', wsl: 'Arsenal WFC' },
  'aston-villa': { pl: 'Aston Villa', wsl: 'Aston Villa WFC' },
  'brighton-hove-albion': { pl: 'Brighton & Hove Albion', wsl: 'Brighton WFC' },
  'chelsea': { pl: 'Chelsea', wsl: 'Chelsea Women' },
  'crystal-palace': { pl: 'Crystal Palace', wsl: 'Crystal Palace Women' },
  'everton': { pl: 'Everton', wsl: 'Everton FC Women' },
  'leicester-city': { pl: 'Leicester City', wsl: 'Leicester City WFC' },
  'liverpool': { pl: 'Liverpool', wsl: 'Liverpool FC Women' },
  'manchester-city': { pl: 'Manchester City', wsl: 'Manchester City WFC' },
  'manchester-united': { pl: 'Manchester United', wsl: 'Manchester United WFC' },
  'tottenham-hotspur': { pl: 'Tottenham Hotspur', wsl: 'Tottenham Women' },
  'west-ham-united': { pl: 'West Ham United', wsl: 'West Ham Women' },
};

const unsupportedWslPairs = {
  'newcastle-united': { pl: 'Newcastle United', wsl: '' },
  'nottingham-forest': { pl: 'Nottingham Forest', wsl: '' },
  'brentford': { pl: 'Brentford', wsl: '' },
  'bournemouth': { pl: 'AFC Bournemouth', wsl: '' },
  'fulham': { pl: 'Fulham', wsl: '' },
  'burnley': { pl: 'Burnley', wsl: '' },
  'wolverhampton-wanderers': { pl: 'Wolverhampton Wanderers', wsl: '' },
};

console.log(JSON.stringify({
  checkedAt: new Date().toISOString(),
  mappedBothCompetitions: Object.keys(clubCatalog).length,
  plOnlyMappings: Object.keys(unsupportedWslPairs).length,
  mappedClubs: clubCatalog,
  unsupportedWslPairs,
}, null, 2));
