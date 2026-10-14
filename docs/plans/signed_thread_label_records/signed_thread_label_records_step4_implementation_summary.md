> **Feature plan:** [Step 1](./signed_thread_label_records_step1_solution_assessment.md) · [Step 2](./signed_thread_label_records_step2_feature_description.md) · [Step 3](./signed_thread_label_records_step3_development_plan.md) · [Step 4](./signed_thread_label_records_step4_implementation_summary.md)

Debug mode: enabled

# Signed labels and reactions implementation

Branch: `feature/signed-thread-label-records`. Planning commit: `2cbbe624`.

## Stage 1 - Signed thread actions
- Changes: browser builds/signs canonical thread labels; writer verifies exact action/author/bytes, safe IDs, and signatures before duplicate handling; commits record/signature together. Documented optional signatures in both format specs.
- Verification: PHP lint, Node syntax check; `php tests/run.php WriteApiSmokeTest::testSignedThreadReactionPreservesBytesAndRejectsTampering ReadModelThreadLabelsTest` (3 passed). Real GPG signing verifies exact persistence and rejects altered bytes, unsafe IDs, mismatched actions, and incomplete/invalid signatures.
- Notes: full browser/QDB coverage is scheduled in Stage 8; clock fallback, historical validation, and interruption recovery remain scheduled stages. Existing unsigned path is retained.

## Stage 2 - Signed post Likes and Flags
- Changes: post reactions reuse the same browser/server signature envelope; existing classic, Forte, and QDB handlers require no separate markup. Registered the existing post-reaction read-model tests, previously absent from the runner.
- Verification: real-GPG API regression covers root/reply Like and Flag, exact signature persistence, moderation count, and duplicate submission; passed. Node syntax passed. Existing ReadModelPostReactionsTest passed (1 test).
- Notes: inspected existing lazy-signing assets and both action contracts; theme/browser interaction coverage remains Stage 8.

## Stage 3 - Bounded clock fallback
- Changes: signed requests outside ±300 seconds return prepared bytes/token with `clock_skew`; browser validates unchanged action fields, signs once more, and finalizes through the same endpoint. Tokens reuse existing ten-minute storage/expiry and bind exact bytes and author.
- Verification: three signed-reaction regression methods passed, including real-GPG HTTP fallback for both families, expired tokens, successful finalization, and duplicate finalization. PHP/Node syntax passed.
- Notes: correction to Stage 2 count: ReadModelPostReactionsTest ran two tests, both passed. Historical signature verification in Stage 4 will not enforce the live clock window.

## Stage 4 - Canonical and imported verification
- Changes: both canonical loaders verify optional signature companions against author keys; full/incremental reducers therefore reject invalid signed input. Import planning resolves source keys with destination fallback. Viewer/duplicate scans skip invalid records. Added invalid post-reaction diagnostics and bounded content-keyed verification caching.
- Verification: 15 write/import/read-model tests passed; additional real-GPG historical test passed, proving old valid records import/rebuild while corrupted companions are rejected and removed from derived scores.
- Notes: no live timestamp cutoff in historical verification; unsigned records continue through existing reducers. Missing keys cannot produce verified status.

## Stage 5 - Per-record evidence and inspection
- Changes: schema 17 adds `reaction_signatures` for all records, including activity-deduplicated labels. Full/incremental refreshes maintain it; shared activity/source metadata reports verified/unsigned/invalid and current raw-source responses add a status header without changing bytes.
- Verification: schema and signed-record tests passed (4); version-16 normal-page startup rebuild and historical evidence tests passed (2). `./v3 rebuild tests/fixtures/parity_minimal_v1 /tmp/signed-reactions-stage5b.sqlite3` succeeded. Fixed a test-only open SQLite cursor that blocked a rebuild before rerunning.
- Notes: schema reference updated; aggregate scores are not marked universally signed. Production data was not rebuilt.

