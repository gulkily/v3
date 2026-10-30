> **Feature plan:** [Step 1](./unified_reaction_records_step1_solution_assessment.md) · [Step 2](./unified_reaction_records_step2_feature_description.md) · [Step 3](./unified_reaction_records_step3_development_plan.md) · [Step 4](./unified_reaction_records_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/unified_reaction_records; [standing enrollment P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2)
Extended estimates: enabled
Relationship: separate follow-up to [signed_thread_label_records](../signed_thread_label_records/signed_thread_label_records_step4_implementation_summary.md)
Branch: `feat/unified-reaction-records`; planning commit `59c663e4`
**Cycle estimate: 0.75**

# Unified Reaction Records Step 4 Implementation Summary

## Step 4 entry checkpoint

- Checkpoint: Step 4 entry, 2026-10-30 UTC; whole-cycle **0.75 reaffirmed** from Step 3, no new information since.
- Scope v1 / Option A; baseline `60d17174`; R1 / P2; Claude (claude-opus-5-5) single exposed rater.
- Original stage-work estimates S1–S5/v1: 0.15 / 0.15 / 0.15 / 0.20 / 0.10, copied from the approved plan, not additive.
- Disclosure: this summary was written after Stage 1 code was drafted; the 0.75 entry value was settled at the Step 3 handoff and not revised.
- Full-suite baseline at `60d17174` (separate worktree): **1653 run, 1649 passed, 4 failed** — `LocalAppSmokeTest::testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`, `PlatformDocsStaticTest::testStaticReleaseIncludesCompleteInventoryAndSafeAssets`, `ReadModelBuilderTimingTest::testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts`, `TaskQueueCommandTest::testRebuildWorkerRecordsPrivateProgressCheckpoints`. None involve reaction records.

## Stage 1 - Shared parser

**Original estimate: 0.15**
**Retrospective stage work: 0.12**

- Changes:
  - `ReactionRecordParser` reads the unified header set (`Target-Kind`, `Target-ID`, `Tags`) and both legacy sets; the family fixes the expected scope; mixed headers, scope mismatch and post-scope `Source-Post-*` are rejected. Includes a `build()` for unified bytes.
  - `ThreadLabelRecordParser` and `PostReactionRecordParser` now wrap it and return the existing record types, so readers and the read-model schema are unchanged. Legacy error messages are identical.
  - New spec [`reaction_record_v1.md`](../../specs/reaction_record_v1.md).
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest`: 52/52 passed, including four new tests (both scopes, scope mismatch, mixed/misplaced headers, legacy cross-family rejection).
  - Regression: `ReadModelThreadLabelsTest` 2/2, `ReadModelPostReactionsTest` 2/2, `ReadModelViewerTagsProjectionTest` 1/1, `ViewerTagLookupParityTest` 1/1, `ContentImportPlannerTest` 14/14, `RemovalReceiptTest` 3/3, `WriteApiSmokeTest` 189/189 passed.
- Notes: nothing writes the new format yet. The spec's cross-version statement is based on reading the old parser (it requires `Thread-ID`/`Post-ID`); not tested against an older checkout.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 2 - Readers and lifecycle accept the unified format

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - ER1 refinement: legacy header handling moved out of `ReactionRecordParser` into a deprecated `LegacyReactionHeaders` adapter (header mapping now; the legacy signed client layout joins it in Stage 4). Removing legacy support later means deleting that class and its callers; the spec's Compatibility section says so.
  - `scripts/audit_post_signatures.php` reads tags through the shared parser instead of raw `Labels`/`Tags` headers. Side effect: a reaction record that fails family parsing now reports no tags (previously its raw header value).
  - Confirmed by test, no code change needed: full and incremental read models, viewer tags, signature index, import planner, archive round trip, activity, high-traffic safety, removal receipts. `delete_record.php`, `thread_attributes.php` and `archive_thread.php` already use the loaders.
  - New `ReactionRecordFormatParityTest` (registered in `tests/run.php`): legacy, unified and mixed repositories build identical read models (all tables; metadata excluding fingerprint, path and timing keys); incremental updates with unified records match a rebuild.
  - Extended `WriteApiSmokeTest` historical-signature and archive tests with signed unified records; a legacy and a unified like from the same signer on the same post count once (post score 1); tampering invalidates both forms. Extended `PostSignatureAuditCommandTest` with a unified record.
- Verification (all passed): `CanonicalRecordParsersTest` 52, `ReactionRecordFormatParityTest` 2, `PostSignatureAuditCommandTest` 2, `ActivityProjectionTest` 7, `HighTrafficTest` 6, `ArchiveThreadCommandTest` 3, `ImportInstanceCommandTest` 9, `ContentImportPlannerTest` 14, `RemovalReceiptTest` 3, `RetractionReceiptTest` 4, `ReadModelProvenanceTest` 6, `ImportedQuoteSeedScoringTest` 4, `ReadModelViewerTagsProjectionTest` 1, `ViewerTagLookupParityTest` 1, `ReadModelThreadLabelsTest` 2, `ReadModelPostReactionsTest` 2, `WriteApiSmokeTest` 189.
- Notes: the incremental parity test omits the invalid reply-target label; the incremental updater doesn't index it in `reaction_signatures` for either format (a gap that predates this cycle and is unrelated to format). Activity detail and high-traffic safety are covered by their existing suites plus code reading (they parse through the shared parsers), not by new unified-specific assertions.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 3 - Server producers write the unified format

**Original estimate: 0.15**
**Retrospective stage work: 0.15**

- Changes:
  - `LocalWriteService::buildReactionRecord()` replaces `buildThreadLabelRecord()` and `buildPostReactionRecord()`; it serves keyless thread/post tags, hashtag labels (thread, reply and signed-post paths) and offline signed-intent replay. ID prefixes and paths are unchanged.
  - Clock-skew fallback: the prepared record now keeps the client's submitted layout and replaces only `Record-ID` and `Created-At`. Otherwise current browsers (still signing the legacy layout until Stage 4) would reject the prepared record, because their check compares everything after line 2.
  - Tests: new `testUnsignedReactionsWriteUnifiedFormatAndDeduplicateAcrossFormats` (exact unified bytes for both kinds; a legacy vote blocks a same-author/target/tag write; a unified vote blocks a repeat). The offline-intent test asserts unified headers. Hashtag tests now assert `Target-ID`/`Tags`, and the legacy-label retraction test now rewrites its fixture to genuine legacy headers to keep its intent.
- Verification: `WriteApiSmokeTest` 190/190, `OfflineOutboxSendTest` 4/4, `ImportedQuoteSeedScoringTest` 4/4, `HighTrafficTest` 6/6, `ArchiveThreadCommandTest` 3/3, `ActivityProjectionTest` 7/7, `RemovalReceiptTest` 3/3, `RetractionReceiptTest` 4/4, `AcceptedRetractionTest` 4/4, `ImportInstanceCommandTest` 9/9, `ReactionRecordFormatParityTest` 2/2 passed. `LocalAppSmokeTest` 154/155; the one failure (`testRepositoryArchiveDownloadFilenamesIncludeReadableTimestamp`) is in the recorded baseline.
- Notes: browser-signed submissions still use the legacy layout until Stage 4.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Stage 4 - Signed browser path

**Original estimate: 0.20**
**Retrospective stage work: 0.15**

- Changes:
  - `thread_reactions.js` builds unified bytes through a named `reactionRecord()` (exposed as `ForumThreadReactions.reactionRecord` for tests).
  - `LocalWriteService::signedReactionInput()` accepts the exact unified layout, or the exact legacy layout via `LegacyReactionHeaders::signedClientRecord()` (ER1: cached older clients, removable with the adapter). The skew-fallback prepared record keeps whichever layout was submitted (Stage 3).
  - `signed_reaction_submission_v1.md` describes the unified layout, the deprecated legacy acceptance and the new hashtag/offline output.
  - Tests: byte agreement between JS and PHP builders (`ReactionRecordFormatParityTest`); the tampering test now runs for both layouts and adds wrong-scope and mixed-header rejections; the clock-fallback test runs for both layouts and asserts the prepared record keeps the client layout; the browser test asserts unified bytes.
- Verification:
  - `node tests/browser/signed_reactions.mjs` (local Chrome): all 7 scenarios passed — classic, Forte (including clock fallback) and QDB signed buttons with exact source bytes and verified status, guest setup, legacy keyless client, Activity/RSS across phone/desktop and four themes.
  - `WriteApiSmokeTest` 190/190, `BrowserSigningNormalizationTest` 82/82, `ForteBoardReaderTest` 4/4, `AssetFingerprintTest` 2/2, `OfflineOutboxSendTest` 4/4, `FeatureFlagsBehaviorTest` 4/4, `ReactionRecordFormatParityTest` 3/3 passed; `LocalAppSmokeTest` 154/155 with only the baseline failure.
- Notes: no visible UI change. Navigation icons and UI copy are not applicable.

*— Claude · 2026-10-30 UTC · Model: claude-opus-5-5*

## Enhancement requests

- **ER1 — easy deprecation and later removal of legacy support** (2026-10-30, during Stage 2)
  - Request: keep supporting old-style records, but make it easy to deprecate and later remove that support.
  - Disposition: in-scope refinement after the user chose "isolate only for now". Isolating legacy reading and the legacy client layout behind one removable adapter fits Stages 1–4 as an in-scope refinement. A legacy inventory or conversion path, and any rule about removing readers for signed legacy records, would change scope and conflict with Step 2 ("legacy header sets remain valid indefinitely") and PRINCIPLES "Keep our own backward compatibility".
  - Approval status: user chose "isolate only for now" (2026-10-30); the end state for existing legacy records (conversion, signed records) is deferred to a separate future cycle. No change to approved scope.
  - Outcome: adapter introduced in Stage 2 (`0012ca21`); legacy signed client layout added to it in Stage 4. Removal later = delete `LegacyReactionHeaders` and its two call sites.
