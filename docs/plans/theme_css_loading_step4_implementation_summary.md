# Theme CSS Loading — Step 4: Implementation Summary

## Stage 1 - Define theme asset and hint contracts
- Changes:
  - Added the canonical `theme-hint` cookie name, explicit-theme validation, and per-theme stylesheet paths to `ThemeRegistry`.
  - Added registry coverage for the cookie, valid theme paths, and rejection of `auto` or unknown hints.
- Verification:
  - `php -l src/ForumRewrite/View/ThemeRegistry.php`
  - `php -l tests/ThemeRegistryTest.php`
  - `php tests/run.php ThemeRegistryTest` — 5 tests passed.
- Notes:
  - Cookie reading and rendering are intentionally deferred to Stages 3–4.

## Stage 2 - Extract shared and per-theme stylesheets
- Changes:
  - Reduced `site.css` from 94,509 to 40,753 bytes by retaining shared, fallback, and critical rules only.
  - Extracted all 13 explicit theme selector groups, including their responsive rules and swatch styling, to fingerprintable `theme-<name>.css` assets.
  - Added a regression test that requires every registered theme asset and rejects explicit theme selectors in the shared stylesheet.
- Verification:
  - `php tests/run.php ThemeRegistryTest` — 6 tests passed.
  - Confirmed all 13 stylesheet assets are non-empty and no direct explicit-theme, swatch, or theme-option selector remains in `site.css`.
- Notes:
  - The unscoped light variables remain in `site.css` as the deliberate no-JavaScript/default fallback.
