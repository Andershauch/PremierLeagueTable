<?php

if (! defined('ABSPATH')) {
    exit;
}

class PLT_Club_Map
{
    /**
     * Resolve one saved club selection into a canonical internal key that can later
     * map cleanly to both men's and women's provider identities.
     */
    public function resolve_canonical_key(string $team_name): string
    {
        $normalized = $this->normalize($team_name);
        if ($normalized === '') {
            return '';
        }

        foreach ($this->get_club_catalog() as $canonical_key => $club) {
            $aliases = isset($club['aliases']) && is_array($club['aliases']) ? $club['aliases'] : [];
            foreach ($aliases as $alias) {
                if ($normalized === $this->normalize((string) $alias)) {
                    return $canonical_key;
                }
            }
        }

        return $normalized;
    }

    public function get_display_team_name(string $canonical_key, string $competition_key, string $fallback = ''): string
    {
        $canonical_key = $this->normalize($canonical_key);
        $competition_key = strtolower(trim($competition_key));
        $catalog = $this->get_club_catalog();

        if (
            isset($catalog[$canonical_key]['providers']) &&
            is_array($catalog[$canonical_key]['providers']) &&
            isset($catalog[$canonical_key]['providers'][$competition_key])
        ) {
            return (string) $catalog[$canonical_key]['providers'][$competition_key];
        }

        return trim($fallback);
    }

    public function get_provider_team_name(string $canonical_key, string $competition_key, string $fallback = ''): string
    {
        return $this->get_display_team_name($canonical_key, $competition_key, $fallback);
    }

    public function has_competition_mapping(string $canonical_key, string $competition_key): bool
    {
        $canonical_key = $this->normalize($canonical_key);
        $competition_key = strtolower(trim($competition_key));
        $catalog = $this->get_club_catalog();

        return isset($catalog[$canonical_key]['providers'][$competition_key])
            && trim((string) $catalog[$canonical_key]['providers'][$competition_key]) !== '';
    }

