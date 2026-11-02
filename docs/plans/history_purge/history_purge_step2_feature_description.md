> **Feature plan:** [Step 1](./history_purge_step1_solution_assessment.md) · Step 2 · [Step 3](./history_purge_step3_development_plan.md) · [Step 4](./history_purge_step4_implementation_summary.md)

Debug mode: enabled.
Effort study: `railspike-effort` / `v3/history_purge`; [ledger](../effort_estimation_trial.md#cycle-v3history_purge), standing P2 enrollment.
Extended estimates: enabled.

**Cycle estimate: 0.80**

## Problem

Prose deleted by an accepted retraction or operator removal remains recoverable from the repository's Git history on the server. An operator needs a deliberate, bounded way to purge it that keeps the retained receipts sufficient and the site's commit-addressed links and indexes working.

## User stories

- As an operator, I want to run one command that removes retracted prose from history so that a deleted post is gone from the server, not only from the current tree.
- As an operator, I want to preview what a purge will rewrite and roughly how long it will take so that I can schedule the pause and keep a pre-purge backup.
- As a member or reader, I want old links to source files and commits to keep resolving after a purge so that nothing I bookmarked breaks.
- As a steward of a mirror or a later importer, I want the retained receipts to describe each removal on their own so that I can tell moderation from rewriting without the history.

## Core requirements

- **Worklist from receipts only.** The purge targets exactly the post and signature paths named by accepted `delete` receipts ([removal acceptance v1](../../specs/removal_acceptance_record_v1.md)). It never purges a path without a receipt and never changes receipt, request or signature bytes.
- **Built-in Git, operator-run, batched.** One `./v3` command under the existing repository write lock, with `preview` and `run`. No per-retraction rewriting at acceptance. No new dependency; Git 2.34 on the deployment host already provides what is needed.
- **Commit map record.** Every rewritten commit's old and new identity is recorded in a `Key: value` record in the repository and committed with the purge. Source blob and commit routes resolve an old identity through the map. Activity, evidence and commit indexes are rebuilt afterward, never patched.
- **Verified gone.** After the rewrite, `run` proves that no purged path is reachable from any ref, reflog or dangling object before it reports success. Failure leaves the pre-purge refs intact and says what to do.
- **Honest limits.** Output and the runbook state what the purge does not reach: operator backups, other clones, mirrors, downloads already made, provider copies, and quotations in surviving posts.

## Concepts touched

- History purge: created. Proposed entry: purpose, operational principle (receipts in, map out, rebuild), sources.
- Post retraction and operator removal: used unchanged. Proposed entry; none exists despite three completed cycles.
- Source history links (`/source/blob/{commit}`, `/source/commits/{commit}`): changed to consult the map. Proposed entry.
- [Content snapshot export](../../concepts/content_snapshot_export.md): unchanged; exports were already history-free.
- [Instance content import](../../concepts/instance_content_import.md): unchanged; receipts already block resurrection of accepted deletions.

## Delivery scope

**Work type:** application change. CLI command, map record contract, source-route resolution, verification, operator runbook, and the concept entries above. Excluded: scheduling, a web UI, purging anything a receipt does not name, changes to receipt formats, backups or mirrors held by others, and any production run.

## Completion boundary

Normal entry is an operator with an accepted deletion running `./v3 purge-history preview` then `run` on the deployed repository with ordinary writes paused by the lock. End-to-end outcome: the purged prose is absent from every Git object, a committed map record exists, the read model and static artifacts are rebuilt, old `/source/*` links for rewritten commits resolve, and surviving records are byte-identical. Recovery: a failed or interrupted run restores the original refs and reports; a completed run cannot be undone except from the operator's pre-purge backup, which the runbook requires. Release condition: the sizing gate below passed on a QDB-sized copy, the flows in success criteria pass locally, and the runbook is linked from the retraction references.

## Known user or environment

The retraction handoff names qdb.us as the production instance whose history matters. Ordinary entry is the operator's shell on that host with the deployment's repository and database paths. The failure to handle: a rewrite that cannot finish within the pause the operator can afford. Unaided next action: `preview` reports commit count, affected paths and a measured dry-run time on a copy so the operator can decide before anything changes. Verification: `run` output and `git log --all -- <path>` for a purged path returning nothing. Evidence is a recorded local run on a QDB-derived repository; a live qdb.us run is not part of this cycle.

## Risks

- **Rewrite time on a large repository.** `filter-branch` touches every commit after the first affected one. Earliest validation: Stage 1 times a dry run on a QDB-derived copy before any other stage. Mitigation: if it exceeds an operator-acceptable pause, switch the engine to the Step 1 horizon squash behind the same command and map contract; the decision point is recorded in Step 3.
- **Hash breakage beyond the map.** Image backup manifests, interrupted-import journals and bookmarks hold old commit identities. Impact: restores or resumes that reference a vanished commit. Validation: inventory every consumer of a commit identity in Step 3 and classify each as map-resolved, rebuilt, or documented-as-broken. Mitigation: `preview` refuses to run while an interrupted import journal exists; the runbook orders backup verification before purge.
- **Incomplete removal.** Objects survive in reflogs, packs, `refs/original`, or another clone. Impact: a purge that reports success while prose remains. Validation: the verification step is written before the rewrite step and tested against a deliberately leaked object. Mitigation: refuse success until reflog expiry and prune have run and the reachability check is empty; name other clones as outside scope in output.
- **Legacy dates and provenance loss.** Posts without `Created-At` derive dates from history; rewriting preserves author dates but a future squash would not. Validation: confirm `records/post-timestamps/` resolution covers every legacy post on the QDB copy before Stage 1 ends. Mitigation: `preview` reports legacy posts lacking a timestamp record and refuses to run until they are baked.

## Shared component inventory

- `./v3` dispatcher and the CLI error-handling contract: extend with one command; reuse `ExecutionLock` on `forum-rewrite.lock` as `delete-record` does.
- `AcceptedRetraction` and `AcceptedRemoval` readers: reuse unchanged as the worklist source.
- `SourceFileController` blob and commit routes, and `Application::sourceCommitDetails`: extend with map lookup; same validators, same retracted-path notice.
- `ReadModelBuilder` commit indexing, source commit preload and legacy timestamp preload: reuse unchanged; a mandatory rebuild follows the purge.
- `SqliteActivityCommitManifestCache`: invalidated by rebuild; no change.
- `ImageBackup` manifests and `.git` pointer restores: documented consumer, not changed; the runbook tells operators to take a fresh image backup after a purge.
- `ContentImportRunner` journals under `.git/instance-import/`: guard only, no change.
- New: commit map record contract under `records/`, `Key: value`, with its reader; the only new component, because no existing record expresses commit identity.

## Simple user flow

1. Operator takes and verifies a full repository backup, including `.git`.
2. Operator runs `./v3 purge-history preview`: sees receipts found, paths to purge, commits to rewrite, legacy posts needing timestamps, blockers, and a timed dry run.
3. Operator runs `./v3 purge-history run`: writes pause, history is rewritten, old refs and reflogs are expired and pruned, the map record is committed, reachability is verified, the read model rebuilds, static artifacts rebuild.
4. Operator opens a purged post's old permalink and sees the existing deletion notice; opens an old `/source/blob/<old-commit>/...` link for a surviving file and sees it resolve.
5. Operator takes a fresh image backup if images are enabled.

## Success criteria

- For every accepted `delete` receipt on the test repository, `git log --all` and a dangling-object scan for the purged paths return nothing, and the receipt, request and signature files are byte-identical before and after.
- Every surviving canonical record is byte-identical; a full rebuild produces the same posts, labels, reactions and approvals as before the purge.
- Old `/source/blob/<old-commit>/<surviving-path>` and `/source/commits/<old-commit>` links resolve for a sample of rewritten commits; a purged path returns the deletion notice, not prose.
- `preview` on a QDB-derived copy reports a dry-run time; the recorded figure, not an estimate, is in the Step 4 summary with the engine decision it drove.
- An interrupted `run` leaves the original refs intact and the next `preview` says so.
- The runbook states the backup prerequisite, the pause, and the copies the purge cannot reach, and is linked from the post retraction reference.

## Enhancement Requests

None pending.

## Approval and study checkpoint

2026-10-30 UTC; Step 1 Option A explicitly approved ("history_purge approved step 1"). Scope v1/A, baseline `2e17c971308a710a540c905e738048c4f51e53ca`, R1/P2, Claude single exposed rater. Step 2 handoff whole-cycle **0.80 reaffirmed**; the additional consumer inventory (image manifests, import journals, legacy timestamps) is detail inside the Step 1 scope, not new scope. All three frozen R1 hashes verified at Step 1 and unchanged. No implementation, measured work or accepted outcome. Await Approved Step 2 before Step 3.

Debug: [Step 2 report](history_purge_debug_log.md#2026-10-30-utc--step-2).

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*

Approval update — 2026-10-30 UTC: user wrote "approved step 2"; requirements unchanged, Step 3 drafted.

*— Claude · 2026-10-30 UTC · Model: claude-fable-5-1*
