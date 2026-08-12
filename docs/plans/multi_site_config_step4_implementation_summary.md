# Multi-Site Config — Step 4: Implementation Summary

## Stage 1 - SiteProfileRegistry class
- Changes:
  - Added `src/ForumRewrite/SiteProfileRegistry.php`
    - `all(): array` — `zenmemes` and `chouse` entries, each with `name`, `defaultTheme`, `composerPrompt`
    - `active(): array` — resolves `getenv('FORUM_SITE_ID')`, defaults to `zenmemes` when unset/empty/unrecognized
  - Added `tests/SiteProfileRegistryTest.php` covering default resolution, known override, unknown-value fallback, and required-field presence
  - Registered the new test file in `tests/run.php`
- Verification:
  - `php tests/run.php` — full suite passes, including all 4 new `SiteProfileRegistryTest` cases
- Notes:
  - Both profiles currently carry identical `defaultTheme`/`composerPrompt` placeholder values; Stages 3-4 wire those fields through to rendering, at which point chouse's values can be revisited/diverged if desired

## Stage 2 - Wire SiteConfig::siteName()
- Changes:
  - `SiteConfig::SITE_NAME` constant replaced with `SiteConfig::siteName(): string`, delegating to `SiteProfileRegistry::active()['name']`
  - Updated call sites: `Application.php:966,997,2346,2422`, `Host/FrontController.php:265,278`, `View/TemplateRenderer.php:66`
  - `templates/pages/about.php` needed no change — it already reads `$siteName` from the template context these call sites populate
- Verification:
  - `php -l` on all four touched files — no syntax errors
  - `php tests/run.php` — full suite passes
  - Manual check: `SiteConfig::siteName()` returns `zenmemes` with `FORUM_SITE_ID` unset, `chouse` when set to `chouse`, and `zenmemes` for an unrecognized value
- Notes:
  - Searched for other hardcoded `zenmemes` literals outside the `SITE_NAME` call sites. Found and intentionally left untouched, as out of scope for this stage:
    - `FrontController.php:281` — a hardcoded "zenmemes is still baking" joke string in the busy-page copy (site-specific copy, not the site-name accessor; belongs to a future chouse-branding feature)
    - `templates/layout.php`, `public/assets/theme_toggle.js`, `public/assets/thread_density_toggle.js` — `localStorage` key names (`zenmemes-theme`, `zenmemes-thread-density`); internal storage keys, not user-facing branding, and functionally inert across sites since browsers scope `localStorage` per origin
    - `templates/pages/about.php:3` — a `data-about-section="zenmemes"` attribute used for CSS/JS targeting, not rendered text

## Stage 3 - Default theme wiring
- Changes:
  - `TemplateRenderer::renderLayout()` adds `'defaultTheme' => SiteProfileRegistry::active()['defaultTheme']` to the layout context
  - `templates/layout.php` renders it as `data-default-theme="..."` on the `<html>` root element
  - `public/assets/theme_toggle.js`: new `defaultTheme()` helper reads that attribute (validated against the known theme list, else `"auto"`); `readStoredTheme()`'s two `"auto"` fallbacks now call it instead
