param(
    [string] $Message = 'chore: update plugin',
    # Leave empty to auto-detect the Local site (see scripts/lib/local-site.ps1).
    [string] $LocalPluginPath = '',
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
