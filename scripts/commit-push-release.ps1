param(
    [string] $Message = 'chore: update plugin',
    [string] $LocalPluginPath = 'C:\Users\ander\Local Sites\whitehartdanes\app\public\wp-content\plugins\premier-league-table',
    [switch] $SkipLocalUpdate
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $RepoRoot

$ReleaseArgs = @{}
if ($LocalPluginPath) {
    $ReleaseArgs.LocalPluginPath = $LocalPluginPath
}
if ($SkipLocalUpdate) {
    $ReleaseArgs.SkipLocalUpdate = $true
}

& (Join-Path $PSScriptRoot 'release.ps1') @ReleaseArgs

$Status = git status --short
if ($Status) {
    git add -A
    git commit -m $Message
} else {
    Write-Host 'No tracked repository changes to commit.'
}

git push
