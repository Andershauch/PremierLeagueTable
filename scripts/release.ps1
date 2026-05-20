param(
    [string] $LocalPluginPath = 'C:\Users\ander\Local Sites\whitehartdanes\app\public\wp-content\plugins\premier-league-table',
    [switch] $SkipLocalUpdate
)

$ErrorActionPreference = 'Stop'

$PluginSlug = 'premier-league-table'
$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
$MainPluginFile = Join-Path $RepoRoot 'premier-league-table.php'
$ReadmeFile = Join-Path $RepoRoot 'readme.txt'
$ReleaseDir = Join-Path $RepoRoot '.release'
$BuildRoot = Join-Path $ReleaseDir 'build-work'
$StageDir = Join-Path $BuildRoot $PluginSlug

if (-not (Test-Path $MainPluginFile)) {
    throw "Main plugin file not found: $MainPluginFile"
}

$PluginHeader = Get-Content -Path $MainPluginFile -Raw
if ($PluginHeader -notmatch '(?m)^\s*\*\s*Version:\s*([0-9A-Za-z.+-]+)\s*$') {
    throw 'Could not find a valid Version header in premier-league-table.php'
}

$Version = $Matches[1]

if (Test-Path $ReadmeFile) {
    $Readme = Get-Content -Path $ReadmeFile -Raw
    if ($Readme -match '(?m)^Stable tag:\s*([0-9A-Za-z.+-]+)\s*$') {
        $StableTag = $Matches[1]
        if ($StableTag -ne $Version) {
            throw "Version mismatch: plugin header is $Version, readme Stable tag is $StableTag"
        }
    }
}

New-Item -ItemType Directory -Path $ReleaseDir -Force | Out-Null

if (Test-Path $BuildRoot) {
    Remove-Item -LiteralPath $BuildRoot -Recurse -Force
}

New-Item -ItemType Directory -Path $StageDir -Force | Out-Null

$ItemsToPackage = @(
    'assets',
    'includes',
    'templates',
    'CHANGELOG.md',
    'premier-league-table.php',
    'readme.txt',
    'roadmap.md'
)

foreach ($Item in $ItemsToPackage) {
    $Source = Join-Path $RepoRoot $Item
    if (-not (Test-Path $Source)) {
        throw "Required release item missing: $Item"
    }

    Copy-Item -LiteralPath $Source -Destination $StageDir -Recurse -Force
}

$ZipPath = Join-Path $ReleaseDir "$PluginSlug-$Version-wp.zip"
if (Test-Path $ZipPath) {
    Remove-Item -LiteralPath $ZipPath -Force
}

Compress-Archive -Path $StageDir -DestinationPath $ZipPath -CompressionLevel Optimal

if (-not $SkipLocalUpdate) {
    $ResolvedLocalPath = $LocalPluginPath
    if (-not (Test-Path $ResolvedLocalPath)) {
        New-Item -ItemType Directory -Path $ResolvedLocalPath -Force | Out-Null
    }

    $RoboCopyArgs = @(
        $StageDir,
        $ResolvedLocalPath,
        '/MIR',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/NP'
    )

    & robocopy @RoboCopyArgs | Out-Null
    $RoboCopyExitCode = $LASTEXITCODE
    if ($RoboCopyExitCode -gt 7) {
        throw "Local plugin update failed. Robocopy exit code: $RoboCopyExitCode"
    }
}

Remove-Item -LiteralPath $BuildRoot -Recurse -Force

Write-Host "Built $ZipPath"
if (-not $SkipLocalUpdate) {
    Write-Host "Updated Local plugin at $LocalPluginPath"
}
