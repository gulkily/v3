> **Feature plan:** [Step 1](./image_content_step1_solution_assessment.md) · [Step 2](./image_content_step2_feature_description.md) · [Step 3](./image_content_step3_development_plan.md) · [Step 4](./image_content_step4_implementation_summary.md)

Debug mode: disabled

## Stage 1 - Portable storage and feature flag
- Changes: Added strict versioned body references, immutable private filesystem store, upload/store limits, and default-off registered image flag. Documented the canonical text contract; no database migration.
- Verification: `php tests/run.php ImageContentTest FeatureFlagEvaluatorTest` — 18 passed; reference round-trip, corruption, traversal/symlink roots, dimensions, idempotent storage and flag precedence checked. Updated the registry enumeration expectation for the new flag.
- Notes: Original bytes are retained; future bucket storage can preserve digest identity. Store mutations will use the existing instance write lock. UI and serving arrive in later stages.

## Stage 2 - Signed attachment publication
- Changes: Prepared posts bind portable image metadata in their signed body. Finalization accepts a server-verified multipart upload, verifies signature/approval/flag/checksum/limits under the instance write lock, and saves retained bytes only after the canonical commit. Unsigned thread/reply/quote writes reject image references. Image finalization retains its expiring preparation token for safe retries.
- Verification: `php tests/run.php WriteApiSmokeTest::testImageSignedPublicationRejectsTamperingAndRetriesSafely` passed with a real GPG signature, tampered bytes, disabled flag, unsigned bypass and duplicate retry checks; changed PHP files pass lint.
- Notes: A post committed before a failed image save is explicitly reported; retrying the same signed submission repairs the image without a duplicate post. Failed pre-commit uploads remain PHP temporary files, outside retained backups. Preparation-token expiry still applies.

## Stage 3 - Shared image compose flow
- Changes: Shared optional JPEG/PNG and description controls, lazy signing activation, browser metadata hashing and multipart signed finalization. Image requests retain preparation/signature for same-form retry; anonymous and Outbox actions reject selected images. File inputs are excluded from saved drafts and disabled until signing binds; descriptions/text persist and reload guidance explains reselection.
- Verification: `php tests/run.php LazyComposeSigningTest BrowserSigningNormalizationTest ImageContentTest` — 82 passed; `node tests/browser/image_compose_transport.cjs` checks actual helper metadata, multipart bytes, retry reuse, changed drafts and offline rejection; changed JavaScript passes syntax checks.
- Notes: Image authoring requires HTTPS/localhost Web Crypto; normal text authoring is unchanged. Full browser acceptance is consolidated in Stage 8 after delivery and backups exist.

## Stage 4 - Protected rendering and delivery
- Changes: Shared body renderer displays local images with escaped descriptions, dimensions and unavailable fallback. `/images/` is behind the instance access gate and feature flag, serves only verified digest filenames, and returns no-store/nosniff responses. Generated layouts carry image flag state; FrontController bypasses mismatched static artifacts, including environment override changes.
- Verification: Renderer/media regressions (14 passed); focused storage/routing/signed publication/static tests (7 passed); `LocalAppSmokeTest::testImageFlagMismatchBypassesStaleStaticArtifact` passed. Public image delivery and flag-off rejection exercised via Application. Existing service-worker allowlist excludes `/images/`.
- Notes: Removed Apache QDB direct-content shortcuts so PHP can evaluate live flags/membership; matching artifacts still use PHP's static fast path. This adds PHP dispatch overhead on formerly direct QDB paths. Cached copies already downloaded cannot be recalled.

## Stage 5 - Complete revision-bound backups
- Changes: Added bounded, serialized image exports with manifest/checksums and repository revision; matched repository snapshots retain Git history. Backup links pin their displayed revision; changed/dirty repositories and missing/corrupt images fail before download. Snapshot capture uses the instance write lock, archiving happens afterward, and temporary space is checked. Retained-image backup is independent of display flag.
- Verification: `php tests/run.php ImageContentTest LocalAppSmokeTest::testFrontControllerServesStaticArtifactForBackupAlias` — 4 passed, including real tar exports, manifest/object checks, matching revisions and missing/wrong-revision rejection. PHP lint and whitespace checks passed.
- Notes: Web repository snapshots are bounded to 512 MiB/100000 files; image limits remain 256 MiB/1024 objects. Archive assembly is serialized and requires twice snapshot bytes plus 16 MiB free temporary space. External writers must respect the documented maintenance boundary; detected HEAD/record changes abort capture.

## Stage 6 - Verified independent restoration
- Changes: Added `./v3 images verify|restore`, strict bounded tar validation, full inventory/reference checks, revision matching, and private staging with atomic activation. Existing different stores are preserved; identical-store retries succeed. Added backup/recovery runbook and CLI reference.
- Verification: `php tests/run.php ImageContentTest` — 3 passed including verify-only, activation, identical retry, unsafe entries, wrong repository revision and preservation of existing bytes. CLI help, PHP lint, launcher syntax and whitespace checks passed.
- Notes: Repository snapshot extraction precedes image restore; restore deliberately requires the original revision rather than a content-import commit. Rebuild and new-instance browser verification follow before cutover. Full command/browser recovery acceptance is scheduled for Stage 8.

## Stage 7 - Secondary surfaces and deployment guidance
- Changes: Offline rendering shows escaped image descriptions with an explicit unavailable message; offline/SQLite tools explain separate image backups. Text APIs retain canonical references and annotate missing binary content; RSS adds a view-online note. Repository-import reports disclose that image bytes are not transferred. Updated deployment guidance, example environment and README links, including QDB routing and future Option C portability.
- Verification: `php tests/run.php OfflineSnapshotPresentationTest ImportedContentPublicationTest ImageContentTest` — 12 passed; extended offline renderer coverage also passes (7 methods), including escaped descriptions. JavaScript syntax and whitespace checks passed.
- Notes: Activity/text/SQLite retain readable text references rather than fetching binary content. No offline binary cache or remote storage adapter is introduced. Real host PHP/Apache and browser checks are the next stage; no production host is configured in this task.
