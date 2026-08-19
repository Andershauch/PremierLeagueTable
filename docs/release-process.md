# Release process

This plugin is distributed from GitHub, not wordpress.org. A release is a tagged
commit plus a `premier-league-table.zip` asset attached to a GitHub Release. The
plugin installed on a site reads that release itself and offers the update
through WordPress's own update screens.

## The pieces

| Piece | File | Role |
| --- | --- | --- |
| Updater | `includes/class-github-updater.php` | Installed sites ask GitHub for the newest release and offer it as a normal WordPress update |
| Release workflow | `.github/workflows/release.yml` | Builds and publishes the zip when a `v*` tag is pushed |
| Tag helper | `scripts/publish-github-release.ps1` | Validates versions, tags, pushes |
| Local build | `scripts/release.ps1` | Builds the same zip locally, for testing or manual upload |

## Cutting a release

1. **Bump the version in all three places.** They must agree or the workflow
   refuses to publish:
   - `premier-league-table.php` → `* Version:` header
   - `premier-league-table.php` → `define('PLT_VERSION', ...)`
   - `readme.txt` → `Stable tag:`
2. **Add a `## <version> - <date>` section to `CHANGELOG.md`.** The workflow
   lifts the release notes straight out of it, so an empty section means an
   empty release.
3. **Run the tests:** `.\scripts\run-php-tests.ps1`
4. **Do the WordPress QA pass** from `docs/hybrid-release-qa.md`.
5. **Commit and push** to `main`.
6. **Publish:** `.\scripts\publish-github-release.ps1`

That last step validates the versions, refuses a dirty working tree, refuses to
reuse an existing tag, then tags and pushes. GitHub Actions takes over: it
re-checks the versions, lints, runs the unit suite, builds the zip, and creates
the release.

Watch and verify:

```powershell
gh run watch
gh release view v2.3.0
```

## The zip contract

The updater and the workflow agree on a format, and breaking it breaks updates
silently rather than loudly:

- The release asset **must** be named `premier-league-table.zip`.
- It **must** contain exactly one top-level folder, `premier-league-table/`.
- It contains `assets/`, `includes/`, `templates/`, `CHANGELOG.md`,
  `premier-league-table.php`, `readme.txt`, `roadmap.md` — no tests, scripts,
  docs, or `.github/`.

If no matching asset is attached, the updater falls back to GitHub's
auto-generated source zipball. That zipball's root folder is named after the
repo and commit, so `PLT_GitHub_Updater::fix_source_directory()` renames it
during install. Without that rename WordPress would install a *second*,
deactivated plugin folder rather than replacing the existing one. The fallback
is a safety net, not the intended path — always ship the asset.

## How updates reach a site

1. WordPress runs its periodic update check.
2. `PLT_GitHub_Updater::inject_update()` fetches
   `https://api.github.com/repos/Andershauch/PremierLeagueTable/releases/latest`.
3. If the tag parses as a version higher than `PLT_VERSION`, an update offer is
   added to the update transient. Otherwise the plugin is registered as current.
4. The site shows the update under `Dashboard -> Updates` and on the `Plugins`
   page, and installs it from the release asset on click.

Deliberate behaviours worth knowing before debugging a "missing" update:

- **The release check is cached for 12 hours.** A new release can take that long
  to appear. "Check again" on the Updates screen does not clear this cache —
  deactivating and reactivating the plugin does.
- **Failures are cached for 1 hour.** An offline or rate-limited site backs off
  instead of calling GitHub on every admin page load.
- **Drafts and pre-releases are ignored.** Use a pre-release to stage something
  without offering it to sites.
- **The call is unauthenticated.** No token, no account. GitHub's unauthenticated
  limit is 60 requests per hour per IP, which the 12-hour cache keeps it far
  below.
- **Only the version decides.** A re-pushed tag with new contents but the same
  version is invisible to already-updated sites. Bump the version instead.

## Manual install and rollback

`https://github.com/Andershauch/PremierLeagueTable/releases` lists every
release. Download the `premier-league-table.zip` asset for the version you want
and upload it under `Plugins -> Add New -> Upload Plugin`.

Do not use the "Source code (zip)" link for a manual install — that is the
zipball with the wrong folder name.

To roll back, upload an older release's zip. WordPress will ask to confirm
replacing the newer copy. The site then sits below the latest release and will
be offered an update back up to it, so a rollback is a temporary state, not a
pin.
