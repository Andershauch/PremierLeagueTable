param(
    # Leave empty to auto-detect the Local site. See scripts/lib/local-site.ps1
    # for the full resolution order (parameter, env vars, auto-discovery).
    [string] $LocalPluginPath = '',
    [switch] $SkipLocalUpdate
)

$ErrorActionPreference = 'Stop'

. (Join-Path $PSScriptRoot 'lib\local-site.ps1')

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

# PLT_VERSION is what the GitHub updater compares against, so a mismatch here
# would make an installed site think it is already up to date.
if ($PluginHeader -notmatch "define\('PLT_VERSION',\s*'([0-9A-Za-z.+-]+)'\)") {
    throw 'Could not find a PLT_VERSION constant in premier-league-table.php'
}

$ConstVersion = $Matches[1]
if ($ConstVersion -ne $Version) {
    throw "Version mismatch: plugin header is $Version, PLT_VERSION is $ConstVersion"
}

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

$ConflictingZipPattern = "$PluginSlug-$Version-wp*.zip"
Get-ChildItem -Path $ReleaseDir -Filter $ConflictingZipPattern -File | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Force
}
Get-ChildItem -Path $ReleaseDir -Filter "$PluginSlug.zip" -File | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Force
}
Get-ChildItem -Path $ReleaseDir -Filter "$PluginSlug-$Version-file-manager-overwrite.zip" -File | ForEach-Object {
    Remove-Item -LiteralPath $_.FullName -Force
}

$ZipPath = Join-Path $ReleaseDir "$PluginSlug.zip"
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$Zip = [System.IO.Compression.ZipFile]::Open($ZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Path $StageDir -Recurse -File | ForEach-Object {
        $RelativePath = $_.FullName.Substring($BuildRoot.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $Zip,
            $_.FullName,
            $RelativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $Zip.Dispose()
}

$VersionedZipPath = Join-Path $ReleaseDir "$PluginSlug-$Version-wp.zip"
Copy-Item -LiteralPath $ZipPath -Destination $VersionedZipPath -Force

$OverwriteZipPath = Join-Path $ReleaseDir "$PluginSlug-$Version-file-manager-overwrite.zip"
$OverwriteZip = [System.IO.Compression.ZipFile]::Open($OverwriteZipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    Get-ChildItem -Path $StageDir -Recurse -File | ForEach-Object {
        $RelativePath = $_.FullName.Substring($StageDir.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $OverwriteZip,
            $_.FullName,
            $RelativePath,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
}
finally {
    $OverwriteZip.Dispose()
}

if (-not $SkipLocalUpdate) {
    $ResolvedLocalPath = Resolve-LocalPluginPath -ExplicitPath $LocalPluginPath

    # The plugin folder itself may not exist yet, but its parent must -- if the
    # plugins directory is missing, the resolved path is not a WordPress install
    # and mirroring into it would just build a directory tree nobody loads.
    $PluginsParent = Split-Path -Parent $ResolvedLocalPath
    if (-not (Test-Path $PluginsParent)) {
        throw "Resolved Local plugin path has no wp-content/plugins parent: $PluginsParent"
    }

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

    # Robocopy signals "files were copied" with exit code 1, which is success
    # here. Left unhandled it becomes this script's own exit code and makes a
    # successful build look like a failure to any caller checking it.
    $global:LASTEXITCODE = 0
}

Remove-Item -LiteralPath $BuildRoot -Recurse -Force

Write-Host "Built $ZipPath"
Write-Host "Upload this exact zip in WordPress. It is intentionally named $PluginSlug.zip."
Write-Host "Built versioned archive copy $VersionedZipPath"
Write-Host "Built $OverwriteZipPath"
Write-Host "Use the file-manager overwrite zip only from inside the existing $PluginSlug folder."
if (-not $SkipLocalUpdate) {
    Write-Host "Updated Local plugin at $ResolvedLocalPath"
}
