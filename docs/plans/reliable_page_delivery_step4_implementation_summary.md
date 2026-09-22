# Reliable Page Delivery: Step 4 Implementation Summary

## Stage 1 - Critical first paint
- Changes:
  - Added a marked critical subset of the canonical stylesheet to every standard page layout.
  - Load the complete fingerprinted stylesheet asynchronously after preloading it; retain a normal stylesheet fallback when JavaScript is unavailable.
  - Kept theme variables and the visible shell in the inline subset so the existing early local theme selection applies before first paint.
- Verification:
  - `php -l src/ForumRewrite/View/TemplateRenderer.php`
  - `php -l tests/LocalAppSmokeTest.php`
  - `./v3 test LocalAppSmokeTest::testApplicationRendersCoreRoutes`
  - Local HTTP smoke check confirmed the rendered board contains the critical style block and fingerprinted stylesheet preload.
- Notes:
  - The inline subset is 22 KB raw (about 4.7 KB gzip), versus the 94 KB full stylesheet; a throttled-device visual check remains required before production release.

## Stage 2 - Interactive-first OpenPGP loading
- Changes:
  - Split the shared OpenPGP loader into eager preload and caller-triggered evaluation phases.
  - Moved theme and density controls ahead of page-specific crypto scripts.
  - Updated shared authentication and signing callers to request the loader explicitly while supporting the existing promise-based test contract.
- Verification:
  - `./v3 test OpenPgpLoaderTest PrivateSiteAuthTest BrowserSigningNormalizationTest LocalAppSmokeTest::testAnonymousPublicBoardDoesNotStartViewerSession LocalAppSmokeTest::testApplicationRendersCoreRoutes`
  - Loader test confirms only a preload is emitted initially and the script is evaluated only when a caller requests it.
- Notes:
  - A signing/authentication request still waits safely if it occurs before evaluation finishes; the network transfer already began during page load.

## Stage 3 - Cache-compatible HTML delivery
- Changes:
  - Added a shared HTML ETag matcher for conditional requests.
  - Public static artifacts now revalidate with an ETag and vary by cookie; configuration/busy responses remain uncacheable.
  - Dynamic HTML is private, cookie-varying, and revalidated; fingerprinted assets retain immutable caching.
- Verification:
  - `php -l src/ForumRewrite/Host/HtmlResponseCache.php`
  - `php -l src/ForumRewrite/Host/FrontController.php`
  - `php -l src/ForumRewrite/Application.php`
  - `php -l tests/LocalAppSmokeTest.php`
  - `./v3 test LocalAppSmokeTest::testFrontControllerServesStaticArtifactForAnonymousEligibleRoute LocalAppSmokeTest::testFrontControllerRevalidatesStaticArtifactByEtag LocalAppSmokeTest::testFrontControllerBypassesStaticArtifactWhenCookieIsPresent LocalAppSmokeTest::testApplicationRendersCoreRoutes`
- Notes:
  - A normal navigation performs a small validator request; unchanged static HTML returns no body, avoiding stale-page/new-script combinations without making assets uncached.
