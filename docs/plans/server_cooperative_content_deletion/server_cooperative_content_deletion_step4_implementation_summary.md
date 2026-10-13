> **Feature plan:** [Step 1](./server_cooperative_content_deletion_step1_solution_assessment.md) · [Step 2](./server_cooperative_content_deletion_step2_feature_description.md) · [Step 3](./server_cooperative_content_deletion_step3_development_plan.md) · [Step 4](./server_cooperative_content_deletion_step4_implementation_summary.md)

Debug mode: enabled

# Server Cooperative Content Deletion Step 4 Implementation Summary

## Plan clarifications

- Stage 6 control placement: the Retract control goes in `partials/post_card.php` and `partials/thread_root_card.php` (what `thread.php` delegates to), plus `post.php`. Paned forte views are not part of the first slice. This refines file names only; Stage 6's scope and the Completion Contract are unchanged.

## Stage 1 - Retraction record family
- Changes:
  - New `PostRetractionRecord` and `PostRetractionRecordParser`: headers Record-ID, Created-At, Post-ID, Operation `retract`, Author-Identity-ID; empty body required; Record-ID must equal `post-retraction-<Post-ID>`, which makes a retraction one-per-post and idempotent.
  - `CanonicalPathResolver::postRetraction`, `CanonicalRecordRepository::loadPostRetraction` (path must match Record-ID).
  - Registered `records/post-retractions/` in `SourcePathValidator`, `ArchiveRecordCatalog` (record path, identity key `post-retraction:<id>`), and `ContentImportPlanner` (depends on the post and the author identity).
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest` and `ContentImportPlannerTest`: all pass, including new parser, repository path-mismatch, resolver, and planner tests (import with signature; missing post reported as invalid).
  - Full suite: no failures attributable to this stage. Pre-existing failures are unchanged. `TestRunnerBehaviorTest::testInterruptedRunReportsCurrentSlowTest` failed once in the full run, passed in isolation, and failed once on stashed `main`, so it is timing-flaky.
- Notes:
  - Retraction records are not excluded from import (unlike approval seeds), so a re-import carries the tombstone; suppression itself is Stage 2.
  - Signature file convention (`.asc`) already covered by `ArchiveRecordCatalog::isSignaturePath`; the signing flow is Stage 3.

## Stage 2 - Read-model suppression
- Changes:
  - Derived read model: nullable `retracted_at` on `posts` and `threads`; `ReadModelMetadata::SCHEMA_VERSION` 15 → 16 (the schema fingerprint changes, so live databases rebuild).
  - `ReadModelBuilder::indexPosts` loads `records/post-retractions/` and, for a retracted post, stores a null subject, empty body, and null event fields, so the thread row gets an empty subject and body preview. Reply rows, reply counts, and the thread's structure are untouched.
  - A retraction applies only when its `Author-Identity-ID` equals the post's author. Posts tagged `identity`, `approval`, or `invitation` are never suppressed, because trust state is derived from their bodies.
  - Activity label for a retracted post is `Deleted post`. A thread-subject record can no longer refill a retracted root's subject, in both the full rebuild and `IncrementalReadModelUpdater::applyThreadSubjectWrite`.
- Verification:
  - New `tests/ReadModelPostRetractionsTest.php` (registered in `tests/run.php`): a marker string is absent from every text column of every table after retraction; replies remain; a retraction signed by another identity is ignored; deleting then restoring the original post file stays suppressed.
  - Full `php tests/run.php`: 1113 run, 9 failed; all 9 are the same long-standing failures listed by the runner, none new.
- Notes:
  - Deviation from the plan: no dedicated incremental retraction path was added. Stage 3 will refresh derived state through the existing full-rebuild path (`refreshDerivedStateAfterCommit`); an incremental path can be added later if rebuild cost matters.
  - Search and feed filtering depend on the same `posts`/`threads` rows, so they are covered here; raw-record and static-artifact exposure is Stage 4.
  - Because the authority tags are exempt, Stage 3 must reject retraction requests for those posts rather than record a no-op tombstone.

## Stage 3 - Signed retraction write path
- Changes:
  - `LocalWriteService::prepareRetraction(array $input): array` and `finalizePreparedRetraction(array $input): array`, following the prepare/finalize approval pattern. Authority is the post's own `Author-Identity-ID`, checked at prepare and again at finalize, plus detached-signature verification against that identity's public key.
  - Identity, approval, and invitation posts are refused, matching the Stage 2 index-time exemption.
  - Finalize writes the record and `.asc`, commits `Retract post <id> by <identity>`, refreshes the read model through the existing full-rebuild path, and invalidates the thread/reply static artifacts. Result carries `retraction_status` (`complete` or `partial`) and `failed_step`.
  - Retry: when the record already exists, prepare returns `already_retracted` and re-runs the refresh if the stale marker is set, so a partial retraction recovers without re-signing.
  - Routes `/api/prepare_retraction` and `/api/create_prepared_retraction` (POST) via `WritePostAndIdentityApiController`, registered in `Application`'s route list.
- Verification:
  - New `WriteApiSmokeTest` tests with real gpg keys: author success (record, `.asc`, commit message, thread page and read-model body suppressed, repeat reports `already_retracted`); non-author rejected with no file written; invalid signature rejected with the prepared token retained; forced refresh failure reports `partial`, the stale marker is set, and a retry reports `complete` and clears it.
  - Test helper `signCanonicalRecord` now removes a stale `.asc` so one key home can sign twice.
  - Full `php tests/run.php`: 1115 run, 9 failed, all long-standing and unchanged. An earlier full run during this stage was invalidated because PHP was uninstalled from the host mid-run; it was rerun after PHP was restored.
- Notes:
  - Refusal of identity/approval/invitation posts is enforced in code but has no dedicated test yet; Stage 7's end-to-end test should cover it.
  - The retraction record's `Created-At` is server time at prepare, and the signature covers the whole canonical record, so a stale prepared token (10-minute expiry) cannot be replayed with a different record.
  - Static artifact invalidation here covers the thread/reply pages only; the wider audit (raw record route, exports, search/feeds) is Stage 4.

## Stage 4 - Close non-template leaks
- Changes:
  - New `Canonical/RetractedPosts`: the one definition of when a retraction applies (signer equals the post's author; never identity/approval/invitation posts) and of which post ids are effectively retracted on disk. `ReadModelBuilder` and `LocalWriteService` now use it instead of duplicating the rule.
  - `SourceFileController`: `/source/current/...` and `/source/blob/<commit>/...` answer `410 This post was retracted by its author.` for a retracted post's record file, at the current path or any historical commit.
  - `StaticArtifactInvalidator::invalidateAllGeneratedPresentation()` (the former `invalidateApprovalSeed` body, which now delegates to it) is used by retraction finalize. It retires every generated HTML file, the active release pointer, and served offline snapshot copies, so tag, profile, listing, and snapshot artifacts cannot keep the prose. A failure surfaces as `partial` / `artifact_invalidate`.
  - `ReadModelBuilder::rebuild` sets `PRAGMA secure_delete = ON` so freed pages in the downloadable read-model file are zeroed.
- Verification:
  - New `WriteApiSmokeTest::testRetractedPostProseIsAbsentFromEveryServedSurface`, with positive controls that the marker is served before retraction: after retraction the marker is absent from the home, threads, thread, post, `/api/get_post`, `/api/get_thread`, `/api/list_index`, activity (HTML and RSS), latest, top, tags, and users routes; the post is not listed in search; both source routes return the 410 text; seeded tag/listing/snapshot artifacts are deleted; and the raw read-model file (plus `-wal`/`-journal`) no longer contains the marker.
  - Full `php tests/run.php`: 1116 run, 9 failed, all long-standing and unchanged.
- Notes:
  - Known retained copies, not changed in this slice (Step 1 Option A): `/downloads/repository.tar.gz` and `.zip` archive the entire checkout including `.git`, so they still contain the original file and its history; the UI copy in Stage 6 must say so. Filtering only the working-tree file would not change that and would leave the archive inconsistent with its own Git state.
  - `/source/commits/<sha>` lists file paths and the commit subject (`Create thread <id>`), not prose, so it is unchanged.
  - Operator scripts that read canonical files directly (`archive_thread`, `thread_attributes`, `delete-record`) are out of scope and unchanged.
  - Agent and analysis workflows read bodies from the read model, so they see an empty body for a retracted post.

## Stage 5 - Deleted-post placeholder
- Changes:
  - `retracted_at` is now selected by the thread and post queries that feed templates: `Application` (`fetchThread`, `fetchThreadPosts`, `fetchPost`), `ThreadRepository` (two thread queries, two post queries), and `AuthoredContentRepository` (thread and post queries).
  - One wording source, `Support/RetractedPostNotice` (`TEXT`, `TITLE`), and one shared partial `partials/retracted_post_notice.php`, used by `post_card.php` (replies and `/posts/<id>`) and `thread_root_card.php` (thread roots, including the QDB quote layout).
  - Retracted posts drop Like/Flag/thread-reaction, agent-reply, Codex handoff, and analysis controls; the Reply link and permalink stay so conversation structure is intact.
  - `threadTitle` closure, `BoardPageController::displayThreadTitle`, and `Application::displayThreadTitle` return `Deleted post` for a retracted root; `thread_card.php` hides the body preview for it.
  - Forte paned views (`paned_board_content_article.php`, `paned_thread_reply_tree.php`) render the same wording inline.
- Verification:
  - New `WriteApiSmokeTest::testRetractedRootShowsPlaceholderKeepsRepliesAndDropsAuthorActions`: after retraction the thread page shows the notice, the `Deleted post` title, and the unchanged reply; the root's `apply-thread-tag` action is absent while reply actions remain; the board listing shows `Deleted post` and not the original subject; `/posts/<id>` and the forte view show the notice and no prose.
  - Full `php tests/run.php`: 1117 run, 9 failed, all long-standing and unchanged.
- Notes:
  - Not separately covered by a test: the activity/forte list rows other than the two paned partials, and profile/username page lists. They read the same `posts`/`threads` rows, which are already blank and titled via the shared helpers; a Stage 7 sweep should open them with a retracted post present.
  - The QDB quote layout branch was changed but has no dedicated test; its placeholder text uses `RetractedPostNotice::TEXT`.
  - Default `/threads/` in the test fixture renders no thread cards without `?view=all`; the board assertion uses `?view=all&sort=newest`.

## Stage 6 - Retract action and status display
- Changes:
  - Shared partial `partials/retract_post_control.php`, included by `post_card.php` (replies and `/posts/<id>`) and `thread_root_card.php` (non-QDB thread roots). It shows a Delete button and a status line only when the viewer's identity equals the post's `author_identity_id`, the post is not already retracted, and it is not an identity/approval/invitation post. `post()` now passes `viewerProfile` to `post.php`.
  - `browser_signing.js`: `submitSignedRetraction(postId)` (exposed as `ForumBrowserSigning.submitSignedRetraction`) prepares, signs the canonical record with the stored browser key, and finalizes; an already-retracted post skips signing so a repeat acts as a refresh retry.
  - New `public/assets/post_retraction.js` (loaded on thread and post pages): confirmation dialog, progress text, then `complete` reloads the page, `partial` shows the failed step with a Retry deletion button, and errors re-enable the button.
  - Wording lives in `Support/RetractedPostNotice` (`CONFIRM`, `DONE`): states that the post stops being shown here and replies stay behind a placeholder, and that version history, backups, and copies others saved are not erased.
- Verification:
  - `WriteApiSmokeTest::testRetractControlAppearsOnlyForTheAuthorOfAnUnretractedPost`: control absent for anonymous and other viewers, present for the author on thread and post pages with the honest copy and script reference, absent after retraction.
  - New `PostRetractionScriptTest` (with `tests/fixtures/post_retraction_script_check.js`): `node --check` on both scripts, plus a stubbed-DOM run covering declined confirm, complete, partial (Retry label, failed step shown), server error, and signing unavailable.
  - Full `php tests/run.php`: 1120 run, 9 failed, all long-standing and unchanged.
- Notes:
  - Not exercised end to end in a real browser: key loading and OpenPGP signing inside `submitSignedRetraction`. Server-side prepare/finalize with real gpg signatures is covered in Stage 3; the browser path reuses `ensureActionIdentity`, `currentAuthorIdentityId`, and `signCanonicalRecord` exactly as `submitSignedApproval` does. A manual run in a browser with a stored key is advisable before release.
  - The control is not added to the forte paned views or the QDB quote layout, per the plan clarification at the top of this summary.
  - Copy for review: the confirmation and completion strings in `src/ForumRewrite/Support/RetractedPostNotice.php`.

## Stage 7 - End-to-end verification and documentation
- Changes:
  - New reference doc `docs/reference/author_post_retraction.md` (behavior, limits, record format, write API, operator notes); `docs/reference/v3_cli.md` `delete-record` section now points to it as the separate operator mechanism.
  - Test helper `createAuthoredReply`; `linkSigningIdentity` also returns `profile_slug`.
- Verification:
  - New `WriteApiSmokeTest::testRetractionEndToEndKeepsRepliesRejectsOthersAndSurvivesReimportOfTheOriginalArchive`, in one flow with real gpg signatures:
    - a different identity's retraction is rejected and HEAD does not move;
    - an identity/approval authority post by the same author is refused (this closes the Stage 3 and Stage 5 test gaps);
    - the author retracts a signed reply and then the thread root, both `complete`;
    - the prose marker is absent from the thread page, activity, latest, top, users, list-index, post API, profile page and profile API, and the raw read-model file, while the guest reply stays and the placeholder is shown, with both rows marked retracted;
    - re-importing the original archive over the live repository through `ContentImportRunner` leaves both posts suppressed;
    - after the operator removes both post files (confirmed gone), importing the archive restores the files (confirmed present) and a rebuild still suppresses them.
  - Full `php tests/run.php`: 1121 run, 9 failed; the 9 are the long-standing failures present before this branch (`testApplicationRendersCoreRoutes`, `testFeatureFlagsPageShowsLockedBadgeWithReasonForNonMutableFlags`, two `OfflineSnapshotBootstrapTest`, two `OfflineSnapshotPublishCommandTest`, `testQdbWelcomeDisplaysThreeNewestNewsItemsAndLinksToAllNews`, `testTaskQueueProcessesQueuedAgentReplyOnce`, `testQdbPermalinkDisablesBothVoteCaptionsAfterAnExistingUpvote`), unchanged by this work.
- Notes:
  - `is_file` results are cached within a PHP process; the test calls `clearstatcache(true)` before checking files an external `git rm` or import changed.

## Final verification
- Stages: all seven planned stages are implemented, each in its own stage-scoped commit with its summary update; the planning commit (Steps 1–3, debug log, folder organization, index entry) precedes them, so the commit count is 8 against a minimum of `1 + 7`.
- Completion Contract:
  - Entry: a signed-in author sees Delete on their own post (thread roots, replies, post page) and confirms.
  - Outcome: signature verified, tombstone committed, post suppressed across read surfaces, raw routes, artifacts and downloads of the read model, status shown.
  - Recovery: `partial` reports the failed step with a Retry that needs no new signature.
  - Release condition: re-import of the original archive leaves the post suppressed (tested).
- Not verified / known limits for review:
  - The in-browser key and signing path of `submitSignedRetraction` was not run in a real browser; reuses the approval flow's helpers. Manual check recommended.
  - Whole-checkout downloads (`/downloads/repository.tar.gz` / `.zip`), Git history, backups, and others' copies still hold the original; the UI copy and reference doc say so. Physical purge is the later slice.
  - Forte paned views show the placeholder but have no Delete control; QDB quote layout has the placeholder but no Delete control.
  - Scoring/analysis sweeps read bodies from the read model, so a retracted post has an empty body there; their behavior on empty bodies was not separately verified.
  - Deployment: no external system; the read-model schema version bump (15 → 16) triggers a one-time rebuild on deploy.
