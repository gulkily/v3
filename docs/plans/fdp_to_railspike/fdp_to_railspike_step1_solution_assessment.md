> **Feature plan:** [Step 1](./fdp_to_railspike_step1_solution_assessment.md) · [Step 2](./fdp_to_railspike_step2_feature_description.md) · [Step 3](./fdp_to_railspike_step3_development_plan.md) · [Step 4](./fdp_to_railspike_step4_implementation_summary.md)

## Original Query
I want to convert this project from using FDP to using Railspike, from https://github.com/gulkily/railspike. Please write Step 1 of docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md.

## Understood Intent
Assess replacing the project's FDP workflow with Railspike; this deliverable follows the existing FDP process and does not perform the migration.

## Problem Statement
The project needs one authoritative Railspike workflow while preserving usable update commands, feature-request handoffs, and existing planning history.

## Current Context
- FDP is vendored at `docs/fdp/`; the root README, CLI reference, and extension cookbook link to it.
- `v3` implements FDP subtree synchronization; handoff drafts use FDP wording, and stored/API handoff data uses `fdp_step1`. Tests cover these integrations and documentation rendering.
- Upstream [Railspike](https://github.com/gulkily/railspike#quick-start) recommends a submodule. Its [workflow](https://github.com/gulkily/railspike/blob/master/RAILSPIKE.md) changes the entrypoint, instruction paths, and plan-folder organization, so adoption requires more than a rename.

## Option A: Adopt Railspike as a Git submodule
Replace the vendored FDP workflow with Railspike's recommended distribution model and align project entrypoints and integrations with it.

- **Pros:** Follows upstream installation guidance; pins an explicit revision; removes the need for custom subtree update logic.
- **Cons:** Changes clone/setup and update procedures; existing tooling and documentation consumers must handle an uninitialized submodule.

## Option B: Vendor Railspike using the existing subtree model
Replace FDP with a reviewed Railspike snapshot at a clearly named location, retaining self-contained checkouts and adapting the existing synchronization interface.

- **Pros:** Preserves the project's established distribution model; instructions remain available in ordinary clones; minimizes contributor setup changes.
- **Cons:** Retains custom synchronization maintenance; differs from upstream's recommended installation; existing branch and legacy-import assumptions need verification.

## Option C: Keep FDP and add Railspike alongside it
Introduce Railspike for new work while leaving existing FDP instructions and integrations active during a transition.

- **Pros:** Small initial disruption; existing commands and in-progress work continue unchanged.
- **Cons:** Creates competing workflow authorities and duplicate maintenance; requires a second migration to complete the conversion.

## Recommendation
Choose **Option B** for continuity with this project's self-contained subtree setup. Adopt Railspike as the sole workflow for new work, align active documentation, synchronization, and generated handoff wording, and provide a clear compatibility path for old entrypoints. Preserve historical plans and existing `fdp_step1` data/API compatibility rather than requiring a schema migration for terminology alone.

The viable slice is repository workflow maintenance: a contributor can discover Railspike, start a new plan, update the installed instructions, and use existing handoffs without broken references or lost data. Verify instruction availability, affected links, synchronization behavior, and handoff compatibility. Leave historical-plan rewrites and unrelated application changes outside this slice; explicitly resolve how in-progress FDP plans finish before changing their governing process.
