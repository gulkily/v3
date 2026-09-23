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

## Stage 4 - Candidate read-model build
- Changes:
  - Added a read-model candidate builder that writes beside, but never opens for writing, the live database.
  - Validates repository metadata and SQLite integrity before returning a candidate for later promotion.
- Verification:
  - `php -l src/ForumRewrite/ReadModel/ReadModelCandidateBuilder.php`
  - `php -l tests/ReadModelCandidateBuilderTest.php`
  - `./v3 test ReadModelCandidateBuilderTest ReadModelBuilderTimingTest`
  - Candidate integration test confirms the live database hash is unchanged during candidate creation.
- Notes:
  - Candidate promotion remains a separate next stage; this stage intentionally does not change the active database.

## Stage 5 - Atomic read-model promotion
- Changes:
  - Added promotion that revalidates a candidate under the existing shared write lock, then atomically replaces the live SQLite file and clears staleness.
  - Refused promotion when a live SQLite sidecar is present, avoiding an unsafe replacement state.
  - Updated the normal rebuild command to build outside the lock and promote only the finished candidate.
- Verification:
  - `php -l scripts/rebuild_read_model.php`
  - `./v3 test LocalAppSmokeTest::testRebuildCommandCreatesDatabase ReadModelCandidateBuilderTest`
  - Promotion test confirms the candidate replaces the live file, removes the candidate path, and clears the stale marker.
- Notes:
  - Existing requests retain a complete old SQLite inode during the rename; new requests open the complete promoted model.

## Stage 6 - Atomic static release publication
- Changes:
  - Added a static release publisher that builds all artifacts in a candidate directory, validates their fingerprinted assets, and atomically activates a complete release through a `current` symlink.
  - Changed the full static-build command to build an isolated read-model candidate and matching artifact release before promotion/activation; it no longer rebuilds the live SQLite file in place.
  - The front controller reads only the active release; it ignores old sibling `public/*.html` files and does not create artifacts during an HTTP request.
  - Updated archive maintenance to publish a new active release after its read-model refresh.
- Verification:
  - `php -l src/ForumRewrite/Host/StaticArtifactBuilder.php`
  - `php -l src/ForumRewrite/Host/StaticArtifactReleasePublisher.php`
  - `php -l src/ForumRewrite/Host/FrontController.php`
  - `php -l scripts/build_static_artifacts.php`
  - `./v3 test LocalAppSmokeTest::testStaticArtifactReleasePublisherActivatesCompleteReleaseForFrontController LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout ArchiveThreadCommandTest`
  - `php scripts/build_static_artifacts.php tests/fixtures/parity_minimal_v1 <temporary-db> <temporary-static-root>` created an active release and `current/index.html`.
- Notes:
  - Existing releases are retained for recovery; automatic retention cleanup is deliberately deferred rather than risking deletion of an active release.

## Stage 7 - Deployment guardrails
- Changes:
  - A canonical write now withdraws the active static-release pointer instead of editing a release in place. Anonymous pages safely fall back to PHP until the next complete release is published.
  - Agent-reply processing receives the same static release root, so an agent-created identity cannot leave a stale active release visible.
  - Updated the production runbook, recovery runbook, CLI reference, environment example, Apache example, and README to use `FORUM_STATIC_HTML_ROOT` and the candidate-release deployment sequence.
- Verification:
  - `php -l src/ForumRewrite/Host/FrontController.php`
  - `php -l src/ForumRewrite/Write/StaticArtifactInvalidator.php`
  - `php -l src/ForumRewrite/Agent/AgentIdentityService.php`
  - `php -l scripts/run_agent_reply_requests.php`
  - `./v3 test LocalAppSmokeTest::testFrontControllerServesStaticArtifactForAnonymousEligibleRoute LocalAppSmokeTest::testFrontControllerRevalidatesStaticArtifactByEtag LocalAppSmokeTest::testFrontControllerServesStaticArtifactForBackupAlias LocalAppSmokeTest::testFrontControllerBypassesStaticArtifactWhenCookieIsPresent LocalAppSmokeTest::testStaticArtifactReleasePublisherActivatesCompleteReleaseForFrontController LocalAppSmokeTest::testFrontControllerFallsBackDynamicallyUntilAReleaseIsActivated LocalAppSmokeTest::testFeatureFlagWriteInvalidatesAlternateStaticActivityArtifact LocalAppSmokeTest::testUsersStaticArtifactsAreInvalidatedByDirectoryAffectingWrites`
- Notes:
  - First deployment of this version stays dynamic until `./v3 build-static` finishes. It never asks users to hard-refresh: HTML revalidation prevents a stale document from being paired with newly fingerprinted assets.
