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

## Stage 3 - Render the prioritized stylesheet link
- Changes:
  - Added a validated `theme-hint` lookup to the shared renderer and a fingerprinted stylesheet manifest for every explicit theme.
  - Rendered one high-priority theme stylesheet link before the head resolver; a valid hint (or explicit site default) supplies its initial path.
  - Extended the early resolver to replace a stale or missing hinted path with the client-resolved explicit or Auto/System theme before first paint.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet LocalAppSmokeTest::testCompactModeMenuStylesUseScopedDensitySelectors LocalAppSmokeTest::testApplicationRendersCoreRoutes ThemeRegistryTest` — passed.
  - Confirmed valid `word97` and invalid `auto` hints respectively render the fingerprinted Word 97 and fallback Light stylesheet paths.
- Notes:
  - The hint is not trusted as user preference and `auto` is rejected server-side.

## Stage 4 - Reconcile preference, Auto/System, and hint updates
- Changes:
  - The early resolver now writes the cookie only when its resolved concrete theme differs from the current hint.
  - The theme script keeps the active stylesheet, resolved-theme attribute, and cookie hint synchronized after explicit selections and Auto/System scheme changes.
  - Kept the stored `auto`/explicit preference separate from the concrete cookie value.
- Verification:
  - `php -l src/ForumRewrite/View/TemplateRenderer.php`
  - `node -c public/assets/theme_toggle.js`
  - `php tests/run.php LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet ThemeRegistryTest` — passed.
- Notes:
  - Cookie lifetime is one year, path is site-wide, and `Secure` is added on HTTPS.

## Stage 5 - Warm alternate themes and preserve responsive switching
- Changes:
  - Added idle-time, low-priority loading for every non-active resolved theme stylesheet.
  - Added stylesheet readiness tracking; a selection promotes an unfinished asset and waits without switching to incomplete styling.
  - Preserved immediate application for already warm/cached themes and kept Auto/System changes synchronized while their asset warms.
- Verification:
  - `node -c public/assets/theme_toggle.js`
  - `php tests/run.php LocalAppSmokeTest::testThemeToggleWarmsAlternateThemeStylesheetsAtLowPriority LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet ThemeRegistryTest` — passed.
- Notes:
  - A failed stylesheet request releases the loading state so controls cannot become permanently stuck.

## Stage 6 - Cover static artifacts and regressions
- Changes:
  - Added static-artifact coverage that requires every fingerprinted theme stylesheet to be referenced by the generated layout and present in its artifact assets.
  - Reused the existing asset copier and fingerprint reference health check; no release-pipeline change was needed.
  - Updated the Word 97 invitation regression to read its extracted theme stylesheet.
- Verification:
  - `php tests/run.php LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout LocalAppSmokeTest::testLayoutUsesOnlyAValidatedThemeHintForTheInitialStylesheet LocalAppSmokeTest::testThemeToggleWarmsAlternateThemeStylesheetsAtLowPriority ThemeRegistryTest` — passed.
  - `php tests/run.php InvitationIssuanceTest` — passed after moving its Word 97 assertion to the extracted asset.
  - Full `php tests/run.php` run: theme-related coverage passed; unrelated failures remain in activity-manifest, adjacent-signature, and lazy-compose test paths.
- Notes:
  - Static pages use the default hint-free initial link; pages carrying a theme hint already take the dynamic path and are corrected by the early resolver.

## Final Verification
- Changes:
  - None; recorded final route-level verification.
- Verification:
  - Started `php -S 127.0.0.1:8099 -t public public/router.php`, requested `/`, and confirmed the fingerprinted Light theme link plus all 13 fingerprinted theme paths in the rendered manifest.
- Notes:
  - Stopped the temporary development server after the check.
