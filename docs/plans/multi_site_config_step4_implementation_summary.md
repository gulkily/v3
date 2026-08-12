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
