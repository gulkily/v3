> **Feature plan:** [Step 1](./case_insensitive_tags_step1_solution_assessment.md) · [Step 2](./case_insensitive_tags_step2_feature_description.md) · [Step 3](./case_insensitive_tags_step3_development_plan.md) · [Step 4](./case_insensitive_tags_step4_implementation_summary.md)

Debug mode: enabled

## Implementation status

Feature branch: `feature/case-insensitive-tags`. Planning commit: `da3af89d`.

## Stage 1 - Online tag browsing

- Changes: merge ASCII case variants without changing stored content; normalize headings/links; recognize uppercase tag segments in dispatch and private-site route classification.
- Verification: `php tests/run.php LocalAppSmokeTest::testTagBrowsingCombinesCaseVariantsWithoutDuplicateThreads LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard LocalAppSmokeTest::testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound PrivateSiteAuthTest` — 12 passed. Fixtures cover six unique threads, overlapping label/tag variants, five-item previews, hidden identity content, six URL forms, unknown tags, and unchanged source records. `git diff --check` passed.
- Notes: access regression assertions belong in LocalAppSmokeTest, which exercises PHP routing; PrivateSiteAuthTest covers browser authentication and was run unchanged. The broader `LocalAppSmokeTest::testApplicationRendersCoreRoutes` fails on an unrelated account-key toolbar assertion, reproduced at planning baseline `da3af89d` in `/tmp/case-tags-baseline`. No schema or migration change.

## Stage 2 - Forte browsing

- Changes: reuse canonical grouping lookup for saved filters; lowercase/deduplicate row metadata; normalize URL filter state during browser history restoration. Original detail labels and reaction contracts remain unchanged.
- Verification: `php tests/run.php ForteBoardReaderTest LocalAppSmokeTest::testTagBrowsingCombinesCaseVariantsWithoutDuplicateThreads LocalAppSmokeTest::testForteReplyLikesRenderAndRestoreViewerState` — 5 passed. Tests exercise server-selected mixed-case filters, all six visible rows, browser filter switching/history restoration, thread fetching, and reaction state. `git diff --check` passed.
- Notes: browser interaction is exercised by the existing Node DOM harness here; end-to-end Chromium navigation is included in release verification.

## Stage 3 - Offline browsing

- Changes: normalize ASCII tag identity before snapshot deduplication, lookup, and headings; accept mixed-case tag segments in the reader and service worker while preserving the existing route alphabet.
- Verification: `php tests/run.php OfflineSnapshotPresentationTest OfflineNavigationWorkerTest` — 16 passed, including cache refresh/activation, missing snapshots, duplicate variants, ordering, previews, and invalid routes. `git diff --check` passed.
- Browser verification: `/tmp/case-tags-browser/check.cjs` with Playwright and Chromium 1208 against an isolated PHP server/fixture: one online group, six detail threads; six Forte rows before/after history restoration; activated worker; offline `/tags/PhP/` rendered `#php` and six threads; offline back-link showed one group; reconnect/reload showed one group. Existing fingerprinted assets and worker install refresh supply the updated reader.
- Notes: system Snap Chromium stalled during actionability/screenshot checks; using the locally installed Playwright Chromium completed the flow. No application workaround or snapshot-format change was needed.

## Stage 4 - Static delivery and release verification

- Changes: canonicalize static tag enumeration, route normalization, and front-controller lookup; publish only lowercase tag artifacts. Tests cover merged static content, private-site bypass, deleted-artifact dynamic fallback, and unknown tags.
- Verification: focused static/routing tests passed (5/5). Final affected regression run passed 39/39; command below. `git diff --check` and local planning-link checks passed.
- Release exercise: `FORUM_APPROVED_MEMBERS_ONLY=false ./v3 build-static /tmp/case-tags-browser/instance /tmp/case-tags-browser/index.sqlite3 /tmp/case-tags-browser/static` succeeded, publishing 49 HTML and 40 asset files. All six forms of `/tags/{php,PHP,Php}` with/without trailing slash served the same static heading and six unique threads. Chromium repeated the online/Forte/offline/reconnect flow successfully against the published release.
- Notes: existing invalidation removes the active release pointer, so no new invalidation mechanism is needed. No migrations, canonical-content edits, external-service changes, or production deployment were performed. The unrelated baseline toolbar smoke failure remains as recorded in Stage 1.

## Final regression command

```sh
php tests/run.php LocalAppSmokeTest::testTagBrowsingCombinesCaseVariantsWithoutDuplicateThreads LocalAppSmokeTest::testApprovedPrivateSessionCanViewOwnProfileAndBoard LocalAppSmokeTest::testPrivateForteRoutesRecoverExpiredSessionsInsteadOfReturningFalseNotFound LocalAppSmokeTest::testForteReplyLikesRenderAndRestoreViewerState ForteBoardReaderTest OfflineSnapshotPresentationTest OfflineNavigationWorkerTest PrivateSiteAuthTest WebServerRoutingTest LocalAppSmokeTest::testStaticArtifactBuilderWritesApacheFriendlyArtifactLayout LocalAppSmokeTest::testStaticArtifactReleasePublisherActivatesCompleteReleaseForFrontController LocalAppSmokeTest::testFrontControllerFallsBackDynamicallyUntilAReleaseIsActivated LocalAppSmokeTest::testFrontControllerDoesNotBuildArtifactForCookieBearingFallback LocalAppSmokeTest::testFrontControllerDoesNotBuildArtifactForQueryFallback
```

## Rollout and rollback

- After deploying the reviewed code through the existing release process, regenerate static artifacts with `./v3 build-static "$FORUM_REPOSITORY_ROOT" "$FORUM_DATABASE_PATH" "$FORUM_STATIC_HTML_ROOT"`, using the target instance's configured paths. A full build replaces stale tag artifacts and fingerprints updated browser assets.
- Verify `/tags/`, a mixed-case tag link, Forte history navigation, and an activated worker with offline navigation. Existing workers install updated source and refresh the reader assets; existing snapshots remain compatible.
- To roll back, deploy the previous reviewed code release (or revert this feature's four implementation commits, newest first) and rerun the same static-build command. Allow the previous worker to activate/refresh. Stored tags require no recovery. Production rollout/rollback is outside this local implementation handoff.
