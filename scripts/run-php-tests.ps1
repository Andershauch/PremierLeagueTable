param(
    [switch] $IncludeLive
)

$ErrorActionPreference = 'Stop'

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $RepoRoot

# Find-PhpExecutable now lives in the shared helper so release tooling and the
# test runner discover the same interpreter on any machine.
. (Join-Path $PSScriptRoot 'lib\local-site.ps1')

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
