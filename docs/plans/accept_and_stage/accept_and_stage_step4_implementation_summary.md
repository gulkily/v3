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

## Stage 4 - Private staging store

**Original estimate: 0.12**

- Changes:
  - New `src/ForumRewrite/Write/StagedSubmissionStore.php`: `stage`, `find`, `listFor`, `discard`, `validPostId`. One folder per post ID under `<database dir>/private/staged-posts/` (production: `state/private/staged-posts/`, beside sessions and messages, outside exports and Git). Each holds the exact `record.txt` and `record.txt.asc` plus `staged.txt` in `Key: value` lines (`Reason`, `Field`, `Character: U+1F3A4 21`, `Staged-At`), following the no-JSON rule in `AGENTS.md`. Writes go to a temporary folder and are renamed into place.
  - `createSignedPost` runs the strict validator first. On failure, `stageSignedPost` stages the post only when the post ID, author and Created-At parse from a lenient header read, the key is registered and not retired, the detached signature verifies over the original bytes, and the post is not already published. Otherwise the original checks run unchanged, so replays and unknown keys behave as before.
  - Reasons: an unsupported header is named first (`Unsupported header: Imported-Score-Seed.`), then CR LF (`Records use LF line endings, not CR LF.`), then text-rule messages with their field and character positions, then the validator's own message.
  - Caps: 1 MiB record (`RecordLimits::ARMOR`) and 20 staged posts per author; the 21st is refused with "Fix or discard one at /staged/ first." A different author cannot overwrite a staged post ID (409).
  - `create_signed_post` answers a staged post with HTTP 202 and `{"status": "staged", "code": "staged", "post_id", "thread_id", "reason", "field", "chars", "staged_at"}`, and skips fast scoring.
- Verification:
  - Passed: new `SingleRequestPostingTest::testSignedPostsThatBreakARuleAreStaged`. Emoji, CR LF, a missing final LF and `Imported-Score-Seed` are each staged with the expected reason, field and characters. Exact bytes are kept, an exact retry returns the same `staged_at`, HEAD and `records/` are untouched, a bad signature and an unregistered key are refused, and the 21st post hits the cap.
  - Passed: new `StagedSubmissionStoreTest` (replace, identical resubmission, cross-author 409, newest-first listing, discard, path-traversal IDs ignored), registered in `tests/run.php`.
  - Passed: `SingleRequestHttpTest::testUnicodeRoundTripAndSymbolRejection` now expects HTTP 202 `staged` with `U+1F642` for an emoji post from a registered key, and checks the post is not readable through `get_post`.
  - Passed: `./v3 test --smoke --jobs=2` plus `StagedSubmissionStoreTest SingleRequestPostingTest SingleRequestHttpTest SignedPostIdentityTest SignedPostBundleValidatorTest AgentPostingApiTest BrowserSigningNormalizationTest TestRunnerBehaviorTest ApiRouteCatalogTest`: 549 run, 549 passed. Full suite not run.
  - Inapplicable: UI (Stage 6), `frm.py` (Stage 7).
- Notes:
  - Two behaviors changed for clients: an emoji post from a registered key now gets 202 instead of 400, and `docs/examples/agent_single_request_post.sh` treats anything but 200 as rejected. The example's wording is fixed with the agent docs in Stage 8.
  - Text-rule errors from `UnicodeTextPolicy` that are plain `RuntimeException`s (unsupported spacing or format characters) stage with their message but no character positions; adding positions there is outside this slice.

**Retrospective stage work: 0.12**

- Estimate details: original scope unchanged. CR being caught as a control character and seed headers failing in the parser needed reason ordering by the bytes rather than the message; otherwise as planned.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 5 - A signed fix publishes the staged post

**Original estimate: 0.08**

- Changes:
  - `createSignedPost` calls `discardStagedCopy` once the post is committed, and on an exact replay, so a crash between commit and discard heals on the next retry. Only a staged copy from the same author is discarded; a discard failure is logged, not raised, because the post is already published.
  - A fix that still breaks a rule goes back through Stage 4's `stage`, which replaces the staged copy and its reason (same author).
  - A fix keeps the original post ID and `Created-At`, so Stage 2's receipt gives it the publication time.
  - Correction to Stage 4: the store folder is now `<database dir>/private/staged-posts-<16 hex of sha256(repository root)>`, the same keying as the signed-reaction journal. Tests keep their databases directly in the system temp directory, so the unkeyed folder was shared between test runs (found here as 51 staged posts where 1 was expected). In production the database directory belongs to one instance, so this only changes the folder name.
