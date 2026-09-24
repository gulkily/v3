# Multi-Site Config — Step 3: Development Plan

## Stage 1
- Goal: Introduce a single source of truth for per-site branding.
- Dependencies: none
- Expected changes:
  - New `src/ForumRewrite/SiteProfileRegistry.php`, mirroring `ThemeRegistry`'s shape
  - `all(): array` — returns `zenmemes` and `chouse` entries, each with `name`, `defaultTheme`, `composerPrompt`
  - `active(): array` — resolves `getenv('FORUM_SITE_ID')`, defaults to `zenmemes`, falls back to `zenmemes` for an unrecognized value
- Verification approach: new unit test asserting default resolution, `FORUM_SITE_ID` override resolution, and unknown-value fallback
- Risks or open questions:
  - none
- Canonical components/API contracts touched: new `SiteProfileRegistry::all()`, `SiteProfileRegistry::active()`

## Stage 2
- Goal: Replace the hardcoded site name with the profile-driven value everywhere it renders.
- Dependencies: Stage 1
- Expected changes:
  - `SiteConfig::SITE_NAME` constant replaced by `SiteConfig::siteName(): string`, delegating to `SiteProfileRegistry::active()['name']`
  - Update all call sites: `Application.php:966,997,2346,2422`, `Host/FrontController.php:265,278,281`, `View/TemplateRenderer.php:66`, `templates/pages/about.php`
- Verification approach: existing tests pass unmodified with `FORUM_SITE_ID` unset (zenmemes output unchanged); add assertion that `FORUM_SITE_ID=chouse` changes rendered site name at each call site
- Risks or open questions:
  - confirm no other literal `'zenmemes'` string exists outside the known call sites
- Canonical components/API contracts touched: `SiteConfig::siteName()`

## Stage 3
- Goal: Let each site profile set the client-visible default theme.
- Dependencies: Stage 1
- Expected changes:
  - `TemplateRenderer` passes the active profile's `defaultTheme` into template context alongside the existing `themes` list
  - Base layout renders it as a `data-default-theme` attribute on an existing root element
  - `public/assets/theme_toggle.js` reads that attribute as its fallback instead of the literal `"auto"` at its current fallback points, keeping `"auto"` as the ultimate fallback when the attribute is absent
- Verification approach: manual check per `docs/runbooks/theme_development_guide.md` — load with no theme cookie under each `FORUM_SITE_ID`, confirm the rendered default theme; existing theme-toggle behavior still passes
- Risks or open questions:
  - `theme_toggle.js` is shared by every page; fallback chain must keep zenmemes' current behavior (`auto`) provably unchanged
- Canonical components/API contracts touched: `ThemeRegistry` (read-only reference, unchanged), `theme_toggle.js` fallback resolution

## Stage 4
- Goal: Let each site profile override the inline composer's prompt copy.
- Dependencies: Stage 1
- Expected changes:
  - `templates/pages/board.php` reads `composerPrompt` (placeholder + aria-label text) from the active profile instead of the literal `"Start a thread..."` string
  - Default value preserves the current zenmemes text
- Verification approach: template smoke test rendering `board.php` under both `FORUM_SITE_ID` values, asserting the placeholder/aria-label text
- Risks or open questions:
  - confirm no other page duplicates this literal string outside `board.php`
- Canonical components/API contracts touched: `templates/pages/board.php`

## Stage 5
- Goal: Let a developer preview either profile in one checkout with a single `FORUM_SITE_ID=<id> ./v3 start`, without hand-setting content-path env vars.
- Dependencies: Stage 1
- Expected changes:
  - `LocalRepositoryBootstrap::defaultRepositoryRoot()` becomes site-scoped when `FORUM_REPOSITORY_ROOT` is unset; conceptual signature: `defaultRepositoryRoot(string $projectRoot, string $siteId): string`
  - `zenmemes` resolves to today's exact path (`state/local_repository`) so existing local dev state is untouched
  - `chouse` resolves to a separate sibling directory, auto-initialized from the same fixture the same way
  - `public/index.php`'s equivalent local defaults for database/artifact paths become site-scoped the same way
- Verification approach: run `./v3 start` and `FORUM_SITE_ID=chouse ./v3 start` locally; confirm each auto-initializes and serves a distinct local repository/database with correct branding, and that omitting `FORUM_SITE_ID` reproduces today's exact path
- Risks or open questions:
  - must not change the default path for the zenmemes case, or existing local dev state would appear to "disappear"
- Canonical components/API contracts touched: `LocalRepositoryBootstrap::defaultRepositoryRoot()`, `public/index.php` local-default resolution

## Stage 6
- Goal: Document the shipped behavior and extend automated coverage.
- Dependencies: Stages 1-5
- Expected changes:
  - Update `docs/examples/apache_vhost.conf` and `docs/examples/env.production.example` with `FORUM_SITE_ID`
  - Update `docs/runbooks/production_deploy.md` precedence/rollback section
  - Extend `tests/LocalAppSmokeTest.php` (or add a sibling test) to run once per `FORUM_SITE_ID` value
- Verification approach: `./v3 test` passes; doc read-through against the Step 1 "How to configure" section for consistency
- Risks or open questions:
  - none
- Canonical components/API contracts touched: `tests/LocalAppSmokeTest.php`, `docs/examples/*`, `docs/runbooks/production_deploy.md`