    private function get_club_catalog(): array
    {
        return [
            'arsenal' => [
                'aliases' => ['Arsenal', 'Arsenal FC', 'Arsenal WFC', 'Arsenal Women'],
                'providers' => ['pl' => 'Arsenal', 'wsl' => 'Arsenal WFC'],
            ],
            'aston-villa' => [
                'aliases' => ['Aston Villa', 'Aston Villa FC', 'Aston Villa WFC', 'Aston Villa Women'],
                'providers' => ['pl' => 'Aston Villa', 'wsl' => 'Aston Villa WFC'],
            ],
            'birmingham-city' => [
                'aliases' => ['Birmingham City', 'Birmingham City FC', 'Birmingham City WFC', 'Birmingham City Women'],
                'providers' => ['pl' => 'Birmingham City', 'wsl' => 'Birmingham City WFC'],
            ],
            'bournemouth' => [
                'aliases' => ['AFC Bournemouth', 'Bournemouth'],
                'providers' => ['pl' => 'AFC Bournemouth', 'wsl' => ''],
            ],
            'brentford' => [
                'aliases' => ['Brentford', 'Brentford FC'],
                'providers' => ['pl' => 'Brentford', 'wsl' => ''],
            ],
            'brighton-hove-albion' => [
                'aliases' => ['Brighton', 'Brighton & Hove Albion', 'Brighton FC', 'Brighton WFC', 'Brighton Women'],
                'providers' => ['pl' => 'Brighton & Hove Albion', 'wsl' => 'Brighton WFC'],
            ],
            'burnley' => [
                'aliases' => ['Burnley', 'Burnley FC'],
                'providers' => ['pl' => 'Burnley', 'wsl' => ''],
            ],
            'charlton-athletic' => [
                'aliases' => ['Charlton', 'Charlton Athletic', 'Charlton Athletic Women'],
                'providers' => ['pl' => 'Charlton Athletic', 'wsl' => 'Charlton Athletic Women'],
            ],
            'chelsea' => [
                'aliases' => ['Chelsea', 'Chelsea FC', 'Chelsea Women'],
                'providers' => ['pl' => 'Chelsea', 'wsl' => 'Chelsea Women'],
            ],
            'crystal-palace' => [
                'aliases' => ['Crystal Palace', 'Crystal Palace FC', 'Crystal Palace Women'],
                'providers' => ['pl' => 'Crystal Palace', 'wsl' => 'Crystal Palace Women'],
            ],
            'everton' => [
                'aliases' => ['Everton', 'Everton FC', 'Everton FC Women', 'Everton Women'],
                'providers' => ['pl' => 'Everton', 'wsl' => 'Everton FC Women'],
            ],
            'fulham' => [
                'aliases' => ['Fulham', 'Fulham FC'],
                'providers' => ['pl' => 'Fulham', 'wsl' => ''],
            ],
            'leeds-united' => [
                'aliases' => ['Leeds', 'Leeds United', 'Leeds United FC'],
                'providers' => ['pl' => 'Leeds United', 'wsl' => ''],
            ],
            'leicester-city' => [
                'aliases' => ['Leicester', 'Leicester City', 'Leicester City WFC', 'Leicester Women'],
                'providers' => ['pl' => 'Leicester City', 'wsl' => 'Leicester City WFC'],
            ],
            'liverpool' => [
                'aliases' => ['Liverpool', 'Liverpool FC', 'Liverpool FC Women', 'Liverpool Women'],
                'providers' => ['pl' => 'Liverpool', 'wsl' => 'Liverpool FC Women'],
            ],
            'london-city-lionesses' => [
                'aliases' => ['London City Lionesses'],
                'providers' => ['pl' => '', 'wsl' => 'London City Lionesses'],
            ],
            'manchester-city' => [
                'aliases' => ['Manchester City', 'Man City', 'Manchester City FC', 'Manchester City WFC', 'Manchester City Women'],
                'providers' => ['pl' => 'Manchester City', 'wsl' => 'Manchester City WFC'],
            ],
            'manchester-united' => [
                'aliases' => ['Manchester United', 'Man Utd', 'Manchester United FC', 'Manchester United WFC', 'Manchester United Women'],
                'providers' => ['pl' => 'Manchester United', 'wsl' => 'Manchester United WFC'],
            ],
            'newcastle-united' => [
                'aliases' => ['Newcastle', 'Newcastle United', 'Newcastle United FC'],
                'providers' => ['pl' => 'Newcastle United', 'wsl' => ''],
            ],
            'nottingham-forest' => [
                'aliases' => ['Forest', 'Nottingham Forest', 'Nottingham Forest FC'],
                'providers' => ['pl' => 'Nottingham Forest', 'wsl' => ''],
            ],
            'sunderland' => [
                'aliases' => ['Sunderland', 'Sunderland AFC'],
                'providers' => ['pl' => 'Sunderland', 'wsl' => ''],
            ],
            'tottenham-hotspur' => [
                'aliases' => ['Tottenham', 'Tottenham Hotspur', 'Spurs', 'Tottenham Women', 'Tottenham Hotspur Women'],
                'providers' => ['pl' => 'Tottenham Hotspur', 'wsl' => 'Tottenham Women'],
            ],
            'west-ham-united' => [
                'aliases' => ['West Ham', 'West Ham United', 'West Ham Women'],
                'providers' => ['pl' => 'West Ham United', 'wsl' => 'West Ham Women'],
            ],
            'wolverhampton-wanderers' => [
                'aliases' => ['Wolves', 'Wolverhampton Wanderers'],
                'providers' => ['pl' => 'Wolverhampton Wanderers', 'wsl' => ''],
            ],
        ];
    }

    private function normalize(string $value): string
    {
        $value = remove_accents(strtolower(trim($value)));
        $value = preg_replace('/\b(fc|afc|cf|wfc)\b/u', ' ', $value);
        $value = preg_replace('/[^a-z0-9 ]+/u', ' ', (string) $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return str_replace(' ', '-', trim((string) $value));
    }
}
