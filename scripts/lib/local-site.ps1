# Shared helper for locating the WordPress install this plugin is developed
# against, without hardcoding one machine's paths.
#
# The old scripts pointed at a literal 'C:\Users\ander\Local Sites\whitehartdanes\...'.
# On a machine where that site does not exist, robocopy /MIR happily created the
# folder and mirrored the plugin into a WordPress install that was not there —
# so the build "succeeded" while the plugin never reached the site. Resolving
# the path instead, and failing loudly when it cannot be resolved, turns that
# silent miss into an error.

$PluginSlug = 'premier-league-table'

function Get-LocalSitesRoot {
    <#
        .SYNOPSIS
        Returns the folder Local stores its sites in, honouring an override.
    #>
    if ($env:PLT_LOCAL_SITES_ROOT -and (Test-Path $env:PLT_LOCAL_SITES_ROOT)) {
        return (Resolve-Path $env:PLT_LOCAL_SITES_ROOT).Path
    }

    $candidates = @(
        (Join-Path $env:USERPROFILE 'Local Sites'),
        (Join-Path $env:USERPROFILE 'Documents\Local Sites')
    )

    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return (Resolve-Path $candidate).Path
        }
    }

    return $null
}

function Find-LocalWordPressSites {
    <#
        .SYNOPSIS
        Returns every Local site that actually looks like a WordPress install.
    #>
    $root = Get-LocalSitesRoot
    if (-not $root) {
        return @()
    }

    $sites = @()
    foreach ($dir in (Get-ChildItem -Path $root -Directory -ErrorAction SilentlyContinue)) {
        $public = Join-Path $dir.FullName 'app\public'
        # wp-settings.php is the cheapest reliable marker of a real WP root.
        if (Test-Path (Join-Path $public 'wp-settings.php')) {
            $sites += [pscustomobject]@{
                Name        = $dir.Name
                PublicPath  = $public
                PluginsPath = Join-Path $public 'wp-content\plugins'
            }
        }
    }

    return $sites
}

function Resolve-LocalPluginPath {
    <#
        .SYNOPSIS
        Resolves the folder the plugin should be mirrored into.

        .DESCRIPTION
        Resolution order:
          1. -ExplicitPath (the script's own -LocalPluginPath parameter)
          2. $env:PLT_LOCAL_PLUGIN_PATH
          3. $env:PLT_LOCAL_SITE_NAME, matched against discovered Local sites
          4. Auto-discovery, when exactly one Local WordPress site exists

        Throws with an actionable message when the target is ambiguous or
        missing, rather than inventing a folder.
    #>
    param(
        [string] $ExplicitPath
    )

    if ($ExplicitPath) {
        return $ExplicitPath
    }

    if ($env:PLT_LOCAL_PLUGIN_PATH) {
        return $env:PLT_LOCAL_PLUGIN_PATH
    }

    # PowerShell unrolls a single-element array on return, so re-wrap it before
    # counting -- otherwise one discovered site is not seen as a count of 1.
    $sites = @(Find-LocalWordPressSites)

    if ($env:PLT_LOCAL_SITE_NAME) {
        $match = @($sites | Where-Object { $_.Name -eq $env:PLT_LOCAL_SITE_NAME })[0]
        if (-not $match) {
            $names = ($sites | ForEach-Object { $_.Name }) -join ', '
            throw "PLT_LOCAL_SITE_NAME is set to '$($env:PLT_LOCAL_SITE_NAME)' but no such Local WordPress site was found. Found: $names"
        }

        return (Join-Path $match.PluginsPath $PluginSlug)
    }

    if ($sites.Count -eq 1) {
        Write-Host "Auto-detected Local site: $($sites[0].Name)"
        return (Join-Path $sites[0].PluginsPath $PluginSlug)
    }

    if ($sites.Count -eq 0) {
        throw @'
No Local WordPress site found.

Looked under "$env:USERPROFILE\Local Sites" (and Documents\Local Sites).
Fix one of the following, then re-run:
  - Create/start the site in Local, or
  - Set PLT_LOCAL_PLUGIN_PATH to the plugin folder inside your WordPress install, or
  - Pass -LocalPluginPath explicitly, or
  - Pass -SkipLocalUpdate to build the zip only.
'@
    }

    $names = ($sites | ForEach-Object { $_.Name }) -join ', '
    throw @"
Multiple Local WordPress sites found: $names

Pick one explicitly, then re-run:
  - `$env:PLT_LOCAL_SITE_NAME = '<site name>'`, or
  - Pass -LocalPluginPath, or
  - Pass -SkipLocalUpdate to build the zip only.
"@
}

function Find-PhpExecutable {
    <#
        .SYNOPSIS
        Finds a PHP CLI: PATH first, then Local's bundled builds.

        .DESCRIPTION
        Local ships its own PHP under lightning-services, and the exact version
        folder differs per machine and per Local update, so it is discovered
        rather than hardcoded.
    #>
    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) {
        return $onPath.Source
    }

    $localCandidates = Get-ChildItem -Path "$env:APPDATA\Local\lightning-services" -Filter 'php.exe' -Recurse -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -match 'win64' } |
        Sort-Object FullName -Descending

    if ($localCandidates) {
        return $localCandidates[0].FullName
    }

    throw 'No PHP CLI found on PATH or under Local by Flywheel. Install PHP or add it to PATH.'
}
