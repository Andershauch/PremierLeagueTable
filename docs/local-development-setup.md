# Local development setup

How to get this plugin running against a local WordPress install on a fresh
machine. Written after moving the project to a new PC on 2026-08-19, where the
old procedure broke because it assumed one specific machine's paths.

## What the machine needs

| Tool | Why | How it is found |
| --- | --- | --- |
| [Local](https://localwp.com/) | Runs the WordPress site the plugin is tested in | Site auto-discovered under `%USERPROFILE%\Local Sites` |
| PHP CLI | Linting and the unit test suite | PATH first, else Local's bundled PHP under `%APPDATA%\Local\lightning-services` |
| Node.js | The `scripts/*.mjs` feed verification scripts | Must be on PATH |
| Git | Source control | Must be on PATH |
| GitHub CLI (`gh`) | Watching release runs; optional | Must be on PATH |

Nothing needs installing beyond that. There is deliberately no Composer and no
PHPUnit — the test suite is dependency-free so that "just run `php`" is the
whole setup step.

## First-time setup on a new machine

1. **Create the site in Local.** Any site name works; the scripts discover it.
   The site must be a real WordPress install (the scripts check for
   `app/public/wp-settings.php`).
2. **Clone the repository** anywhere. It does not need to live inside the site.
3. **Create `.env.local`** in the repository root. It is gitignored and holds
   API keys used only by the Node verification scripts:

   ```
   FOOTBALL_DATA_API_KEY=your-key-here
   ```

   The plugin itself does not read this file — WordPress stores the key in
   plugin settings. Only `scripts/*.mjs` use it.
4. **Deploy into the site:** `.\scripts\release.ps1`
5. **Activate** the plugin in WordPress and set the API key under
   `Settings -> Premier League Table`.

## How the Local site is located

`scripts/lib/local-site.ps1` resolves the target folder in this order, and stops
at the first one that answers:

1. `-LocalPluginPath` passed to the script
2. `$env:PLT_LOCAL_PLUGIN_PATH`
3. `$env:PLT_LOCAL_SITE_NAME`, matched against discovered sites
4. Auto-discovery — used only when exactly one Local WordPress site exists

With several sites installed, resolution stops and tells you to pick one:

```powershell
$env:PLT_LOCAL_SITE_NAME = 'whd-test'
.\scripts\release.ps1
```

To make that permanent for your user account:

```powershell
[Environment]::SetEnvironmentVariable('PLT_LOCAL_SITE_NAME', 'whd-test', 'User')
```

**Why this matters:** the previous version of these scripts hardcoded
`C:\Users\ander\Local Sites\whitehartdanes\...`. On a machine without that site,
`robocopy /MIR` cheerfully created the folder and mirrored the plugin into a
WordPress install that did not exist. The build reported success while the
plugin never reached the site. Resolution now fails loudly instead.

## Everyday commands

| Command | What it does |
| --- | --- |
| `.\scripts\release.ps1` | Builds the zips in `.release/` and mirrors the plugin into the Local site |
| `.\scripts\release.ps1 -SkipLocalUpdate` | Builds the zips only — no WordPress needed |
| `.\scripts\run-php-tests.ps1` | Runs the deterministic unit suite |
| `.\scripts\run-php-tests.ps1 -IncludeLive` | Also runs the network-touching smoke test |
| `.\scripts\run-hybrid-qa.ps1` | Runs the Node feed-verification scripts against live providers |
| `.\scripts\publish-github-release.ps1` | Tags and pushes a release (see `docs/release-process.md`) |

`release.ps1` mirrors with `robocopy /MIR`, which means the Local plugin folder
is made to match the build exactly — anything you edited directly inside
`wp-content/plugins/premier-league-table` is deleted. Edit in the repository,
never in the site.

## Manual QA in WordPress

Place these on a page and check each one:

- `[pl_table]` — Premier League
- `[pl_table competition="wsl"]` — Women's Super League
- `[pl_table competition="all"]` — combined tab view
- `[pl_next_match]` — the two next-match cards

`docs/hybrid-release-qa.md` holds the full checklist.

## Machine facts as of 2026-08-19

Recorded so the next move can tell what is machine-specific rather than
project-specific. None of these are hardcoded anywhere any more.

- Local site: `whd-test`, WordPress 7.0.4
- Local's bundled PHP: `%APPDATA%\Local\lightning-services\php-8.2.29+0\bin\win64\php.exe`
- PHP is **not** on PATH; the scripts find Local's copy themselves
- That PHP loads no `php.ini` by default. Scripts needing HTTPS must pass
  `-d extension_dir=<php>\ext -d extension=php_openssl.dll` explicitly —
  `run-php-tests.ps1 -IncludeLive` already does this.
