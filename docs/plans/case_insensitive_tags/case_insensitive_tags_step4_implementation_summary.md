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