- Verification:
  - Passed: new `SingleRequestPostingTest::testSignedFixPublishesAStagedPost`. An emoji post sent 20 minutes ago is staged; a second bad fix updates the character (`U+1F3B6`) with still one staged item; the clean fix publishes byte-for-byte under the same ID with a receipt, the staged copy is gone, the read model has one row, and an exact retry replays. A staged copy under a different key stays when this author publishes the same ID.
  - Passed: `./v3 test --smoke --jobs=2` plus `StagedSubmissionStoreTest SingleRequestPostingTest SingleRequestHttpTest SignedPostIdentityTest AgentPostingApiTest`: 460 run, 460 passed. Full suite not run.
  - Inapplicable: UI (Stage 6).
- Notes:
  - The plan said `find` would ignore staged items whose post already exists. That check belongs to the `/staged/` page (Stage 6), which knows the repository; the store does not.

**Retrospective stage work: 0.07**

- Estimate details: original scope unchanged; the store-location correction was small.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*

## Stage 6 - /staged/ page and the Unread link

**Original estimate: 0.10**

- Changes:
  - New `src/ForumRewrite/Http/StagedPageController.php` and `templates/pages/staged.php`, routed at `/staged` and `/staged/` in `Application` (and listed in `isApplicationRoute`). Built on the `InboxPageController` pattern: private, per viewer, `noStoreHeaders()`, plain form POST.
  - A signed-in viewer sees staged posts from any of their username's approved keys (`ApprovedUserKeyResolver`, as Unread does); an operator (`ApprovalPolicy::isOperator`) sees all, labelled with the author's name, or key when the read model has no profile. Replies show their thread's subject. Posts that have since been published are left out.
  - Each item shows a `chip--pending` "Held" chip, kind and date, subject, a 240-character preview, the reason with each character's code point and position, the post ID (for `frm.py --replace`) and a Discard button. One line above the list keeps the privacy fact and the recovery step; no other explanatory copy.
  - Discard: POST `post_id`, checked with `SameOriginRequest` and against the items this viewer may see; success redirects 303 back to `/staged/`, failure re-renders with "Couldn’t discard. Nothing was removed." (409).
  - A signed-out viewer gets "Nothing staged." A browser holding a key is decided before paint (`account_state_script`): it shows the heading only while the new `public/assets/staged_access.js` signs in through `PrivateSiteAuth` and reloads once; a refused sign-in shows Retry. This mirrors `private_message_access.js`, without its approval requirement (staging does not need approval).
  - Unread (`InboxPageController`, `inbox.php`) shows a "Staged (n)" link card at the top when this viewer can see any staged post.
  - `public/assets/site.css`: one rule, `.staged-item__id code { overflow-wrap: anywhere; }`, so post IDs wrap on phones.
  - Not a navigation destination, so `theme-nav-icons.css` is unchanged.
- Verification:
  - Passed: new `StagedPageTest` (registered in `tests/run.php`): logged out and another member see "Nothing staged." and no staged text; the author sees only theirs, with code point, position and Discard; the operator sees both, author names and the reply's thread link; a published post drops out; cross-site discard is refused and keeps the post; another member cannot discard; the author and the operator can; Unread shows "Staged (1)" for the author, "Staged (2)" for the operator and nothing for others.
  - Passed: `./v3 test --smoke --jobs=2` plus `StagedPageTest StagedSubmissionStoreTest InboxPageTest InboxFeedTest WebServerRoutingTest NavIconSetsTest ApiRouteCatalogTest SingleRequestPostingTest`: 476 run, 476 passed. Full suite not run.
  - Passed, rendered check: the real templates rendered through the controller and served with the site's CSS and scripts, screenshotted with Playwright at 1100 px and 390 px, light and dark, in these states: author with items, operator, signed out, failed discard, empty after discard, Unread with the link. The first pass found the post ID overflowing at 390 px; after the wrap rule and moving the ID onto its own line, no state overflows horizontally. Screenshots were working files and are not committed.
  - Passed, by inspection: `StaticArtifactBuilder` and the offline snapshot code never read the staging folder or render `/staged/`; the offline snapshot is built from the read model, which never holds staged posts.
  - Pending: the sign-in-and-reload path for a signed-out browser that holds a key was not exercised in a real browser session; it reuses `PrivateSiteAuth.authenticate` the way Messages does. Checked again on zenmemes.com in Stage 8.
  - Not covered: themes other than the default, light and dark. The page uses only existing card, meta and chip classes.

**Retrospective stage work: 0.12**

- Estimate details: original scope unchanged. Above the original because of the signed-out sign-in path, the name lookups for the operator view and the phone overflow fix.

*— Claude · 2026-11-11 UTC · Model: claude-opus-5-5*
