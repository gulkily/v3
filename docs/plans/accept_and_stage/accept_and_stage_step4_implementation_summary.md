> **Feature plan:** [Step 1](./accept_and_stage_step1_solution_assessment.md) · [Step 2](./accept_and_stage_step2_feature_description.md) · [Step 3](./accept_and_stage_step3_development_plan.md) · [Step 4](./accept_and_stage_step4_implementation_summary.md)

# Step 4: Implementation Summary — Accept and stage

- Debug mode: enabled. The project marker `.railspike_debug_on` was added on 2026-11-09 before Step 1 began, so this new workflow resolved to enabled. Steps 1–3 left out the field and their completed-step reports; those reports are missing and not reconstructed. The Step 4 report goes to [accept_and_stage_debug_log.md](./accept_and_stage_debug_log.md).
- Branch: `accept-and-stage`; planning commit `7613a02b`. Steps 1–3 were first pushed on `accept-and-stage-step1` during planning (a session hook asked for every file to be pushed); that branch holds only those three documents and the Step 3 ledger line, which moves into Stage 1 here.
- Related feedback: none found in `docs/feedback/items/` for clock skew, staging or text-rule rejections.

**Cycle estimate: 0.70**

## Step 4 entry

Study cycle `v3/accept_and_stage` (R1, P2) entered Step 4 on 2026-11-11 UTC. The Step 3 projection **0.70** is reaffirmed unchanged: inspection at entry found nothing that changes scope. Rater: Claude, single rater, exposed to the approved plans, the mockup and the code read for Step 3. Stage estimates copied from Step 3 (not new ratings): S1 0.06, S2 0.10, S3 0.05, S4 0.12, S5 0.08, S6 0.10, S7 0.08, S8 0.08.

Reuse decisions made at entry, from inspecting the existing code:

- Receipts reuse the signed reaction write pair and its crash journal (`writeSignedReactionPair`, `recoverSignedReactionWrites`) instead of a new write path.
- `/staged/` follows the private, per-viewer, no-store pattern of `InboxPageController` (`/unread`), not the static shared-page pattern, because its content is private to each viewer.
- No navigation destination is added, so `theme-nav-icons.css` is unaffected; operators reach `/staged/` through the same Unread link.

## Stage 1 - Accept skewed signed votes

**Original estimate: 0.06**

- Changes:
  - New `src/ForumRewrite/Canonical/RecordReceipt.php`: `isSkewed`, `contents`, `forWrite` (reuses a receipt already on disk), `effectiveCreatedAt`, `parse`. A receipt is one line, `Received-At: <UTC>`, in `<record>.txt.received`.
  - `LocalWriteService::signedReactionInput` no longer re-stamps a skewed vote. It returns the receipt as a fourth element; `writeSignedReactionPair` writes it first, both tag routes commit it with the pair (`signedReactionPaths`), and the crash journal accepts three or four elements.
  - Removed `ReactionClockSkew` and its two `catch` blocks in `TagApiController`. A `prepare_token` from an older answer is still honored.
  - `docs/specs/signed_reaction_submission_v1.md` describes the receipt and the retired 409.
  - Test `testSignedReactionsClockFallbackBindsPreparedBytesAndExpires` replaced by `testSignedReactionsWithSkewedClockAreAcceptedWithReceipt`.
- Verification:
  - Passed: `./v3 test WriteApiSmokeTest::testSignedReactionsWithSkewedClockAreAcceptedWithReceipt`. Covers 10 minutes behind and ahead, legacy and unified layouts, threads and posts, the receipt committed in the same commit, an exact retry keeping the same receipt, no receipt inside the window, and an old `prepare_token`.
  - Passed: `./v3 test --smoke` plus `testSignedReactionArchiveRoundTripRetainsBothCompanions`, `testSignedReactionRecoveryAfterPartialPairCommitAndRefreshFailures`, `testSignedReactionApiVerifiesCommentLikeAndRetainsBothTimes`, `testHistoricalReactionSignaturesAreVerifiedOnRebuildAndImport`, `OfflineOutboxSendTest`, `ApiRouteCatalogTest`: 452 run, 452 passed. Full suite not run.
  - Not covered: crash recovery of a four-element journal entry. The path reuses the three-element code with one extra file.
  - Inapplicable: UI, deployment. Sorting by receipt time arrives in Stage 2.
- Notes:
  - `public/assets/thread_reactions.js` still handles a `clock_skew` answer. It is now unreachable and left alone; removing it is cleanup, not part of this stage.
  - Content import (`ContentImportPlanner`) copies records by path and does not yet carry receipts. An imported skewed vote sorts by its own `Created-At`. Revisit in Stage 2.

**Retrospective stage work: 0.06**

- Estimate details: original scope unchanged; no revisions. The retrospective matches the original: the journal needed a small extension, offset by the class removal being trivial.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
