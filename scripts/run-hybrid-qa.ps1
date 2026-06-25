param(
    [switch] $SkipIntro
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $RepoRoot

$Checks = @(
    @{
        Label = 'TheSportsDB WSL raw coverage';
        Command = 'node';
        Arguments = @('.\scripts\check-thesportsdb-wsl.mjs');
    },
    @{
        Label = 'Derived WSL table prototype';
        Command = 'node';
        Arguments = @('.\scripts\prototype-thesportsdb-wsl-table.mjs');
    },
    @{
        Label = 'WSL season mode verification';
        Command = 'node';
        Arguments = @('.\scripts\verify-thesportsdb-wsl-mode.mjs');
    },
    @{
        Label = 'Club mapping verification';
        Command = 'node';
        Arguments = @('.\scripts\verify-club-map.mjs');
    },
    @{
        Label = 'WSL next-match alias verification';
        Command = 'node';
        Arguments = @('.\scripts\verify-thesportsdb-next-match.mjs');
    }
)

if (-not $SkipIntro) {
    Write-Host 'Running hybrid PL + WSL QA checks...'
    Write-Host "Repo: $RepoRoot"
}

foreach ($Check in $Checks) {
    Write-Host ''
    Write-Host ('=== ' + $Check.Label + ' ===')
    & $Check.Command @($Check.Arguments)
    if ($LASTEXITCODE -ne 0) {
        throw "QA check failed: $($Check.Label)"
    }
}

Write-Host ''
Write-Host 'Hybrid QA checks completed successfully.'