## Stage 6 - Signature lifecycle preservation
- Changes: thread archives now collect/remove signature companions alongside records and retain supporting author keys. Legacy repository archive import uses the shared reaction plan for signature validity and paired conflict decisions; modern sync/import already uses that planner. Whole-repository downloads include companions without extension filtering.
- Verification: archive, legacy import, and import planner tests passed (13). Real-GPG round trip passed: archive both signed families, confirm both companions leave the live repository, extract/restore exact bytes, rebuild, and recover two verified evidence rows.
- Notes: intentionally stripped signatures remain indistinguishable from legacy unsigned records; supported archive paths now preserve evidence.

## Stage 7 - Durable retry recovery
- Changes: signed writes journal exact accepted bytes outside the canonical repository, atomically publish signature before record, and clear the journal only after commit/refresh/invalidation. The next locked write recovers pending pairs, commits only when needed, rebuilds derived state, and invalidates presentation. Duplicate shortcuts also repair stale derived state.
- Verification: signed write/fallback regressions passed (3). Failure-injection test passed for signature-only interruption, rejected Git commit, and failed postcommit refresh; retries preserve exact pairs, produce committed records once, clear journals/stale markers, and return duplicate success.
- Notes: unsigned records are never retroactively signed. Recovery rejects conflicting filesystem bytes and invalid journal evidence rather than overwriting them.

## Stage 8 - Integration verification and operator documentation
- Changes: added a disposable localhost Chrome/OpenPGP matrix, valid signed activity fixtures, and submission/recovery documentation. Fixed integration findings: raw-source headers must use RouteServices' list format; activity commit manifests need explicit per-record status; signer-key lookup must preserve claimed-author metadata independently of validity. Updated asynchronous optimistic-UI tests to wait for signing before the pending fetch. Canonical atomic files retain public-readable permissions while journals remain private. Prepared tokens without signing fields cannot downgrade to unsigned.
- Verification: final full suite `php tests/run.php`: **1,130 run, 1,121 passed, 9 failed**. The baseline worktree at `2cbbe624` ran 1,121 tests with seven identical failures; rerunning the remaining two there with the original private-config path reproduced both. Thus all nine failures predate this change. Follow-up targeted tests passed for source/activity display, wrong prepared bytes, missing/import-fallback keys, recovery, and signed persistence. Syntax and whitespace checks passed.
- Browser verification: `PLAYWRIGHT_MODULE=/tmp/signed-reaction-browser-deps/node_modules/playwright-core/index.mjs node tests/browser/signed_reactions.mjs` passed classic root/reply Like/Flag, Forte equivalents with two-request clock recovery, QDB up/down captions and Flag, exact record/signature/source bytes, verified headers, cached reload without repeat writes, automatic guest identity/signing, legacy keyless unsigned submission/status, and signing failure without an unsigned request followed by successful retry.
- Notes: no production deployment. All eight implementation stages are implemented and feature-specific checks pass, but the approved globally-green suite release condition is **not met** because of the baseline failures below. Do not represent this branch as globally green. Unified canonical storage remains the separate todo follow-up.

### Baseline failures retained
- `LocalAppSmokeTest::testApplicationRendersCoreRoutes`: account-key toolbar expectation.
- `LocalAppSmokeTest::testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`: locked-reason expectation with local private configuration.
- `OfflineSnapshotBootstrapTest::testPublishesWhenNoSnapshotIsServed` and `testRetainsAnExistingServedSnapshot`: fixture lacks `author_identity_id`.
- `OfflineSnapshotPublishCommandTest::testPublishBuildsOnlyIndependentOfflineSnapshot` and `testPublishReportsSelectedProfileWithoutChangingExplicitRootPrecedence`: existing offline publication failures.
- `QuoteCardDisplayNumberTest::testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`: welcome copy expectation.
- `WriteApiSmokeTest::testTaskQueueProcessesQueuedAgentReplyOnce`: queue task expectation with local private configuration.
- `WriteApiSmokeTest::testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote`: existing disabled-caption markup expectation.
