> **Feature plan:** [Step 1](./history_purge_step1_solution_assessment.md) · [Step 2](./history_purge_step2_feature_description.md) · [Step 3](./history_purge_step3_development_plan.md) · Step 4

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/history_purge`; [ledger](../effort_estimation_trial.md#cycle-v3history_purge), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.80**

## Entry — 2026-10-30 UTC

Step 3 explicitly approved ("approved step 3"). Branch `feat/history-purge` from `main` at `4abda3db`; planning commit `7dfc7118`. Scope v1/A, baseline `2e17c971`, R1/P2, Claude single exposed rater. Step 4 entry whole-cycle **0.80 reaffirmed**. Original stage work S1–S7/v1: 0.15 / 0.10 / 0.20 / 0.10 / 0.10 / 0.10 / 0.15, non-additive; Stage 7 owns shared acceptance and closeout. All stages pending at entry. No measured labor or accepted outcome.

**Environment limit found at entry.** Step 2 and Step 3 name a QDB-derived repository copy for the sizing gate and acceptance. No such copy exists in this or any sibling checkout: the largest local repository has 814 posts and 122 commits, and the `local_repository_qdb` copies hold 21 posts. Stage 1 will measure on a synthetic repository shaped like QDB (tens of thousands of post files across thousands of commits) and record it as synthetic, not QDB-derived. This weakens the evidence for the known-environment condition and is reported in the handoff; it does not change scope.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

## Stage 1 - Sizing gate and purge planner

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.20**

- Changes:
  - `src/ForumRewrite/Retraction/HistoryPurgePlanner.php`: `plan()` builds the worklist from accepted `delete` receipts (post and signature candidates intersected with one history walk of `records/posts`), counts total and affected commits, lists legacy posts lacking `Created-At` and a `records/post-timestamps/` record, and reports blockers (`missing_git`, `dirty_tree`, `interrupted_import`, `legacy_timestamps_missing`, `no_targets`). `timeDryRun()` clones with `--no-hardlinks` and runs the real rewrite in the clone, returning clone and rewrite seconds, total, rewritten and remaining commit counts. The legacy check streams post bytes from one `git cat-file --batch` process instead of opening each file.
  - `src/ForumRewrite/Retraction/HistoryRewrite.php`: the shared rewrite primitive. `rewrite(root, paths)` streams `git fast-export --all --no-data --show-original-ids` through a path filter into `git fast-import --force --export-marks` on the same repository and returns the old-to-new commit map for every commit. Path validation is restricted to `records/`; a `.git/history-rewrite` marker refuses concurrent rewrites. `remainingCommits()` counts commits on any ref still touching the paths.
  - `tests/HistoryPurgePlannerTest.php`, `tests/HistoryRewriteTest.php` (registered in `tests/run.php`); `tests/Support/history_purge_sizing_fixture.php` builds a synthetic QDB-shaped repository with `git fast-import`.
- Sizing gate (synthetic repositories from the fixture; WSL2, 12 cores, Git 2.34.1; small ~150-byte posts; 10 deletions spread across history so nearly every commit is rewritten):
  - 100,000 posts, 5,010 commits, 60 MB `.git`. `filter-branch --index-filter`: 2,215 of 5,010 commits rewritten after 17.5 minutes, about 2 commits per second and slowing as the index grew; stopped, projected over 40 minutes. fast-export engine: `plan()` 8.1 s, clone 23.6 s, rewrite 14.1 s, reflog expiry plus `gc --prune=now` 4.5 s; 0 commits touching targets afterward.
  - 100,000 posts, 100,010 commits (one commit per post, the organic shape since each signed post is one commit), 115 MB `.git`. fast-export engine: `plan()` 32.0 s, clone 20.5 s, rewrite 19.0 s, reflog expiry plus gc 15.4 s; 0 commits touching targets afterward. `filter-branch` not attempted; projected hours.
  - With blob data piped instead of `--no-data`, the same rewrites took 31 s and 72 s; `--no-data` references existing blobs by id and makes rewrite cost independent of post size.
  - Environment limit stands: no QDB-derived copy exists locally, so these are synthetic figures, not the Step 2 known-environment replay. Stage 7 still owes a run on real receipts.
- Engine decision: **fast-export stream rewrite**, built in Git, in place. The user confirmed the switch on 2026-10-31 ("switch to fast-export"). This is a third engine, not either of the two the Step 3 plan named; the Option A contract (command surface, `Key: value` commit map, mandatory rebuild, verification, runbook) is unchanged, and the horizon-squash fallback is not needed. The map comes from fast-export original ids joined with fast-import marks, which removes the Stage 3 "map capture" risk. Commit count, order, messages, authors and dates are preserved; a commit that only touched a purged path becomes an empty commit so the map stays one to one; the HEAD tree id is unchanged when the targets were already deleted at HEAD. Signed tags are stripped and commit signatures are dropped by fast-export; the application does not sign commits. Old objects remain in the pack until Stage 3 expires reflogs and prunes.
- Verification:
  - `php tests/run.php HistoryRewriteTest HistoryPurgePlannerTest`: 5 run, 5 passed. Covers worklist and blockers, no-receipt repository reporting `no_targets`, dry run leaving the source untouched, every old commit mapped to a distinct new commit on live refs, purged paths absent from every ref, surviving blobs and receipt bytes identical, dates and messages preserved, empty commit retained, path and concurrency refusals.
  - Dry runs on both synthetic repositories above: `remaining_commits` 0 and `rev-list --all -- <target>` empty after gc; source repositories unchanged.
  - Full suite `php tests/run.php`: 4 failures, none in this stage's files. `LocalAppSmokeTest` private lobby and forte session tests and the repository archive filename test fail identically on a clean HEAD worktree (inherited). `WriteApiSmokeTest` failed on a different gpg detach-sign command in each of two runs, which is environment flakiness, not a code path this stage touches. The first full run also saw `ReadModelBuilderTimingTest` and `PlatformDocsStaticTest` failures that the runner records as long-standing.
  - Not applicable: UI, themes, roles, migration and deployment; nothing user-facing changed.
- Notes:
  - `plan()` at 100k commits spends most of its 32 s in the history walk; acceptable for an operator preview, revisit only if Stage 6 shows it matters.
  - Stage 3 material revision (new understanding, not changed scope): original 0.20 (0.15–0.25) revised to **0.15** (0.12–0.18) because the rewrite primitive and map capture now exist; Stage 3 keeps lock, ref snapshot, restore-on-failure, reflog expiry, prune and verification. Whole-cycle **0.80 reaffirmed** independently, not by summing stage deltas.
- Estimate details (extended): original scope S1/v1 planner plus timed dry run, 0.15 (0.10–0.20). Retrospective 0.20: the gate needed a working engine to time, so the rewrite primitive and commit-map capture moved forward from Stage 3; two engines were measured instead of one. No measured labor claimed.

*— Claude · 2026-10-31 UTC · Model: claude-fable-5-1*

## Stage 2 - Commit map record contract

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes:
  - `docs/specs/history_rewrite_record_v1.md`: the record. Path `records/history-rewrites/history-rewrite-<YYYYMMDD>T<HHMMSS>Z.txt`, headers `Format: history-rewrite-v1`, `Rewritten-At`, `Reason: purge`, `Receipt-Count`, `Part`, `Parts` in fixed order; body of `Old-Commit` / `New-Commit` blocks, strictly ascending by old identity, one to one, identity mappings omitted. Size bound: at most 100,000 blocks per file (about 10.7 MB, under the 16 MiB record limit); a larger run continues in `-<part>` files. Instance-local: `ArchiveRecordCatalog` does not list the directory, so exports and imports ignore it, as Step 2 requires.
  - `src/ForumRewrite/Retraction/CommitMap.php`: `encode(rewrittenAt, receiptCount, map)` returns path => bytes for every part and self-validates; `decode(bytes, path?)` is the strict validator and refuses a filename that disagrees with its headers; `write(root, files)` refuses to overwrite, which is the "second run in the same second" rule; `records(root)` lists parts in resolution order; `resolve(root, sha)` applies every record in order and returns the newest identity, or null when no record names it. Resolution scans record bytes for the block rather than parsing 100,000 mappings per lookup, with a format-line check and a shape check on the hit. `GenericTextRecordParser` and `RecordLimits` reused; no new parser.
  - `src/ForumRewrite/Canonical/SourcePathValidator.php`: the record path is a valid canonical record path, so `/source/blob` can show it once committed.
  - `src/ForumRewrite/Activity/ActivityService.php`: the file role label "history rewrite record" for the purge commit in Activity, beside the other record families.
  - `tests/CommitMapTest.php`, registered in `tests/run.php`.
- Verification:
  - `php tests/run.php CommitMapTest`: 4 run, 4 passed. Covers round trip with sorting and dropped identity mappings, an empty run, the 100,000-block split into parts with correct `Part`/`Parts`, sixteen malformed variants rejected (format, reason, timestamp, leading-zero count, part out of range, header order, extra header, identity mapping, duplicate old, duplicate new, out of order, uppercase hex, short hash, unknown block field, unterminated block, CRLF), path mismatch, many-to-one and negative count refused, chained resolution across two records to the latest identity, case-insensitive lookup, unmapped and malformed identities returning null, stray files in the directory ignored, overwrite refused.
  - `php tests/run.php CommitMapTest HistoryRewriteTest HistoryPurgePlannerTest ActivityItemLinkTest ActivityProjectionTest`: 12 run, 12 passed for the activity suites on the label change, 9 run, 9 passed for the purge suites. No test exercises `SourcePathValidator` directly; the new pattern is asserted in `CommitMapTest`.
  - Not applicable: UI, themes, roles, migration and deployment; no read-model schema change; no dependency change.
- Notes:
  - Routes consult the map only after Git reports a commit unknown (Stage 5), so a live identity is never redirected; the spec states this.
  - Stage 4 will call `encode` with the `HistoryRewrite::rewrite` map and `write` before committing; Stage 3 keeps the engine free of record knowledge.
- Estimate details (extended): original 0.10 (0.08–0.12). Retrospective 0.10: the strict codec was the driver as planned; the part split was the only addition beyond the Step 3 sketch, forced by the 100,010-commit shape Stage 1 measured against the 16 MiB record limit. Whole-cycle **0.80 reaffirmed**. No measured labor claimed.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*

## Stage 3 - Rewrite engine with restore and verification

**Original estimate: 0.20, revised at Stage 1 to 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes:
  - `src/ForumRewrite/Retraction/HistoryPurge.php`: `run(root, databasePath, plan)` takes the shared `forum-rewrite.lock` and calls `execute(root, plan)`. Phases: snapshot every ref, the HEAD symref and every blob ever stored at a target path into a `.git/history-purge/` journal (`Key: value` text); `HistoryRewrite::rewrite`; verify that no commit on any ref touches a target and that every pre-rewrite ref is mapped (annotated tags excepted); on any failure up to here, restore the snapshotted refs, clear the journal and throw "Purge aborted before any history was discarded; original refs restored". Then the point of no return: remove any target still in the working tree, reset the index, delete stale pseudo-refs (`ORIG_HEAD`, `FETCH_HEAD`, `MERGE_HEAD`, `CHERRY_PICK_HEAD`, `REBASE_HEAD`, `REVERT_HEAD`), delete `refs/original/*`, expire all reflogs including HEAD's, `gc --prune=now`; finally `lingering()` asks `cat-file --batch-check` whether any old target blob or any changed old commit id still exists anywhere in the object database. An empty answer clears the journal; otherwise the journal stays at phase `lingering`, the result reports `verified: false` with the ids, and `restore()` refuses because old history is gone and the backup is the rollback. `interrupted(root)` reads the journal; `restore(root)` puts the refs back when the journal shows a phase before prune. `execute()` restores an interrupted run itself before purging, so no new CLI verb is needed for recovery.
  - `src/ForumRewrite/Retraction/HistoryPurgePlanner.php`: new blocker `interrupted_purge` while a journal exists, so the next `preview` says so.
  - `tests/HistoryPurgeTest.php`, registered in `tests/run.php`.
  - `HighTrafficSafety::beforeWrite` is deferred to Stage 4, where the map record is the first write that changes served content; the rewrite itself leaves the HEAD tree unchanged.
- Verification:
  - `php tests/run.php HistoryPurgeTest HistoryPurgePlannerTest HistoryRewriteTest CommitMapTest`: 13 run, 13 passed. The fixture has a side branch, a lightweight tag, a stale `ORIG_HEAD`, an edited then deleted post with its signature, and a local receipt. Covered: full purge with `verified` true, three target blobs and four changed commits absent from the object database (`cat-file -e` fails for each), `fsck --unreachable --no-reflogs` empty, receipt and surviving post byte-identical, dates, messages, HEAD tree and symref unchanged, every ref on its mapped commit, clean working tree, journal gone, a second plan reporting `no_targets`; an injected failure after rewrite restoring refs exactly with the prose still in history and a following purge succeeding; a purged blob deliberately kept alive in a `.keep` pack failing verification with the id reported, the planner reporting `interrupted_purge`, `restore()` and a rerun both naming the backup; a simulated kill at phase `rewrite` with moved and stray branches restored by `restore()`, and a journal at `verify-refs` restored automatically by `execute()` before a successful purge.
  - Scale (synthetic fixture, same machine as Stage 1, in `php` with phase timings from the result): 100,000 posts / 5,010 commits: plan 4.9 s; execute 6.4 s total (snapshot 0.2, rewrite 3.8, verify refs 0.3, prune 2.0, verify objects 0.1); verified, 0 commits touching targets afterward. 100,000 posts / 100,010 commits: plan 12.8 s; execute 35.2 s total (snapshot 2.1, rewrite 17.8, verify refs 3.0, prune 10.2, verify objects 2.1); verified. Both faster than the Stage 1 figures for the same shapes, which were taken with a cold cache; still synthetic, not the Step 2 known-environment replay.
  - Not applicable: UI, themes, roles, migration and deployment. Full suite not rerun this stage; inherited failures as recorded at Stage 1.
- Notes:
  - The verifier proves absence by object id, which also catches an object kept alive by another pack, an alternate or an index entry; it cannot see other clones, which the runbook (Stage 6) names.
  - `run()` holds the write lock for the whole purge, including gc; at the 100k-commit shape that is about half a minute of paused writes plus Stage 4's rebuild.
- Estimate details (extended): revised original 0.15 (0.12–0.18). Retrospective 0.15: lock, snapshot, restore and two-phase verification as planned; the journal for interrupted runs and the pseudo-ref cleanup were the additions, offset by the engine already existing. Whole-cycle **0.80 reaffirmed**. No measured labor claimed.

*— Claude · 2026-11-01 UTC · Model: claude-fable-5-1*
