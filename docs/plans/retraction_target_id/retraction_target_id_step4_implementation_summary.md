> **Feature plan:** [Step 2](./retraction_target_id_step2_feature_description.md) · [Step 3](./retraction_target_id_step3_development_plan.md) · [Step 4](./retraction_target_id_step4_implementation_summary.md)

Debug mode: enabled (inherited from Step 3)

Effort study: `railspike-effort` / `v3/retraction_target_id`; [host ledger](../../effort_estimation_trial.md). Standing P2 enrollment. Child of `v3/post_records`; not an independent outcome.

Extended estimates: enabled (inherited from Step 3)

**Cycle estimate: 0.40** (Step 4 entry; reaffirms the Step 3 projection unchanged)

Approval: Approved Step 3 by the maintainer, 2026-11-13T05:09:00Z. Branch `feat/retraction-target-id` from `05486f22`. Planning commit `a7f0fb7`. Code drift since the planning revision `273bf937` was checked: `delete_record.php` help text and `LabelRemoval::inspect`'s new `$source` parameter don't touch the target header, so the plan stands.

Stage originals, copied from Step 3 (not new ratings): Stage 1 **0.12**, Stage 2 **0.12**, Stage 3 **0.08**. Stage work only; they don't add up to the cycle.

Tests: per the repository rules, only the smoke set plus the tests for what changed, through `./v3 test --smoke …`. The full suite was not run.

## Stage 1 - Read both header forms; reserve reaction prefixes

**Original estimate: 0.12**
**Retrospective estimate: 0.12**

- Changes:
  - New `Canonical/RemovalTarget.php`. `fromHeaders()` returns the target from `Target-ID` / `Target-Created-At`, or from the older `Post-ID` / `Post-Created-At`. It rejects a file that mixes them or has neither. `isReactionId()` checks for the `post-reaction-` and `thread-label-` prefixes.
  - `PostRetractionRecordParser`: `Post-ID` is no longer a required header. The target comes from the helper, and errors name whichever header the file used.
  - `RemovalReceipt`: two exact field lists, current and legacy. `encode()` accepts either and writes it in that form's order, so stored receipts round-trip byte for byte. `decode()` accepts either and returns headers as stored.
  - `RetractionReceipt::decode` and `LabelRemoval::decode` read the target and its time through the helper.
  - `PostRecordParser` rejects Post-IDs that start with a reaction prefix.
  - New `tests/RetractionTargetIdTest.php` (6 tests).
  - Writers are unchanged in this stage and still emit `Post-ID`.
- Verification:
  - Passed: `./v3 test --smoke RetractionTargetIdTest RemovalReceiptTest RetractionReceiptTest AcceptedRetractionTest CanonicalRecordParsersTest ReadModelPostRetractionsTest RetractionTransactionTest PostRetractionScriptTest RetractionMaintenanceTest` gave 474 run, 474 passed, 0 failed. That includes 85 tests from the named classes.
  - Passed: baseline before changes, `./v3 test --smoke` plus four retraction classes, 461 passed.
  - Passed: `php -l` on the changed classes.
  - Not run: the full suite, per the repository rules; the maintainer runs it.
- Notes:
  - All five readers named in Step 3 are covered. Imports (`ArchiveRecordCatalog`, `ContentImportPlanner`) and `AcceptedRemoval` reach receipts only through `AcceptedRetraction::read` and `LabelRemoval::read`, so they need no change.
  - `PostRetractionRecord::$postId` keeps its name. It holds the target ID and has about 100 callers; renaming it belongs with `post_records`, when the target can be something other than a post.
  - An unknown request header besides the target is still ignored, as before. Only the target headers are checked for mixing.
- Estimate details: original scope as in Step 3, unchanged; no revisions. Retrospective: about as planned; finding that receipts must round-trip unchanged settled the "return headers as stored" design. Rater: Claude, the same exposed rater.

*— Claude · 2026-11-13 UTC · Model: claude-opus-5-5*

## Stage 2 - Write the new form; accept target_id

**Original estimate: 0.12**
**Retrospective estimate: 0.12**

- Changes:
  - `LocalWriteService`: the retraction request builder writes `Target-ID`. A new `retractionTargetInput()` runs at the start of `prepareRetraction` and `finalizePreparedRetraction`. It takes `target_id`, falls back to `post_id`, and rejects both when they differ. Everything downstream still reads the resolved value, so prepared-token matching and replay are unchanged. Prepare and result responses add `target_id` and keep `post_id`.
  - `RetractionReceipt::encode` and `LabelRemoval::encode` write `Target-ID` and `Target-Created-At`, so both signed receipts and `./v3 delete-record` / label-repair receipts use the new form.
  - `public/assets/browser_signing.js` sends `target_id` to both retraction calls.
  - Tests: new `WriteApiSmokeTest::testRetractionWritesTargetIdAndStillReadsPostIdRetractions`. It covers a conflicting `target_id`/`post_id`, a `target_id` prepare and finalize, the written request and receipt bytes, a hand-written pre-rename `Post-ID` retraction side by side with a new one through a full `./v3 rebuild`, and an `already_retracted` replay through the `post_id` alias. New `RetractionTargetIdTest::testLocalOperatorReceiptsAreWrittenWithTargetId`. `RetractionReceiptTest` now expects `Target-ID` in the receipt.
- Verification:
  - Passed: `./v3 test --smoke` plus RetractionTargetIdTest, RemovalReceiptTest, RetractionReceiptTest, AcceptedRetractionTest, CanonicalRecordParsersTest, ReadModelPostRetractionsTest, RetractionTransactionTest, PostRetractionScriptTest, RetractionMaintenanceTest, RetractionSweepTest, AbsentRetractionSnapshotTest, RetractedLeafFilterTest, HistoryPurgeTest, HistoryPurgePlannerTest, PurgeHistoryCommandTest, SourceRouteCommitMapTest, ThreadTitleWithdrawalAndConsumersTest, HistoryRewriteTest, WriteApiSmokeTest, SingleRequestPostingTest and LlmsTxtRendererTest: 746 run, 746 passed, 0 failed. That includes the existing operator-retraction tests and the existing `post_id` prepare helper.
  - Passed: `./v3 test DeleteRecordCommandTest DeleteThreadCommandTest ContentImportPlannerTest ContentImportRecoveryTest ImportInstanceCommandTest ImportedContentPublicationTest`, 51 passed.
  - Passed: `php -l` on the changed PHP, and `node --check public/assets/browser_signing.js`.
  - Inapplicable here: a browser click-through of Retract. The browser path differs only in the parameter name, which is covered by the API test. The live check is in Stage 3.
  - Not run: the full suite.
- Notes:
  - Existing clients that send `post_id` (`public/frm.py`, Claude's zenmemes-posting skill, older cached `browser_signing.js`) keep working through the alias.
  - `delete_record.php` needed no change. Its `Post-ID` output fields describe the deleted record itself, not a retraction target, and its receipts come from `LabelRemoval::encode`.
- Estimate details: original scope as in Step 3, unchanged; no revisions. Retrospective: as planned. Rater: Claude, the same exposed rater.

*— Claude · 2026-11-13 UTC · Model: claude-opus-5-5*
