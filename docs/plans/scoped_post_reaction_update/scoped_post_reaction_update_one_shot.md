# Scoped post reaction update

Cycle: `v3/scoped_post_reaction_update`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled (`.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/scoped_post_reaction_update`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.
Extended estimates: enabled (no alternatives or stages, so only the cycle estimate applies)
Debug report: [log](scoped_post_reaction_update_debug_log.md)

**Cycle estimate: 0.25**

Status: planned; next permitted action: implement on this branch.

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
Planning commit: pending.

## Dated execution history

- 2026-11-15: plan written after the diagnosis above. No matching feedback item in `docs/feedback/items/`.

## Study history

- Intake: **0.25**, exposed rater Claude (claude-opus-5-5), recorded after the diagnosis and code inspection (no pristine intake).

*— Claude · 2026-11-15 UTC · Model: claude-opus-5-5*
