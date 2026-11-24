# Read model rebuild memory: hold the post corpus once

Cycle: `v3/read_model_post_memory`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: disabled
Effort study: `railspike-effort` (R1, P2), ledger [read_model_post_memory.md](../effort_ledger/read_model_post_memory.md)
Extended estimates: disabled
**Cycle estimate: unavailable (intake missing, not backfilled; see Study history)**
Status: implemented and verified; completion commit and PR pending

Original request (2026-11-24): "please help me fix this: PHP Fatal error: Allowed memory size of 134217728 bytes exhausted (tried to allocate 2097152 bytes) in /home/qdb/v3/src/ForumRewrite/ReadModel/ReadModelBuilder.php on line 288"

Authorization: the maintainer asked for the fix directly; this record treats that as bounded one-shot authority for the rebuild's memory use only. Excluded: raising `memory_limit` in code, schema changes, rewriting later phases to read posts back from SQLite, and production access (qdb is redeployed by the maintainer).

Outcome and work type: application fix. A full read-model rebuild of a qdb-sized corpus (tens of thousands of single-post threads) fits in PHP's default 128 MB, with the same read model as before.

Approach and risks:
- Cause, replayed locally: `indexPosts()` iterated `$parsedPosts` through the `progressItems()` generator and then set `sequence_number` on each element. The generator still held the original array, so every post was copied and the corpus lived in memory twice. Line 288 at `75c7210` is that assignment, and a synthetic 24,000-post repository failed there with the reported error and allocation size.
- Fix: move each post out of `$parsedPosts` before changing it; write each thread summary as soon as its thread ends (posts are already sorted by thread, so insert order is unchanged); keep in `$posts` only the 15 fields later phases read; stream the two `SELECT ... FROM posts/threads` lookup loops in `indexPostReactions()` and `indexThreadLabels()` instead of `fetchAll()`.
- Risk: a later phase reading a field that was dropped from `$posts`. Mitigation: every `$post[...]` read after `indexPosts()` in `ReadModelBuilder`, `InvitationApprovalReplay` and `KeyBootstrapReplay` was listed; the kept set covers all of them plus `thread_type` (named in the phase docblocks). A parity dump of `posts`, `threads`, `activity`, `post_reactions` and `thread_labels` before and after confirms identical output.

Reuse decisions: no new components. Reuses `progressItems()` (now over keys), the existing prepared inserts, and the subprocess memory-test pattern from `TagsIndexMemoryTest` with `RebuildHistoryFixture`.

Completion conditions: `./v3 rebuild` (and request/worker rebuilds, which share `ReadModelBuilder::rebuild()`) completes on a corpus that previously exhausted 128 MB; read-model output unchanged; regression test fails on the old code and passes on the new.

Concepts touched: read model (changed internally, no contract change); no inventory update proposed.

Verification and recovery plan: replay the reported failure with a synthetic qdb-shaped corpus under `memory_limit=128M`; smoke set plus read-model tests; parity dump. Production replay on qdb is the maintainer's: deploy, then `./v3 rebuild`. Until deployed, `php -d memory_limit=512M scripts/rebuild_read_model.php` from `~/v3` works around it.

Starting revisions and branch: `75c7210` (main), branch `fix/read-model-post-memory`.
Planning commit: pending at time of writing.

## Dated execution history

- 2026-11-24 — Correction to chronology: the investigation and the implementation edits were made in the working tree before this record existed, while diagnosing the error. This record and its planning commit were written afterwards and before the completion commit; the planning commit holds only this record and the index link.
- 2026-11-24 — Replayed: 20,000 posts peaked at 125.5 MB (allocator 144 MB); 24,000 posts failed at line 288 under 128 MB.
- 2026-11-24 — Move-out fix alone: 20,000 posts 100.5 MB; 24,000 then failed later (line 396, the `fetchAll` of threads), because thread summaries and the 35-field `$posts` stayed resident. Added streamed summaries, the 15-field projection and streamed lookups.
- 2026-11-24 — Pre-existing failure found while testing: `ReadModelViewerTagsRebuildTest::testStaleModelWithoutViewerTagsRebuildsOnStartupBeforeServing` fails on main in a multi-class run (passes alone). Cause: the runner's per-process task queue can hold a fresh worker heartbeat from an earlier test, so startup queues the rebuild and shows the update page. Fixed per project rule by giving the test a fixture-local queue, as `ReadModelRequestRebuildTest` already does; applied the same to `ReadModelHotIndexRebuildTest`, which has the identical pattern.
- 2026-11-24 — `ReadModelBuilderTimingTest` no longer expects a separate `writing_thread_summaries` timing, since summaries are now written inside `writing_post_records`.

## Study history

- Intake: missing (work began before the record); not backfilled.
- Closeout 2026-11-24: retrospective scope assessment **0.25**, single exposed Claude rater. Measured time unknown; maintainer judgment pending.

## Outcome

Rebuild peak memory, synthetic single-post-thread corpus (PHP 8.3, allocator peak in parentheses):

| Posts | Before | After |
| --- | --- | --- |
| 8,000 | 50.5 MB (58 MB) | 19.7 MB (32 MB) |
| 20,000 | 125.5 MB (144 MB) | 48.7 MB (76 MB) |
| 40,000 | 249.1 MB (284 MB) | 95.6 MB (146 MB) |

Under a 128 MB limit the ceiling moves from about 19,000 to about 35,000 posts. Limitation: memory is still linear in the corpus, because later phases (approval, attribution, profile counts, activity) take the whole `$posts` array. Next step if qdb grows past that: have those phases read posts back from SQLite in batches.

## Verification

- `./v3 test --smoke ReadModelBuilderTimingTest ReadModelProvenanceTest ReadModelPostReactionsTest ReadModelThreadLabelsTest ReadModelThreadSubjectsTest ReadModelPostRetractionsTest ReadModelMetadataTest ReadModelCandidateBuilderTest ReadModelSignalEligibilityTest ReadModelViewerTagsRebuildTest ReadModelHotIndexRebuildTest ReadModelRequestRebuildTest ActivityProjectionTest`: 522 run, 522 passed. Same batch on main before the test fix: 514/515, the ViewerTags failure above.
- New `ReadModelRebuildMemoryTest` (8,000 posts under `memory_limit=44M`): passes; fails with exit 255 (out of memory) on the old builder.
- Parity: posts, threads, activity, post_reactions and thread_labels from a fixture with multi-reply threads, an orphan reply, labels and reactions are identical before and after.
- Full suite not run (maintainer runs it). qdb production replay not done here: unverified until deployed.

## Final review

Diff checked: one builder change, three test adjustments, one new test with its support script, this record, the index line and the ledger file. No duplicated components; no schema or contract changes.

Commit/merge/publication status: completion commit and PR pending.

*Claude (Opus 5.5), 2026-11-24 UTC*
