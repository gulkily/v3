> **Feature plan:** [Step 1](./absent_retraction_snapshot_step1_solution_assessment.md) · [Step 2](./absent_retraction_snapshot_step2_feature_description.md) · [Step 3](./absent_retraction_snapshot_step3_development_plan.md) · [Step 4](./absent_retraction_snapshot_step4_implementation_summary.md)

Debug mode: enabled (project marker).
Effort study: `railspike-effort`, cycle `v3/absent_retraction_import`; [host ledger](../effort_estimation_trial.md#cycle-v3absent_retraction_import), standing enrollment P2.

## Original Query

Write Step 1 for making this scenario not abort the import.

## Understood Intent

Correction: the user meant offline snapshot publication, not import. Offline snapshot publication should finish when retained retraction records refer to posts already physically deleted from the destination, while keeping removed content out of published views. Task 952 exposed this condition for `reply-20260526032118-654b2b08` and `reply-20260526032816-a1447d49`; both have retained retractions and committed source deletions.

## Problem

The shared publication guard treats an absent retraction target as unavailable evidence, so offline snapshot tasks exhaust their retries without producing base or update snapshots.

## Option A

For a readable, valid retraction whose target is confirmed absent, exclude the target post and its retraction from that snapshot and continue publishing the remaining content. Keep that target excluded for the current build if it reappears; defer validation of the reappeared source to a later build under existing authority rules. Retain all canonical records and existing deletions.

- Pros: completes publication despite the absent target or its reappearance during the build; avoids resolving reappearance within the same build.
- Cons: reappeared content may be temporarily omitted; exclusions must cover stale cached content and remain confined to the affected target. Absence must remain distinguishable from unreadable, unsafe or corrupt evidence.

## Option B

Require trusted historical source evidence or locally accepted removal evidence for every absent target before publication can finish, including an explicit legacy repair path.

- Pros: preserves stronger provenance for permanent removal and protection against reimport.
- Cons: snapshot publication still blocks until repaired; missing history can prevent recovery; broader than tolerating an already absent target.

## Option C

Skip the failed snapshot build, retain any previously published snapshot, and report publication as incomplete for a later retry.

- Pros: keeps unrelated processing moving and preserves a usable prior snapshot when available.
- Cons: leaves snapshots unavailable or stale and does not resolve the validation failure.

## Option difficulty estimates

R1 scale: **1.0 = one comfortably scoped, releasable Railspike cycle**, including investigation, planning, implementation, verification, documentation and ordinary correction. These are approximate total-effort judgments, not hours or failure probabilities.

| Option | Score | Main difficulty |
| --- | --- | --- |
| A — exclude affected target and continue | **0.70** | Defer reappearance validation to a later build; retain direct/queued base/update coverage, stale-content exclusion and concurrent-change isolation. Provisional until planning confirms the simplification. |
| B — trusted evidence plus legacy repair | **1.20** | Adds provenance decisions and an operator repair/recovery path to safe publication; unavailable historical evidence can prevent completion. Discuss splitting into releasable slices before approval. |
| C — preserve prior snapshot and defer | **0.35** | Smaller failure-handling slice: preserve prior artifacts, report incomplete publication and verify retry/no-prior-snapshot behavior. Does not resolve the requested successful-publication outcome. |

Option A remains recommended: it delivers the requested outcome with less scope than B. C is cheaper because it delivers a narrower fallback.

## Recommendation

Choose **Option A** as one releasable slice covering successful base/update snapshot publication through both the direct publisher and queued worker, with regression coverage for other consumers of the shared guard. Retain canonical retraction records and deletions; do not create trusted removal authority. Current-build exclusion is temporary publication filtering, not a determination that a reappeared post was validly retracted. A later build must validate present sources, including unrelated authors and protected posts, under existing authority rules. Existing trusted removal evidence remains authoritative when available.

- Verify direct publication and queued-worker retries complete and produce base/update snapshots excluding the affected post and retraction while retaining surviving replies. Ensure update snapshots cannot leave the excluded target visible through an earlier base snapshot. Limit shared static publication work to compatibility regression coverage.
- Cover stale read-model content, flat/dated paths and reappearance during the build: keep the affected target excluded and continue; verify later-build authority checks for mismatched authors and protected posts. Defer same-build reappearance resolution.
- Abort only when safe isolation or the safety of the remaining snapshot cannot be established. Missing tracked retraction files, unreadable repositories, unsafe paths and malformed evidence remain safety failures, not confirmed absence; preserve existing rejection behavior and actionable diagnostics. This cycle does not introduce generic skipping of invalid records.
- Preserve valid retraction behavior and private-destination access rules. Broader deletion-effect repair, project-wide resilient processing and operator alerting (tracked in todo.txt) and live canonical repair are outside this cycle.
- Dependency delta: zero proposed. No implementation, live repair or task retry is authorized by this assessment.

## Study checkpoint and approval

Historical intake and Step 1 handoff (scope v1 used the mistaken import framing): 2026-10-23T18:16:27Z; scope v1, baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2, Codex single exposed rater. Prior task diagnostics and source inspection preceded enrollment; no pristine intake score is reconstructed. Projected total is unavailable because the frozen R1 report/diff sources are absent at their recorded location; no replacement reference or numeric score is invented. Scope includes planning, implementation, verification, recovery and documentation; no measured effort or accepted outcome. Related cycle: `v3/retraction_effects`; its incomplete broader outcome remains unchanged. Ledger bookkeeping belongs to this cycle's documentation scope.

Scope v2 correction (2026-10-23): focus on offline snapshot publication; filenames now use `absent_retraction_snapshot`. The original study ID is retained for continuity, not a second observation. Numeric projection remains unavailable under R1/P2. User explicitly requested committing this planning handoff for later work on dev; this overrides the usual uncommitted planning rule for this commit only. Implementation and Step 2 remain unapproved.

Scope v3 received **Approved Step 1**; continued in [Step 2](./absent_retraction_snapshot_step2_feature_description.md).

## 2026-10-23T19:20:50Z — Step 1 scoring revision

- Request: estimate each option's difficulty. Disposition: in-scope assessment refinement; completed above. Step 1 and implementation remain unapproved.
- Study snapshot: `railspike-effort`, stable cycle `v3/absent_retraction_import`, scope v2, original baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2; Codex single exposed rater. Existing option descriptions, historical diagnostics and calibration scores were visible; no new application inspection or independent rating.
- Exact frozen R1 report, diff-review and feedback snapshots in `docs/research/effort_estimation/references/R1/` are now available; all three SHA-256 hashes match the host ledger. This dated projection supersedes unavailability for the current checkpoint without backfilling historical scores or changing references.
- Recommended-scope projected total: **0.85** (A); alternatives **1.20** (B) and **0.35** (C), mutually exclusive comparisons, not additive estimates or separate study outcomes. Uncertainty remains until detailed planning; no measured labor or accepted outcome. Dependency delta remains zero proposed.

## 2026-10-23T19:25:12Z — Step 1 scope v3 revision

- Request: trim Option A while avoiding process aborts, then update the assessment. Disposition: revised current Step 1; no approval to advance or implement. The suggested abort-on-reappearance trim (approximately 0.65, conversational only) was rejected and is not adopted.
- Scope v3: exclude the affected post/retraction for the current snapshot, continue through target reappearance, retain canonical evidence and validate present sources on a later build. Keep direct/queued base/update, stale-content, surviving-reply, authority and isolation verification; defer same-build reappearance resolution. Unsafe or unisolatable evidence still blocks publication.
- Study snapshot: `railspike-effort`, stable `v3/absent_retraction_import`, original baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2, Codex single exposed rater. Recommended total **0.70**, revised from v2 **0.85** for reduced same-build resolution scope; includes prior investigation, planning and ordinary correction. B **1.20** and C **0.35** unchanged. Frozen R1 hashes reverified; no new source inspection, independent rating, measured effort or accepted outcome. Feasibility and score remain provisional until detailed planning.
- Historical v1/v2 checkpoints remain above. Dependency delta: zero proposed. Step 1 approval remains pending; no implementation, live repair, task retry or commit authorized by this revision.