- Verification:
  - `php -l` on touched PHP files, `node -c` on `theme_toggle.js` — no syntax errors
  - `php tests/run.php` — full suite passes
  - Manual check via `TemplateRenderer::renderLayout()`: `FORUM_SITE_ID` unset renders `data-default-theme="auto"` + `siteName=zenmemes`; `FORUM_SITE_ID=chouse` renders `data-default-theme="auto"` + `siteName=chouse` (both profiles currently share the `auto` default, so output differs only in site name until a profile's `defaultTheme` diverges)
- Notes:
  - The inline pre-hydration script in `layout.php` (head `<script>`, lines 7-34) still falls back to leaving no `data-theme` attribute when nothing is stored, rather than reading `data-default-theme` itself — for every profile today that resolves to the same visual result (`auto` via CSS `prefers-color-scheme`), since both profiles use `auto`. If a future profile sets a non-`auto` default theme, first-time visitors would see a brief flash of the system-preference theme before `theme_toggle.js` applies the real default on `DOMContentLoaded`. Left out of this stage's scope (the approved plan only called for wiring `TemplateRenderer` → attribute → `theme_toggle.js`); worth revisiting if/when a site profile actually diverges from `auto`.

## Stage 4 - Composer copy wiring
- Changes:
  - `TemplateRenderer::renderFile()` adds `'composerPrompt' => SiteProfileRegistry::active()['composerPrompt']` to the same default-merge block that already injects `unicodeAuthoredTextEnabled`/`emojiAuthoredTextEnabled` into every page/partial, so no per-route wiring was needed
  - `templates/pages/board.php`: the inline composer's `placeholder` and `aria-label` now read `$composerPrompt` (aria-label strips a trailing `.` for phrasing) instead of the literal `"Start a thread..."` / `"Start a thread"` strings
- Verification:
  - `php -l` on touched PHP files — no syntax errors
  - `php tests/run.php` — full suite passes
  - Manual render check via `TemplateRenderer::renderPageTemplate('board.php', ...)`: output textarea shows `placeholder="Start a thread..."` and `aria-label="Start a thread"`, identical to the pre-change literal, confirming no behavior change for zenmemes
  - Grepped `templates/` for other occurrences of `"Start a thread"` — none found, so no duplicate copy was missed
- Notes:
  - Both profiles still share the same `composerPrompt` value from Stage 1's placeholder data; chouse's copy can diverge later (e.g. in a future chouse-branding feature) by editing only `SiteProfileRegistry::all()`

## Stage 5 - Site-scoped local dev bootstrap
- Changes:
  - `LocalRepositoryBootstrap::defaultRepositoryRoot(string $projectRoot, string $siteId = 'zenmemes'): string` — new optional `$siteId` param; `zenmemes` (including the pre-existing default) resolves to the unchanged `state/local_repository` path, any other site ID resolves to a suffixed sibling directory (`state/local_repository_<siteId>`), auto-initialized from the same fixture the same way
  - `public/index.php` resolves `$siteId` via `SiteProfileRegistry::active()['name']` and applies the same suffix convention to the local defaults for `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`, and `FORUM_STATIC_HTML_ROOT` (all three only take effect when the corresponding env var is unset — explicit env vars still win, unchanged)
  - All other `LocalRepositoryBootstrap::defaultRepositoryRoot()` call sites (various `scripts/*.php`, `tests/LocalAppSmokeTest.php`) are unaffected — the new parameter defaults to `'zenmemes'`, reproducing today's exact behavior
- Verification:
  - `php -l` on touched PHP files — no syntax errors
  - `php tests/run.php` — full suite passes
  - Manual check: `LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)` (no site ID) still resolves to the pre-existing `state/local_repository`
  - Manual check: `LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot, 'chouse')` auto-initializes a distinct `state/local_repository_chouse` with its own `records/` and `.git`
  - Manual check of the `public/index.php` path-resolution logic: `FORUM_SITE_ID` unset yields the exact pre-existing database/static-html paths; `FORUM_SITE_ID=chouse` yields distinct `_chouse`-suffixed sibling paths
  - This directly delivers the dev-config ask: `FORUM_SITE_ID=chouse ./v3 start` alone (no other `FORUM_*` vars) now serves a fully separate, auto-initialized chouse sandbox — content, database, and static-artifact paths never overlap with the zenmemes checkout
- Notes:
  - `state/local_repository_chouse` is gitignored (same rule that covers `state/local_repository`), so exercising this locally leaves no untracked changes to commit

## Stage 6 - Docs and smoke-test coverage
- Changes:
  - `docs/examples/apache_vhost.conf`: added a commented `FORUM_SITE_ID` line alongside the existing `FORUM_*` path vars
  - `docs/examples/env.production.example`: same, as a commented optional var with its default noted
  - `docs/runbooks/production_deploy.md`: new "Site Profile" section — values, default, precedence/rollback matching the runbook's existing `FORUM_*` style, and the local/CLI `FORUM_SITE_ID=chouse ./v3 start` dev workflow from Stage 5
  - `tests/LocalAppSmokeTest.php`: added `testDefaultRepositoryBootstrapIsSiteScoped()` (zenmemes path unchanged, chouse resolves to a distinct auto-initialized sibling) and `testBoardPageRendersActiveSiteProfilePerFormSiteId()` (full `Application`-level render confirms site name and default theme both flip correctly between `FORUM_SITE_ID` unset and `chouse`, with proper `putenv` cleanup in `finally`)
- Verification:
  - `php -l` on all touched PHP files — no syntax errors
  - `php tests/run.php` — full suite passes, including both new `LocalAppSmokeTest` cases
- Notes:
  - While verifying this stage, `php tests/run.php` initially reported `testFrontControllerShowsBusyErrorForExecutionLockContention` failing. Root cause: an untracked `public/index.html` (gitignored static-artifact cache) had been written to disk as a side effect of this session's own earlier manual `Application`-level rendering checks in Stages 3-5. `FrontController` serves that cached file for `/` before ever reaching the lock/rebuild code path the test exercises, so the test short-circuited into a false failure — not a regression from this feature's changes. Confirmed by running the full suite on a clean `main` worktree (passed) and by inspecting `FrontController::resolveStaticArtifactPath()`, which checks `publicRoot/index.html` first. Fixed by deleting the stray gitignored file; the full suite is green afterward.
