> **Feature plan:** [Step 1](./fdp_feature_integration_step1_solution_assessment.md) · [Step 2](./fdp_feature_integration_step2_feature_description.md) · [Step 3](./fdp_feature_integration_step3_development_plan.md) · [Step 4](./fdp_feature_integration_step4_implementation_summary.md)

# Plan organization before implementation

## Problem
Railspike currently groups plans only after four artifacts accumulate. Smaller efforts and workflows that skip Step 1 lack a consistent feature folder, while the planning-commit rules do not explicitly allow the navigation repairs needed when plans move.

## User stories
- As a maintainer, I want each feature's plans grouped before implementation so that its decisions and commit history are easy to follow.
- As an agent user, I want organization to preserve approved content and working links so that moving files does not change what I approved.

## Core requirements
- After explicit `Approved Step 3` and creation of the Step 4 branch, organize the current feature's plans in `docs/plans/{feature_name}/` before its first commit or implementation, regardless of artifact count or skipped Step 1. Reuse an existing feature folder.
- Preserve separate step files, filenames, and approved substantive content. Repair relative plan navigation, affected repository references, and the feature's `docs/plans/README.md` entry; create the index if absent.
- Limit the initial commit to approved Steps 1–3 (Steps 2–3 when Step 1 was skipped) plus necessary organization moves, index entries, and reference repairs. Exclude implementation, the Step 4 summary, unrelated plans, and unapproved auxiliary content.
- Create the Step 4 summary afterward in the same folder. Retain existing approvals and stage-by-stage verification, summary updates, and commits.
- If a destination conflicts or organization requires substantive changes, stop and resolve the issue before committing or implementing; never overwrite artifacts or silently revise approved scope.

## Delivery scope
**Documentation-only**, for users and agents following Railspike. Allowed: `RAILSPIKE.md`, `docs/workflow/`, `README.md`, `docs/failure-modes.md`, explanatory copy in `index.html` and `HN_POST_DRAFT.md`, `BACKLOG.md` for the separate debug follow-up, and this cycle's artifacts and index in `docs/plans/`. Changes must concern plan organization and its commit boundaries. No application/runtime, script, CI, deployment, styling, or generated-artifact changes. Debug mode, logs, legacy compatibility, unrelated plan migration, and case studies are excluded.

## Completion boundary
The canonical rules, setup guidance, and public explanations describe one consistent organization-to-commit flow. Correct conflicting guidance and broken references before handoff; return for approval if resolution changes scope. Delivery ends with a verified documentation diff and stage summary, not a claim of guaranteed agent compliance.

## Risks
- **Conflicting commit rules:** agents may omit repairs or include extra files. Validate wording consistency before staging; retain one canonical allowance referenced by detailed guidance.
- **Moves break navigation or collide:** review root, existing-folder, and skipped-Step-1 cases early; require link repair and stop on ambiguous destinations.
- **Debug exceptions leak into this cycle:** compare against Option B during scope review; defer all diagnostic state and log allowances to the second cycle.

## Shared component inventory
Reuse `RAILSPIKE.md` as the canonical contract, existing step instructions for handoffs, and existing README/site explanations. No new UI or API surface; existing link and Git workflow checks remain unchanged.

## User flow
1. Review and approve plans through Step 3.
2. Create the branch, organize the feature's plans, repair navigation, and commit the permitted planning files.
3. Create the summary and deliver verified stages with their own commits.

## Success criteria
Root-level, already-grouped, and skipped-Step-1 scenarios each yield one navigable feature folder before implementation. Guidance consistently separates the planning commit from the summary and implementation. Local links and whitespace checks pass; manual scenario review confirms preserved approvals, bounded commit contents, and safe handling of conflicts.
