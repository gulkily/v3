> **Feature plan:** [Step 1](./retraction_simplification_step1_solution_assessment.md) · [Step 2](./retraction_simplification_step2_feature_description.md) · [Step 3](./retraction_simplification_step3_development_plan.md) · Step 4

Debug mode: enabled (project marker).  
Effort study: `railspike-effort` / `v3/retraction_simplification`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Extended estimates: enabled.

**Cycle estimate: 1.50**

## Original Query

I think we overengineered the retraction feature. I think it should follow 2 steps:
1. Delete the content from repo and commit immediately.
2. Later, in a sweep, delete it from history.
Is that what we did? Did we add something more on top of it?
We seem to have built so much extra stuff on top to keep track of retracted items, perhaps mistakenly thinking that we want high reliability retraction rather than delayed unavailability ok retraction.
Is there anything we can do to fix it now or is it better to just rip out the retraction feature and rebuild it again later?

Clarification: “We should be saving a record of the retraction, but not this ledger type stuff.” Current request: “Great, please write Step 1.”

## Understood Intent and problem

Retraction should delete canonical content and retain a small record in one immediate commit, with later history purge and acceptable delay in derived-copy unavailability; the current feature instead retains the original file and operates a suppression/cleanup subsystem whose sweep excludes Git history.

## Option A — Simplify in place

**Estimate: 1.50**

- Estimate details: Driver: coordinated deletion/migration and publication cleanup plus history-purge recovery. Plausible range 1.30–1.80; uncertainty: legacy acceptance evidence and rewritten-ref recovery.

Change the existing action to delete content and retain its retraction record together, migrate previous retractions, and replace continuous retraction enforcement with ordinary refresh and deferred cleanup. Add a separate repeatable history-purge operation driven by those records.

- **Pros:** preserves authorization, familiar controls and reply structure; reuses useful deletion behavior without retaining its entire evidence machinery; avoids a feature gap.
- **Cons:** requires coordinated migration of readers, imports and publication checks; existing history-dependent validation must stop requiring the deleted content or original history.

*— Revised by Codex · 2026-10-25 UTC*

## Option B — Retire the feature, then rebuild

**Estimate: 1.80**

- Estimate details: Driver: transitional retirement and replacement, with the same migration, cleanup and history-purge outcome as A. Plausible range 1.50–2.20; uncertainty: temporary feature-gap compatibility and duplicate transition work.

Disable new retraction submissions, preserve existing records and removal behavior during transition, then replace the subsystem with deletion-plus-record and later history purge.

- **Pros:** permits a clean implementation boundary and avoids incrementally maintaining the old design.
- **Cons:** temporarily removes the action; still needs migration and cross-consumer cleanup; removing suppression prematurely could expose old retained content. Rebuilding does not avoid the difficult integration work.

*— Revised by Codex · 2026-10-25 UTC*

## Recommendation and release boundary

Choose **A**, split into two usable releases: **(1)** delete-and-record, migrate existing retractions, preserve replies, and remove retraction-specific publication/evidence gating; **(2)** deliver repeatable history purge with interruption recovery and verification over explicitly selected repository refs. Release 1 must state that history purge remains outstanding; the full objective is incomplete until release 2.

Keep a minimal durable record identifying target, actor, time and necessary structural/purge metadata, without deleted prose. Retain action-time authorization, signature checks where required, normal write serialization and actionable errors. Do not retain a parallel per-item cleanup ledger, generation stamps, ongoing history-based acceptance reconstruction or immediate suppression guarantees merely because they exist today. Define cache/log cleanup cadence and old-archive reimport behavior explicitly; accepted delay must not silently become permanent retention. History purge must preserve usable retraction records and account for changed commit identities; remote clones/backups are outside this proposed first scope.

Verify deletion/record commit and retry recovery, legacy migration, surviving replies, ordinary refresh/import/publication, and repeatable purge before calling the relevant release complete. Reconcile the newer high-traffic changes in `~/v3`; preserve its independent registration performance work. The diagnostics plan remains canceled. No new dependencies proposed; no live purge/deployment authorized.

## Study checkpoints and approval

2026-10-24T16:26:32Z: intake **1.50 projected total** for Option A's full two-release outcome, scope v1, starting revision `22134259876481199aacff6f6b2cd39a811236a9`; Step 1 handoff **1.50 reaffirmed**. R1/P2, Codex single exposed rater; prior source/reference inspection and review of `~/v3` at `2d91c8029` disclosed, no pristine intake reconstructed. Frozen R1 hashes verified. Migration, cross-consumer decoupling and purge recovery exceed one comfortable cycle, motivating the proposed split; no child scopes/scores or accepted outcomes invented. Includes planning, verification, documentation and ordinary correction, not hours or measured work. Related diagnostics cycle remains canceled, with its history intact. Include study bookkeeping in later documentation scope.

Await **Approved Step 1** before defining Step 2, under [Railspike Step 1](../../railspike/docs/workflow/step1_solution_assessment.md#next). This assessment and split proposal do not approve implementation.


**Approval recorded, 2026-10-24:** user replied “Approved Step 1.” Option A and the two-release split advance to [Step 2](./retraction_simplification_step2_feature_description.md). The original full-outcome scope and estimate remain above; Step 2 defines the first release.


## Extended-estimate activation — 2026-10-25

2026-10-25T18:04:54Z: enabled at the user's explicit request for all three artifacts. Earlier disabled-mode checkpoints remain historical; activation does not backfill estimates or authorize implementation. Study `railspike-effort`, cycle `v3/retraction_simplification`, R1/P2, original baseline `22134259876481199aacff6f6b2cd39a811236a9`; exact three frozen R1 hashes reverified. Codex single exposed rater with the earlier plans, scores and source review visible; no independent rating or measured labor. Exact runtime model/settings unavailable.

Stable option IDs `A/v1` and `B/v1` retain the original full two-release outcomes and baseline. These are first **current** option ratings, not reconstructed Step 1 originals. Recommended full-outcome projection **1.50 reaffirmed**; both alternatives exceed one comfortable cycle and retain the approved split recommendation. The active release-1 cycle is **1.00**, independently judged in Steps 2–3; do not treat 1.50 as the release-1 estimate.

*— Revised by Codex · 2026-10-25 UTC*
