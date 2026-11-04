> **Feature plan:** [Step 1](./delete_record_speed_step1_solution_assessment.md) · [Step 2](./delete_record_speed_step2_feature_description.md) · [Step 3](./delete_record_speed_step3_development_plan.md) · [Step 4](./delete_record_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/delete_record_speed`; [host ledger](./effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.75

## Original Query

qdb@pdx1-shared-a2-13:~/v3$ time ./v3 delete-record thread-20261103074506-331f77ad --inspect

```
Source label inspection: thread-20261103074506-331f77ad
source_path: records/posts/2026/11/03/thread-20261103074506-331f77ad.txt
source_hash: 416d7828dfaa62f4bce968122128d4683146360529ee835ccaf02e340de87869
proven: {"records\/thread-labels\/thread-label-20261103074507-4408bc56.txt":"b13b47aa0c41486dd45ae9d03d6538d1b336c27b8159ebd6df77bb8f3317fcbe"}
unresolved: []
review_candidates: []

real    0m17.235s
user    0m0.900s
sys     0m10.442s
```

qdb@pdx1-shared-a2-13:~/v3$ time ./v3 delete-record thread-20261103074506-331f77ad

Do you know why the inspect took 17 seconds and the actual delete is still going after 2 minutes?

OK, both of these commands need to show status updates about what they're doing as they're doing it, and they need to be way faster than they are now. Please write Step 1.

They basically need to take <1s.

## Understood Intent

Both `./v3 delete-record <post-id> --inspect` and `./v3 delete-record <post-id>` should finish in under one second on the qdb.us shared host at its current size (about 311k quotes), and print what they are doing while they run. The delete must still leave the site showing the post as gone when the command returns.

## Problem Statement

Deleting one post reads every thread-label file in the repository several times and then rebuilds the whole read model and invalidates every generated page, all silently, so a one-post change costs minutes on a large site.

## Where the time goes (from source, not yet measured on the host)

- **Inspect:** `LabelRemoval::inspect` opens and parses every file in `records/thread-labels/` to find the few labels on one thread. High `sys` time fits per-file I/O on a shared filesystem.
- **Delete:** repeats that scan in prepare and again after acceptance, waits for two locks, deletes every generated static page (`invalidateAllGeneratedPresentation`), then runs a full `ReadModelBuilder::rebuild()` over every record.
- Normal web writes already avoid this through `IncrementalReadModelUpdater`, and `ReadModelBuilder` already accepts a progress reporter that `purge-history` uses.

## Concepts touched

- Option A: [read model rebuild](../concepts/read_model_rebuild.md) (used unchanged as the fallback), [static publication](../concepts/static_publication.md) (changed: targeted invalidation), [post retraction](../concepts/post_retraction.md) (used unchanged); proposed: *incremental source removal*, *operator command progress*.
- Option B: [read model rebuild](../concepts/read_model_rebuild.md) (changed: deferred via task queue); proposed: *operator command progress*.
- Option C: [read model rebuild](../concepts/read_model_rebuild.md) (changed: faster full rebuild).

## Solution Options

### Option A: Targeted removal with live status lines

**Estimate: 0.75**

Look up a thread's labels through the read model (or a per-thread index) instead of scanning every label file, and scan once per run. After the removal commit, apply it with a new incremental updater method that removes the post and its labels and refreshes only that thread's summaries, then invalidate only the pages that showed it (thread, indexes, its tags, the qdb front page). Each phase prints one line to stderr with elapsed time. The full rebuild remains available as `./v3 rebuild` and as automatic recovery when the targeted update fails, with the existing stale marker and resume journal.

- Pros: meets the 1s target in principle, since web posting already does incremental work of this size; the site is correct when the command returns; reuses the incremental updater, journal and stale-marker recovery.
- Cons: the incremental removal must match a full rebuild exactly, including reply placeholders and label effects, which needs an equivalence check; the set of pages to invalidate must be complete.
- Driver: rebuild-equivalent incremental removal. Uncertainty: PHP and `./v3` startup cost on the shared host may use much of the 1s budget before any work starts.

### Option B: Commit fast, rebuild in the background

**Estimate: 0.40**

Keep the current scan and full rebuild, but after committing, mark the read model stale and queue a rebuild through the existing task queue. The command returns once the commit lands, and progress is visible through the queued job's status.

- Pros: small change; uses the existing `ReadModelRebuildTaskHandler`.
- Cons: the deleted post stays on the site for minutes after the command "finishes"; inspect stays slow; the whole site rebuild still runs for one post.
- Driver: task-queue handoff and status display.

### Option C: Make the full rebuild faster

**Estimate: 0.90**

Profile and speed up `ReadModelBuilder::rebuild()` (batched reads, fewer statements, a cached parse of unchanged records) and wire its progress reporter into `delete-record`.

- Pros: every rebuild caller benefits.
- Cons: rereading about 311k records cannot plausibly finish in 1s on a shared host; the inspect scan is untouched.
- Driver: rebuild profiling. Uncertainty: large; the target is likely unreachable this way.

## Recommendation

**Option A.** It is the only option that fits the 1s target with the site already correct when the command returns. The progress lines are useful with any option and belong in the same cycle.

Step 2 should start with a per-phase timing of both commands on the qdb host, so the budget is set from measurements, and should settle: the exact status line format, whether the 1s target covers PHP startup, which pages to invalidate, and whether the older non-post `delete-record` path (which also does a full rebuild) is in scope.

**Vertical-slice viability:** on a copy of the qdb repository, deleting one tagged post takes under 1s, prints each phase as it runs, and leaves a read model identical to one produced by a full rebuild after the same deletion.

## Enhancement Requests

- Other commands that call a full rebuild for small changes (`AgentIdentityService`, approval receipt maintenance) may benefit from the same approach. Disposition: noted; not in this scope.

## Study Snapshots

2026-11-04T17:30:18Z; scope v1; starting revision `ea306fc03b3152a2e66b3d2a75b13d7d1c4064bc`; reference R1 (all three frozen hashes verified). Claude, single exposed rater; the delete path and read-model code were read before rating, so no pristine intake is claimed.

- Intake after inspection: **0.75** projected total for Option A, including the timing pass, progress lines and equivalence check.
- Step 1 handoff: **0.75**, Option A. Alternatives B 0.40 and C 0.90, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

Status: Step 1 proposed. Committed and pushed before approval at the session stop hook's request; this is not approval.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
