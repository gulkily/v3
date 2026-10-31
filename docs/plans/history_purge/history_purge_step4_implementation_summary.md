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
