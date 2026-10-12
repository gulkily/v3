> **Feature plan:** [Step 1](./fdp_feature_integration_step1_solution_assessment.md) · [Step 2](./fdp_feature_integration_step2_feature_description.md) · [Step 3](./fdp_feature_integration_step3_development_plan.md) · [Step 4](./fdp_feature_integration_step4_implementation_summary.md)

## Original Query
We added two new features while still working on the FDP project. Let us fold them into this one, please. Review the latest commits for `~/fdp` and write Step 1 of `RAILSPIKE.md` to fold them into this project.

## Understood Intent
Bring the two latest workflow additions into Railspike while preserving its names, paths, editable-plan guidance, and deliberate removal of backwards compatibility.

## Problem
Railspike lacks FDP's durable instruction diagnostics and consistent pre-implementation plan organization, whose shared commit rules need to be reconciled with the current workflow.

## Source findings
Reviewed local FDP history through `b850ed7`:
- **Debug mode:** `00222d4`, `d3ffc48`, and `6fdf330` introduce optional diagnostics about the workflow instructions themselves; `b850ed7` adds persistent logging. New workflows default off; explicit requests control durable state inherited across steps and restored after context loss. Completed steps report evidence-backed useful/redundant findings, distinguishing inapplicable or uncertain instructions. Reports are advisory, not permission to change instructions or advance.
- **Plan organization:** `7f16579` requires a feature folder after Step 3 approval and branching, before the planning commit, even when Step 1 is skipped. `b850ed7` includes an existing debug log in that commit. Only necessary moves, index/reference repairs, and approved plans are otherwise allowed; the Step 4 summary comes later.
- **Evidence boundary:** FDP records document checks and manual scenarios for the original debug feature, not guaranteed agent adherence. Its later logging and organization changes require fresh validation here. Earlier diagram changes are outside this two-feature scope.

## Option A — Adapt both as one coherent workflow update
Port the two behaviors together into Railspike's canonical instructions and usage guidance, reconciling their shared state, organization, and commit boundaries.
- **Pros:** Preserves both features end to end without reintroducing legacy paths; one consistent initial-commit contract.
- **Cons:** Requires careful comparison across step entry, resumption, completion, and public guidance.

## Option B — Integrate organization first, debug mode separately
Complete a standalone plan-organization cycle, then assess and deliver diagnostics in another cycle.
- **Pros:** Separate feature histories make the Git log easier to read; smaller reviews and independently complete deliveries.
- **Cons:** Revisits the same planning-commit rules to accommodate logs; leaves one requested feature deferred.

## Option C — Cherry-pick the FDP commits and repair conflicts
Import the source commits, then reconcile their changes with Railspike's renamed files and newer copy.
- **Pros:** Retains source commit history and its original grouping.
- **Cons:** Touches obsolete entrypoints and historical artifacts; conflict resolution risks restoring removed compatibility or overwriting newer guidance.

## Recommendation
Choose **Option B**, as requested during review, to keep the Git history readable. Deliver two sequential documentation-only cycles, each with its own approved requirements, plan, feature branch, planning commit, stage commits, and summary.

- **First cycle — plan organization:** Make feature-folder organization part of Step 4 setup after approval and branching, before the planning commit. Preserve approved content; allow only the necessary moves, index entries, and reference repairs alongside approved plans. Create the summary afterward. Validate skipped Step 1, existing folders, navigation, and commit boundaries. This is independently useful without debug mode; exclude diagnostic state, reporting, and log exceptions from this cycle.
- **Second cycle — debug mode:** Build on the completed organization rules. Cover durable enable/disable state, recovery, evidence-backed handoff reports, and append-only timestamped logs. Introduce the log-specific planning/stage/final-handoff commit allowances here. Validate disabled and resumed workflows, revisions, unchanged-report suppression, and write failures.

Preserve explicit approvals, direct plan revision, and per-stage commits in both cycles; add no CLI, legacy aliases, unrelated history, or case studies. After Step 1 approval, Step 2 should scope only plan organization. Debug mode remains a separate follow-up requiring its own planning and approvals.
