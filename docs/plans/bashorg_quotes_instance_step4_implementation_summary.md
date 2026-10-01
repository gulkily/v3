# Bash.org-Style Quotes Instance — Step 4: Implementation Summary

## Stage 1 - Site instance identity plumbing
- Changes:
  - Added a `bashorg` entry to `SiteProfileRegistry::all()` (`src/ForumRewrite/SiteProfileRegistry.php`): `defaultTheme: bashorg`, `composerPrompt: "Submit a quote..."`.
  - Added a `bashorg` entry to `ThemeRegistry::all()` (`src/ForumRewrite/View/ThemeRegistry.php`), `mode: light`.
  - Documented `bashorg` as a valid `FORUM_SITE_ID` value in `docs/runbooks/production_deploy.md`.
  - Extended `tests/SiteProfileRegistryTest.php` with `testActiveHonorsBashorgOverride`, mirroring the existing `chouse` coverage.
- Verification:
  - `./v3 test SiteProfileRegistryTest` — 5 run, 5 passed (including the new bashorg case).
  - Inline PHP check with `FORUM_SITE_ID=bashorg` set: `SiteProfileRegistry::active()` resolves `{"name":"bashorg","defaultTheme":"bashorg","composerPrompt":"Submit a quote..."}`; `ThemeRegistry::explicitNames()` includes `bashorg`; `ThemeRegistry::stylesheetPaths()['bashorg']` resolves to `/assets/theme-bashorg.css`.
- Notes:
  - The `/assets/theme-bashorg.css` file referenced above does not exist yet — that's Stage 2. No error occurs from its absence yet since nothing serves the stylesheet until a page actually loads under this theme.

## Stage 2 - Bash.org-accurate theme stylesheet
- Changes:
  - Added `public/assets/theme-bashorg.css`: variable block (`:root[data-theme="bashorg"]`, `.theme-menu__option[data-theme-option="bashorg"]`) using the archived palette (`#ffffff` page, `#000000` ink, `#c08000` line/active/button chrome), `--code-font` set to Courier New/Lucida Console for the quote body font Stage 4 will use, theme swatch, and scoped chrome overrides (`.site-header`, `.eyebrow`, `.card`, `.nav-link.is-active`) synthesizing the orange title-bar look from existing markup per the theme guide's "CSS-only illusion" approach.
  - Updated `tests/LocalAppSmokeTest.php`'s theme allow-list assertion (`var allowed = [...]`) and added a `data-theme-option="bashorg"` presence assertion, per the theme guide's required two updates.
- Verification:
  - `./v3 test LocalAppSmokeTest` — 113 run, 110 passed; the 3 failures (`testAnonymousPublicBoardDoesNotStartViewerSession`, `testPostAndActivityLinkAdjacentSignatureFiles`, `testSqliteViewerRouteUsesToolsShellAndPublishedSource`) are pre-existing long-standing failures per the suite's own test-run history tracking, unrelated to `SiteProfileRegistry`/`ThemeRegistry`/theme CSS.
  - Confirmed `testApplicationRendersCoreRoutes` (the test containing the updated allow-list/theme-option assertions) passed.
- Notes:
  - No template changes were needed — `ThemeRegistry` already feeds the popover markup, cycle button, anti-FOUC allow-list, and `layout.php`'s stylesheet-swap map automatically, per the theme guide.
