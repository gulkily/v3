> **Feature plan:** [Step 1](./installation_process_improvements_step1_solution_assessment.md) · [Step 2](./installation_process_improvements_step2_feature_description.md) · [Step 3](./installation_process_improvements_step3_development_plan.md) · [Step 4](./installation_process_improvements_step4_implementation_summary.md)

Debug mode: disabled

## Problem

Submodule-first installation disrupts active projects unnecessarily, while welcome requires users to repeat their intent to use Railspike for the first substantial task.

## User stories

- As a project owner, I want reviewed Railspike files copied into my project so that installation preserves my ongoing work.
- As a new user, I want my first substantial task to enter Railspike planning so that installation leads directly to using the process.

## Core requirements

- Default to safely unpacking one identified source snapshot in an isolated temporary directory, inspecting it before executing or adopting its instructions, then copying that reviewed snapshot into `docs/railspike`. Preserve upstream structure and licensing; exclude Git metadata. Report inspection limitations and stop on concerns.
- Preserve unrelated files and staging without requiring a clean tree, stashing, staging, or committing for the default installation. Detect occupied or redirected destinations and existing installations; do not overwrite, merge blindly, or convert submodules automatically. Recheck failed prerequisites after remediation; limit cleanup to this attempt's files.
- Verify copied contents before welcome; record source and revision durably and report destination, changes, and remaining issues. Document manual updates that review the replacement and preserve local edits. Submodule installation requires explicit request; label its initialization/update guidance separately.
- Announce Railspike as the default for the first suitable task during welcome. On receiving that task, begin the appropriate planning step unless the user opts out; preserve the small-task exception and every subsequent approval gate.
- Offer persistent project guidance separately, showing text that defaults suitable future tasks to Railspike with an opt-out. Require consent, preserve existing instructions, and resolve conflicts. An unanswered offer neither blocks the first task nor authorizes a project-file edit.

## Delivery scope

Documentation-only: edit `README.md`, `WELCOME.md`, this feature's planning/checklist files, and its `docs/plans/README.md` index entry. Audience: installing agents and project owners. Application/runtime, scripts, tests, CI, and deployment changes are not permitted. Minimal packaging, automatic updates, submodule conversion, and the instruction-reprinting backlog item are excluded.

## Completion boundary

Deliver consistent installation, manual-update, recovery, and welcome guidance. Users can reach a verified copy and first planning artifact, or receive a specific recoverable blocker. Correct contradictions before handoff; report scenario-review evidence and limitations without claiming universal agent compliance.

## Risks

- Overwrites or partial copies could damage work: review conflict/extraction cases before Step 3; require bounded writes, completeness checks, and scoped recovery.
- Copies could lose provenance or local edits during updates: review the update lifecycle before Step 3; require durable revision information and comparison before replacement.
- Default workflow use could be mistaken for implementation approval: review welcome branches before Step 3; separate task entry, persistent-guidance consent, and step approvals.

## Shared component inventory

Reuse README's canonical installation/update guidance and WELCOME's onboarding/project-guidance text. RAILSPIKE already supports vendored paths; the website already describes submodules as optional. Neither needs changes. No UI/API components are involved.

## Simple user flow

1. Request installation; agent unpacks, reviews, checks the destination, copies, and verifies.
2. Receive installation details, welcome, and the separate persistent-guidance offer.
3. Supply a suitable task; agent writes the first applicable plan and waits for approval.

## Success criteria

All 13 [checklist items](./installation_process_improvements_failure_modes_checklist.md) have recorded verification or justified applicability decisions. Reviews cover unrelated dirty work, conflicts, partial failure, revision identity, manual updates, explicit submodules, unanswered guidance, opt-out, small tasks, and planning approval gates. Changed-document links and scope checks pass.
