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

## Stage 2 - Skewed signed posts sort by arrival

**Original estimate: 0.10**

- Changes:
  - `createSignedPost` no longer refuses a post outside 600 seconds. It commits `<post>.txt.received` with the post (and with a first-use bootstrap post when that one is skewed). Hashtag label records and title records the server builds for a skewed post use the arrival time.
  - Removed `SignedPostBundleValidator::assertFresh`; its test now checks the window through `RecordReceipt::isSkewed`.
  - Read model: every `created_at` the two builders fill from a post, thread label or post reaction goes through `RecordReceipt::effectiveCreatedAt`. That is `ReadModelBuilder` (posts, label activity, reaction activity) and `IncrementalReadModelUpdater` (post insert, thread insert, last activity, post activity, reaction activity, label activity), the latter through one helper, `effectivePostCreatedAt`.
  - Receipts are applied at fill points, not when records load: several write paths and `ApprovalAcceptance` rebuild a post's path from its loaded `Created-At`, and changing that value would break them.
  - `ApprovalHistory` built a post path from the read model's `created_at`; it now falls back to the dates in the post ID, so a receipt time cannot hide an approval post.
  - Retraction (`RetractionDeletion`), source cleanup (`SourceLabelRemoval`) and `HistoryPurgePlanner` include `.txt.received` beside `.txt` and `.txt.asc`.
- Verification:
  - Passed: new `SingleRequestPostingTest::testSkewedSignedPostsPublishWithReceipt`. A thread 20 minutes behind, a thread a day ahead and a reply an hour behind each publish as signed, commit the receipt in the post's commit, get the arrival time in `posts.created_at` (and `threads.root_post_created_at`), match a full rebuild exactly (`ReadModelSnapshot`), and replay on an exact retry.
  - Passed: new `RetractionTransactionTest::testRetractionRemovesAReceipt`; `HistoryPurgePlannerTest` now expects the receipt among purge targets.
  - Passed: `./v3 test --smoke --jobs=2` plus `ApprovalHistoryTest ActivityProjectionTest ReadModelPostReactionsTest ReadModelThreadLabelsTest ReadModelPostRetractionsTest DeleteRecordCommandTest ContentImportPlannerTest SingleRequestPostingTest SingleRequestHttpTest SignedPostBundleValidatorTest SignedPostIdentityTest HistoryPurgeTest HistoryPurgePlannerTest RetractionTransactionTest AcceptedRetractionTest ReactionRecordFormatParityTest ViewerTagLookupParityTest StandardActivityPaginationTest SourceRouteCommitMapTest AgentPostingApiTest`: 526 run, 526 passed. Full suite not run.
  - Direct `->createdAt` reads outside the read model, reviewed: path derivations in `LocalWriteService`, `ApprovalAcceptance`, `ArchiveRecordCatalog` and `SignedPostIdentity` need the signed value and keep it; `AttributionCutoff`, `InvitationLedger`, invitation replay order, `ContentImportPlanner`, `LegacyTimestampBake` and `ImageBackup` read records that are server-built or compared byte-for-byte, so they are unaffected. `ApprovalAcceptance` rejects an acceptance older than `Created-At`; approvals are server-built through `prepare_approval`, so skew does not reach it.
  - Inapplicable: UI. Verified-attribution cutoffs keep comparing the signed `Created-At` in both builders, so they stay consistent.
- Notes:
  - Ordering of label and reaction operations inside a rebuild still follows the signed `Created-At` (add and remove replay order); only displayed and sorted times use the receipt.
  - Instance import (`ContentImportPlanner`) still copies records without receipts; an imported skewed post sorts by its signed time. Recorded as a follow-up; carrying receipts through import is outside this slice.

**Retrospective stage work: 0.12**

- Estimate details: original scope unchanged. Retrospective above the original because two path consumers (`ApprovalHistory`, source cleanup) and the purge planner surfaced only during the stage.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 3 - Reordered headers are stored as signed

**Original estimate: 0.05**

- Changes:
  - `SignedPostBundleValidator::validateRecord` still rebuilds the canonical form, but a mismatch caused only by header order now passes (`sameHeadersInAnyOrder`: same header lines, same body). Unknown, missing or duplicate headers and any other byte difference still fail. The error now reads "unsupported headers or noncanonical bytes".
  - Implemented as a private method on the validator rather than the `PostAuthoredHeaders` class named in Step 3: the check is ten lines and only the validator uses it.
- Verification:
  - Before changing it, searched `src`, `public/assets` and `public/*.py` for order-dependent header parsing: only record builders match; every reader goes through `GenericTextRecordParser`, which takes any order.
  - Passed: new `SignedPostBundleValidatorTest::testReorderedHeadersAreAcceptedAsSigned` (fully reversed headers accepted; `Imported-Score-Seed`, a changed body and a missing header refused). `testOptionalThemeHeaderFollowsBodyFormat` now expects its two reordered cases to be accepted; its duplicate and invalid-value cases are still refused.
  - Passed: new `SingleRequestPostingTest::testReorderedHeadersPublishAsSigned`. A post with reversed headers publishes byte-for-byte, appears after a full rebuild, is marked `import` by `ContentImportPlanner` (parse plus signature check on the receiving side) and replays on retry.
  - Passed: `./v3 test --smoke --jobs=2` plus `SignedPostBundleValidatorTest SingleRequestPostingTest SingleRequestHttpTest SignedPostIdentityTest AgentPostingApiTest BrowserSigningNormalizationTest`: 547 run, 547 passed. Full suite not run.
  - Inapplicable: UI, read model (records were already parsed in any order).
- Notes:
  - `canonical_post_record_v1.md` is updated with the other specs in Stage 8.

**Retrospective stage work: 0.04**

- Estimate details: original scope unchanged; slightly under the original because no reader needed changes.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
