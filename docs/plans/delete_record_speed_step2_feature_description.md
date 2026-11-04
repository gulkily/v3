> **Feature plan:** [Step 1](./delete_record_speed_step1_solution_assessment.md) · [Step 2](./delete_record_speed_step2_feature_description.md) · [Step 3](./delete_record_speed_step3_development_plan.md) · [Step 4](./delete_record_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/delete_record_speed`; [host ledger](./effort_estimation_trial.md#cycle-v3delete_record_speed). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved on 2026-11-04; Step 3 drafted; implementation unapproved.

**Cycle estimate: 0.60** (revised; Step 1 0.55)

## Problem

`./v3 delete-record <post-id>` scans every label file and rebuilds the whole site to remove one post, so it takes minutes on qdb.us and prints nothing until it ends; `--inspect` takes 17 seconds for the same scan.

## User Stories

- As the operator, I want to delete a post and see it gone from the site in under a second so that cleaning up spam or a bad post is instant.
- As the operator, I want each step printed as it happens so that I can tell a working command from a stuck one.
- As the operator, I want `--inspect` to answer in under a second so that I can check before deleting without waiting.
- As the operator, I want an interrupted or failed delete to tell me how to finish it so that the site never stays half-updated.

## Core Requirements

- **Proportional work.** Inspect and delete read and write only the post, its thread's label files, that thread's read-model rows and pages, and the shared listing pages. Nothing scans all records or rebuilds the read model on success.
- **Under 1s.** On the qdb.us host at its current size, both commands finish in under 1s of wall time, including PHP startup, measured with `time`. If startup plus one bare Git commit alone exceeds the budget, the measurement is reported and the target is restated against that floor before Step 3.
- **Live status.** Each step prints one line to stderr as it starts or finishes, with elapsed time (`reading post`, `finding labels`, `committing`, `updating index`, `clearing pages`), ending `Deleted <id> in 0.21s`. A step waiting on a lock says so. `--json` output on stdout is unchanged.
- **Same result as a rebuild.** After a delete, a full `./v3 rebuild` produces the same read model. The delete receipt keeps its format, so a later `./v3 purge-history` still removes the post from history.
- **Recovery.** If any step after the commit fails, the read model is marked stale, the message names the command to rerun, and rerunning finishes the job. The full rebuild remains as the fallback. `--repair-labels` keeps its current thorough path.

## Delivery Scope and Completion Boundary

Work type: application change, plus the read-model schema spec, touched concept and reference docs, this cycle's records and study-ledger bookkeeping. No new dependencies. Out of scope: history purge (separate later run), the older non-post `delete-record <path>` mode, the web retraction flow, and other commands that rebuild after small changes.

Completion boundary: on the qdb.us host, the operator runs `time ./v3 delete-record <id> --inspect` and then `time ./v3 delete-record <id>` for a real spam thread, sees each step print, both report under 1s, and the thread is gone from its page, the front page, Recent and its tag pages. Release condition: verified on a copy of the qdb repository locally, then once on the qdb.us host.

Known environment: operator `qdb@pdx1-shared-a2-13`, SSH into `~/v3` on a shared host with slow small-file I/O. If a step stalls, the status line shows which one; if the command fails or is interrupted, the operator reruns the same command and sees it finish, then confirms with `--inspect` and by loading the thread URL. Evidence is the pasted `time` output and status lines from that host; local timings do not satisfy the target.

## Shared Component Inventory

- **`delete-record` command** (`scripts/delete_record.php`): extend in place; no new command.
- **Label ownership** (`LabelRemoval`, `SourceLabelRemoval`, removal journal): reuse ownership rules and receipt format; replace the directory scan with an indexed lookup.
- **Incremental read-model updater** (`IncrementalReadModelUpdater`, which already handles retracted threads): extend with a post-removal update rather than a separate writer.
- **Targeted page invalidation** (`StaticArtifactInvalidator::invalidateBoardThread` / `invalidateReply`): reuse; add tag pages if they are not covered.
- **Rebuild progress reporter** (`ReadModelBuilder` progress closure, used by `purge-history`): reuse its output style for status lines and for the fallback rebuild.
- **Stale marker and locks** (`ReadModelStaleMarker`, `ExecutionLock`, `PublicationLock`): reuse unchanged.

## Risks

- **Git commit cost in a large repository.** A normal commit may stat every tracked file. Validate first by timing a bare commit on the qdb host; mitigate with a commit that writes only the changed paths.
- **Startup floor.** PHP bootstrap on the shared host may take a large share of 1s. Measure in the first stage; report before tuning further.
- **Divergence from a rebuild.** A direct removal can miss a count, label or reply placeholder. Mitigate with a test comparing the read model after delete against a fresh rebuild.
- **New index on existing installs.** The label lookup needs a schema change; an install still on the old schema must fall back to the scan or rebuild once, not fail.

## Simple User Flow

1. The operator spots a spam thread and copies its ID.
2. `./v3 delete-record <id> --inspect` prints the post and its labels in under a second.
3. `./v3 delete-record <id>` prints each step and finishes with `Deleted <id> in 0.xs`.
4. The thread page and its front-page entry are gone on reload.
5. Later, the operator runs `./v3 purge-history` to remove it from history.

## Success Criteria

- On the qdb.us host, `--inspect` and delete each report under 1s with `time`.
- Every step prints before the next starts.
- After a delete, a full rebuild leaves the read model unchanged.
- Killing the command after the commit, then rerunning it, completes the delete.
- `purge-history preview` lists the deleted post.

## Concepts Touched

[Read model rebuild](../concepts/read_model_rebuild.md) used unchanged as fallback; [static publication](../concepts/static_publication.md) used, possibly changed for tag pages; [post retraction](../concepts/post_retraction.md) reviewed, unchanged; proposed: *incremental source removal* and *operator command progress* (inventory entries at implementation).

## Study Checkpoint

2026-11-04T17:45:00Z; scope v2/D, baseline `ea306fc03b3152a2e66b3d2a75b13d7d1c4064bc`, R1/P2; frozen hashes verified earlier this session, unchanged. User explicitly approved Step 1 (Option D). Whole-cycle revised **0.55 → 0.60**: the schema change needs the prior-version regression test `CONTRIBUTING.md` requires and an old-schema fallback, and host timing is a required first stage. Claude, single exposed rater. No implementation, measured labor or accepted outcome. Await Approved Step 2.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
