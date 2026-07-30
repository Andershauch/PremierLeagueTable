param(
    [switch] $IncludeLive
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $RepoRoot

function Find-PhpExecutable {
    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) {
        return $onPath.Source
    }

    # Fall back to Local by Flywheel's bundled PHP if it's installed but not on PATH.
    $localCandidates = Get-ChildItem -Path "$env:APPDATA\Local\lightning-services" -Filter 'php.exe' -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match 'win64' } |
        Sort-Object FullName -Descending

    if ($localCandidates) {
        return $localCandidates[0].FullName
    }

    throw 'No PHP CLI found on PATH or under Local by Flywheel. Install PHP or add it to PATH.'
}

$Php = Find-PhpExecutable
Write-Host "Using PHP: $Php"
& $Php --version

Write-Host "`n=== Unit tests (offline, deterministic) ==="
& $Php (Join-Path $RepoRoot 'tests\run-unit-tests.php')
if ($LASTEXITCODE -ne 0) {
    throw 'Unit test suite failed.'
}

if ($IncludeLive) {
    $PhpDir = Split-Path -Parent $Php
    $ExtensionDir = Join-Path $PhpDir 'ext'
    $OpensslExtension = Join-Path $ExtensionDir 'php_openssl.dll'

    Write-Host "`n=== Live smoke test (network, soft-skips if unreachable) ==="
    if (Test-Path $OpensslExtension) {
        & $Php -d "extension_dir=$ExtensionDir" -d 'extension=php_openssl.dll' (Join-Path $RepoRoot 'tests\live\wpll-live-smoke-test.php')
    } else {
        & $Php (Join-Path $RepoRoot 'tests\live\wpll-live-smoke-test.php')
    }

    if ($LASTEXITCODE -ne 0) {
        throw 'Live smoke test failed (not just skipped -- check output above).'
    }
}

Write-Host "`nAll PHP tests passed."
