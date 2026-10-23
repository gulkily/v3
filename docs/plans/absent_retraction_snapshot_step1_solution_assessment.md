> **Feature plan:** [Step 1](./absent_retraction_snapshot_step1_solution_assessment.md) · [Step 2](./absent_retraction_snapshot_step2_feature_description.md) · [Step 3](./absent_retraction_snapshot_step3_development_plan.md) · [Step 4](./absent_retraction_snapshot_step4_implementation_summary.md)

Debug mode: enabled (project marker).
Effort study: `railspike-effort`, cycle `v3/absent_retraction_import`; [host ledger](./effort_estimation_trial.md#cycle-v3absent_retraction_import), standing enrollment P2.

## Original Query

Write Step 1 for making this scenario not abort the import.

## Understood Intent

Correction: the user meant offline snapshot publication, not import. Offline snapshot publication should finish when retained retraction records refer to posts already physically deleted from the destination, while keeping removed content out of published views. Task 952 exposed this condition for `reply-20260526032118-654b2b08` and `reply-20260526032816-a1447d49`; both have retained retractions and committed source deletions.

## Problem

The shared publication guard treats an absent retraction target as unavailable evidence, so offline snapshot tasks exhaust their retries without producing base or update snapshots.

## Option A

Recognize a readable, valid retraction with a confirmed absent target as a supported state; publish only after confirming the candidate contains no content for that absent target, and validate any subsequently present source under existing authority rules.

- Pros: handles legacy deletions without restoring content; gives direct publication and queued snapshots consistent validation.
- Cons: absence must remain distinguishable from unreadable, unsafe or corrupt evidence; reappearance and concurrent changes need coverage.

## Option B

Require trusted historical source evidence or locally accepted removal evidence for every absent target before publication can finish, including an explicit legacy repair path.

- Pros: preserves stronger provenance for permanent removal and protection against reimport.
- Cons: snapshot publication still blocks until repaired; missing history can prevent recovery; broader than tolerating an already absent target.

## Option C

Skip the failed snapshot build, retain any previously published snapshot, and report publication as incomplete for a later retry.

- Pros: keeps unrelated processing moving and preserves a usable prior snapshot when available.
- Cons: leaves snapshots unavailable or stale and does not resolve the validation failure.

## Recommendation

Choose **Option A** as one releasable slice covering successful base/update snapshot publication through both the direct publisher and queued worker, with regression coverage for other consumers of the shared guard. Retain retraction records and deletions; do not silently create trusted removal authority or suppress a subsequently present unrelated author's post. Existing trusted removal evidence remains authoritative when available.

- Verify direct publication and queued-worker retries complete and produce base/update snapshots excluding absent targets while retaining surviving replies; verify shared static publication remains compatible.
- Cover stale read-model content, flat/dated paths, source reappearance, mismatched authors, protected posts and concurrent changes. Missing tracked retraction files, unreadable repositories, unsafe paths and malformed evidence must still block unsafe publication with actionable diagnostics.
- Preserve valid retraction behavior and private-destination access rules. Broader deletion-effect repair, project-wide resilient processing and operator alerting (tracked in todo.txt) and live canonical repair are outside this cycle.
- Dependency delta: zero proposed. No implementation, live repair or task retry is authorized by this assessment.

## Study checkpoint and approval

Historical intake and Step 1 handoff (scope v1 used the mistaken import framing): 2026-10-23T18:16:27Z; scope v1, baseline `aeaad63298ffe72b792bce84acd9f72c310c4bef`, R1/P2, Codex single exposed rater. Prior task diagnostics and source inspection preceded enrollment; no pristine intake score is reconstructed. Projected total is unavailable because the frozen R1 report/diff sources are absent at their recorded location; no replacement reference or numeric score is invented. Scope includes planning, implementation, verification, recovery and documentation; no measured effort or accepted outcome. Related cycle: `v3/retraction_effects`; its incomplete broader outcome remains unchanged. Ledger bookkeeping belongs to this cycle's documentation scope.

Scope v2 correction (2026-10-23): focus on offline snapshot publication; filenames now use `absent_retraction_snapshot`. The original study ID is retained for continuity, not a second observation. Numeric projection remains unavailable under R1/P2. User explicitly requested committing this planning handoff for later work on dev; this overrides the usual uncommitted planning rule for this commit only. Implementation and Step 2 remain unapproved.

Awaiting **Approved Step 1** before Step 2.
