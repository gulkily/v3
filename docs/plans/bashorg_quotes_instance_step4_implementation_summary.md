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
