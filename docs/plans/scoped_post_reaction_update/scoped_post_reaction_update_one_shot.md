# Scoped post reaction update

Cycle: `v3/scoped_post_reaction_update`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/scoped_post_reaction_update`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](scoped_post_reaction_update_debug_log.md)

**Cycle estimate: 0.25**

Status: technically complete and committed on the branch; next permitted action: push and open a PR when the maintainer asks.

## Original request

"I just saw a weird bug. I had an issue open that had both resolve and close buttons, and I pushed close and then I realized that I wanted to push resolve, and I pushed resolve right away, and just for a moment I saw a red error message on the page that said server is busy, and I'm not even sure if it got applied or not, but either way I think it's not great that that happened, and we should figure out what's going on here and what to do about it, please." Follow-up: "This was on Zen Memes, so you can see like what happened if you look at the logs."

## Diagnosis (before this cycle)

- Public activity for [Small bug display on iPhone](https://zenmemes.com/threads/thread-20261115190951-97b38943): Close written 19:17:31 UTC, Resolve written 19:17:53 UTC. The Resolve was the Outbox retry, about 15 seconds after a 503.
- Every write waits at most 5 seconds for `forum-rewrite.lock` (`ExecutionLock`). Resolve timed out waiting while Close held the lock.
- Close held the lock that long because every post reaction write scans every post reaction file, twice, inside the lock: `IncrementalReadModelUpdater::loadPostReactionRecords` parses all files to keep one post's records, and `ReactionSignatureIndex::refresh` rebuilds the signature rows of the whole family. A signature that fails verification is never cached, so it runs gpg on every write.
- Server logs were not available to this session. The maintainer can confirm by searching the PHP error log near 19:17 UTC for "Timed out waiting for execution lock" and "fell back to a full rebuild".

## Authorization

2026-11-15, maintainer: split the server fix from the page fix, one-shot each, server fix first. Reply to "Should I start Fix 1?": "Yes, don't change the schema." Authorizes this branch, the plan and completion commits, and the bounded change below. Does not authorize pushing, a PR, merging or deploying unless asked; no production access.

## Outcome and work type

Application change. A post reaction write (like, flag, Resolved, Closed, Claimed, Open, other post tags) updates the read model by reading only that post's reaction files and the signature row of the file it wrote. Its time no longer grows with the number of reactions on the site.

Excluded: the read-model schema (no new table, column or index); thread label writes; the full rebuild; the page fix (`thread_reactions.js`), which is a separate one-shot; the 5-second lock wait.

## Approach and risks

- `applyPostReactionWrite` gains an optional list of the reaction files the write just added. With the list, it finds the post's other reaction files from its existing `activity` rows (`kind = post_reaction_add`, indexed by `post_id`, each with `source_path`), adds the new files, and parses only those. It refreshes signature rows only for the new files. Without the list, it keeps today's full scan, so other callers and tests behave as before.
- `LocalWriteService::applyPostTag` passes the record path it wrote.
- Risk: `activity` misses a reaction file for the post, so the derived state drops it. Validation: every incremental post reaction write and every full rebuild write one `post_reaction_add` row per parsed record of a known post; no code deletes those rows except the same per-post refresh. Mitigation: a test that compares the scoped update against the full scan on the same repository.
- Risk: a listed file was removed from disk (retraction or purge). Mitigation: skip missing files, as the full scan would never see them.
- Risk: other writers that add post reaction files without this path (imports, rebuilds) leave `activity` stale. They already go through a full rebuild, which rewrites every row; unchanged.

## Reuse decisions

- Post lookup: existing `activity` rows and `activity_post_id_idx` (`ReadModelSchema`); no new structure, per the maintainer's instruction.
- Record parsing: existing `CanonicalRecordRepository::loadPostReaction` and the existing sort in `loadPostReactionRecords`.
- Signature rows: extend `ReactionSignatureIndex` with a per-path refresh that reuses `ReactionSignature::status`, so the status logic stays in one place.
- Precedent: thread labels already avoid a full scan through `thread_label_files`.

## Completion conditions

- Normal entry: Like, Close or Resolve on a post or thread through `/api/apply_post_tag`.
- End-to-end: the read model after a scoped write equals the read model after the full-scan path for the same records, including resolution state, scores, eligibility, activity and signature rows.
- Recovery: unchanged; a failure still falls back to a full rebuild.
- Release: maintainer merges and deploys; the production check is two quick state clicks without a busy message.

## Concepts touched

[Reaction record](../../concepts/reaction_record.md) and [read model rebuild](../../concepts/read_model_rebuild.md): used unchanged. Inventory update only if an entry describes the per-write scan.

## Verification and recovery plan

New test comparing scoped and full-scan updates on one fixture with several posts, signed and unsigned reactions, and a missing file. Smoke set plus the tests that call `applyPostReactionWrite` (`ActivityProjectionTest`, `ReadModelSignalEligibilityTest`, `ReactionRecordFormatParityTest`) and the tag write tests. A timing check on a fixture with many reactions, before and after. Full suite not run, per `AGENTS.md`. Production replay (two quick clicks) is pending until deploy.

Starting revisions and branch: `fix/scoped-post-reaction-update` from `8314fae95ea2b33e16a2e6a1d6e2ca512ef52c4d`.
Planning commit: `e09103d3` (plan and index link only; confirmed with `git log`).

## Dated execution history

- 2026-11-15: plan written after the diagnosis above. No matching feedback item in `docs/feedback/items/`.
- 2026-11-15: implemented as planned: `ReactionSignatureIndex::refreshPaths`, `applyPostReactionWrite(..., $writtenPaths)`, and `LocalWriteService::applyPostTag` passing the file it wrote.
- 2026-11-15, correction within the same outcome: the timing check showed a third full scan the plan missed. `refreshViewerTagsForTarget` globbed every post reaction file when given no paths. It already accepted a path list (used for thread labels), so the scoped write now passes the post's files.
- 2026-11-15, correction: `WriteApiSmokeTest::testLlmExchangeLinksAppearOnlyOnTheirPostDetailPages` failed on the base revision (`8314fae9`, checked with the change stashed). Commit `0c96cd53` turned the post metadata into a table, so the text "LLM exchanges:" became a `<th>`. The test, and a no-longer-meaningful negative check in `testRetractionMakesDirectAndContextOnlyLlmExchangeDetailsUnavailable`, now look for `>LLM exchanges</th>`. Fixed per the maintainer's rule that tests failing on the base are fixed in the same work.
- 2026-11-15: left on the full scan, out of scope: reaction retraction (`refreshAfterReactionRetraction`), thread label signature refresh, and `refreshApprovalSensitivePostReactions`. `mark_activity_attribution` is per thread, not per site, so it was left alone.

## Study history

- Intake: **0.25**, exposed rater Claude (claude-opus-5-5), recorded after the diagnosis and code inspection (no pristine intake).
- Execution entry: reaffirmed 0.25.
- Closeout retrospective: **0.25**. The viewer-tag scan and the stale test were corrections, not new scope. Not hours or measured labor; maintainer acceptance pending.

## Outcome

Delivered as bounded. A post reaction write through `/api/apply_post_tag` reads only that post's reaction files (from its `activity` rows plus the new file) and refreshes only the new file's signature row. No schema change. Other callers of `applyPostReactionWrite` keep the full scan. The 5-second lock wait and the page behaviour are unchanged; the page fix is the next one-shot.

Limitations: reaction retraction, thread label writes and approval changes still scan their whole family; time per write still grows with the size of the post's own thread (per-thread attribution marking).

## Verification

- New `ScopedPostReactionUpdateTest`: Close then Resolve through the scoped and the full-scan paths on copies of one read model give identical `posts`, `activity`, `viewer_tags` and `reaction_signatures` rows, including a record whose signature does not verify; a listed file that is gone is skipped. Passed.
- Timing (script outside the repo, unsigned records, this workspace): one write with 5 reactions on the target post and the rest on other posts. 2,000 reactions: full scan 183 ms, scoped 41 ms. 8,000 reactions: full scan 626 ms, scoped 57 ms. Production adds gpg signature checks to the full scan, so the saving there should be larger; not measured.
- `./v3 test --smoke ScopedPostReactionUpdateTest ActivityProjectionTest ReadModelSignalEligibilityTest ReactionRecordFormatParityTest ReactionOutboxTest TagUnlocksTest ReadModelPostReactionsTest ReactionRetractionTest ViewerTagLookupParityTest ReadModelViewerTagsProjectionTest BrowserSigningNormalizationTest WriteApiSmokeTest`: 808 run, 808 passed.
- Full suite not run, per `AGENTS.md`.
- Pending: production replay (Close then Resolve quickly, no busy message), and the maintainer's log check for the original incident.

## Final review

Diff checked: three source files, one new test, the test runner list, a stale test fix, this record, its debug log, the plans index and the study ledger. No duplicated status logic: signature status stays in `ReactionSignature::status` through one `row()` helper; record sorting is one helper. Concept entries checked; none describe the per-write scan, so no inventory change.

## Commit/merge/publication status

Completion commit: `7c5c2608` (confirmed with `git log`). Not pushed; no PR; not merged or deployed.

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
