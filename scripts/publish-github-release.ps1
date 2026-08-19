param(
    # Optional. Defaults to the version in premier-league-table.php, which is
    # the single source of truth the release workflow validates against.
    [string] $Version = '',
    [switch] $Force
)

# Tags the current commit and pushes the tag, which triggers
# .github/workflows/release.yml to build premier-league-table.zip and publish it
# as a GitHub Release. Installed sites pick that release up through the plugin's
# own updater, so this script is the whole "ship it" step.

$ErrorActionPreference = 'Stop'

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $RepoRoot

$MainPluginFile = Join-Path $RepoRoot 'premier-league-table.php'
$ReadmeFile = Join-Path $RepoRoot 'readme.txt'
$ChangelogFile = Join-Path $RepoRoot 'CHANGELOG.md'

$PluginHeader = Get-Content -Path $MainPluginFile -Raw

if ($PluginHeader -notmatch '(?m)^\s*\*\s*Version:\s*([0-9A-Za-z.+-]+)\s*$') {
    throw 'Could not find a valid Version header in premier-league-table.php'
}
$HeaderVersion = $Matches[1]

if ($PluginHeader -notmatch "define\('PLT_VERSION',\s*'([0-9A-Za-z.+-]+)'\)") {
    throw 'Could not find a PLT_VERSION constant in premier-league-table.php'
}
$ConstVersion = $Matches[1]

if (-not $Version) {
    $Version = $HeaderVersion
}

if ($HeaderVersion -ne $Version) {
    throw "Version mismatch: plugin header is $HeaderVersion, requested tag is $Version"
}
if ($ConstVersion -ne $Version) {
    throw "Version mismatch: PLT_VERSION is $ConstVersion, requested tag is $Version"
}

$Readme = Get-Content -Path $ReadmeFile -Raw
if ($Readme -match '(?m)^Stable tag:\s*([0-9A-Za-z.+-]+)\s*$') {
    if ($Matches[1] -ne $Version) {
        throw "Version mismatch: readme.txt Stable tag is $($Matches[1]), requested tag is $Version"
    }
}

$Changelog = Get-Content -Path $ChangelogFile -Raw
if ($Changelog -notmatch "(?m)^##\s+\[?$([regex]::Escape($Version))\]?") {
    throw "CHANGELOG.md has no '## $Version' section. The release notes are taken from it, so add one first."
}

$Status = git status --short
if ($Status -and -not $Force) {
    Write-Host $Status
    throw 'Working tree is not clean. Commit first, or re-run with -Force if you know the leftovers are irrelevant.'
}

$Tag = "v$Version"

$ExistingTag = git tag --list $Tag
if ($ExistingTag) {
    throw "Tag $Tag already exists. Bump the version instead of retagging a published release."
}

Write-Host "Tagging $Tag and pushing..."
git tag -a $Tag -m "Release $Version"
git push origin $Tag

Write-Host ''
Write-Host "Pushed $Tag. GitHub Actions is now building and publishing the release."
Write-Host "Watch it:      gh run watch"
Write-Host "Then verify:   gh release view $Tag"
