> **Feature plan:** [Step 1](./delete_record_speed_step1_solution_assessment.md) · [Step 2](./delete_record_speed_step2_feature_description.md) · [Step 3](./delete_record_speed_step3_development_plan.md) · [Step 4](./delete_record_speed_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/delete_record_speed`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Cycle estimate: 0.55 (revised; original 0.75)

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

- Option A: [read model rebuild](../../concepts/read_model_rebuild.md) (used unchanged as the fallback), [static publication](../../concepts/static_publication.md) (changed: targeted invalidation), [post retraction](../../concepts/post_retraction.md) (used unchanged); proposed: *incremental source removal*, *operator command progress*.
- Option B: [read model rebuild](../../concepts/read_model_rebuild.md) (changed: deferred via task queue); proposed: *operator command progress*.
- Option C: [read model rebuild](../../concepts/read_model_rebuild.md) (changed: faster full rebuild).

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

## Revision 1 — 2026-11-04: instant deletion

Maintainer follow-up: “None of these sound like instant deletion?”

Options A–C all start from the current command and trim it. Deleting one post only has to touch that post, so the revision starts from that instead. This matches the [retraction handoff](../retraction_simplification_handoff.md): delete the content and commit immediately.

### Option D: Instant delete — touch only what the post touched

**Estimate: 0.55**

1. Read the post file at its known path. Use Git history only when the file is already gone (the repair path).
2. Find the thread's label files through a small read-model index of label paths by thread, kept up to date by rebuild and by label writes. No directory scan.
3. Remove the post and its proven labels and write the removal receipt as one commit, using plumbing so Git does not stat every file in a repository of about 311k files.
4. In one SQLite transaction, delete the post's rows and labels and update that thread's summary and counts, or drop the thread if the post was its root.
5. Delete only that thread's generated pages and mark the front page, Recent and its tag pages stale.
6. Print one line per step with elapsed time, ending with `Deleted <id> in 0.2s`.

Removing the post from Git history stays a separate, later `./v3 purge-history` run, out of scope here; step 3 keeps writing the same delete receipt that command reads. Inspect is steps 1–2 alone. Full rebuild and the resume journal stay as recovery when any step fails, and `--repair-labels` keeps its current thorough path.

- Pros: work is proportional to one post, not to the site; inspect and delete both become a few file reads, one commit and one transaction; the post is gone when the command returns.
- Cons: needs a new label-path index (a read-model schema change); the direct row deletion must match what a rebuild would produce, which needs a check against rebuild output.
- Driver: plumbing commit and the thread-summary update. Uncertainty: PHP startup and one Git commit on the shared host are the floor; if that floor is near 1s, the target needs the host itself to be faster.

## Revised Recommendation

**Option D.** It replaces Option A as the recommendation. A becomes the fallback idea if a direct row deletion turns out not to match rebuild output. Step 2 still starts with timing PHP startup and a bare Git commit on the qdb host, because those set the floor under any option.

**Vertical-slice viability:** on a copy of the qdb repository, `delete-record <id>` for one tagged thread prints each step, finishes in under 1s, the thread's page and front-page entry are gone, and a later full rebuild produces the same read model.

## Enhancement Requests

- Other commands that call a full rebuild for small changes (`AgentIdentityService`, approval receipt maintenance) may benefit from the same approach. Disposition: noted; not in this scope.

## Study Snapshots

2026-11-04T17:30:18Z; scope v1; starting revision `ea306fc03b3152a2e66b3d2a75b13d7d1c4064bc`; reference R1 (all three frozen hashes verified). Claude, single exposed rater; the delete path and read-model code were read before rating, so no pristine intake is claimed.

- Intake after inspection: **0.75** projected total for Option A, including the timing pass, progress lines and equivalence check.
- Step 1 handoff: **0.75**, Option A. Alternatives B 0.40 and C 0.90, non-additive. Not hours or measured labor; no implementation, accepted outcome or approval to advance.

2026-11-04T17:40:00Z revision: maintainer asked for instant deletion. Scope v2/D replaces the trimmed-command approach with proportional deletion: path-indexed label lookup, plumbing commit, single-transaction read-model update, targeted page invalidation, per-step status. Projection **0.55**; v1/A 0.75 preserved as an alternative; B 0.40 and C 0.90 reaffirmed. Same baseline/R1/P2, single exposed Claude rater. Direction refinement only; not Approved Step 1.

Status: Step 1 approved (Option D) on 2026-11-04; Step 2 drafted. Committed and pushed before approval at the session stop hook's request; this is not approval.

*— Claude · 2026-11-04 UTC · Model: claude-opus-5-5*

*— Revised by Claude · 2026-11-04 UTC · Model: claude-opus-5-5*
