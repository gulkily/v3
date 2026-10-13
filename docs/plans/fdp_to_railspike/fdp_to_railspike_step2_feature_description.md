> **Feature plan:** [Step 1](./fdp_to_railspike_step1_solution_assessment.md) · [Step 2](./fdp_to_railspike_step2_feature_description.md) · [Step 3](./fdp_to_railspike_step3_development_plan.md) · [Step 4](./fdp_to_railspike_step4_implementation_summary.md)

## Problem
FDP is embedded in contributor guidance, workflow updates, and feature-request handoffs. Adopting Railspike must give new work one authoritative process while preserving existing work and compatibility.

## User Stories
- As a contributor, I want Railspike available in an ordinary checkout so that I can plan work without extra setup.
- As a maintainer, I want a reliable workflow update command so that reviewed upstream instructions stay maintainable.
- As an approved local user, I want Railspike handoff drafts so that new requests follow the current process without losing existing handoffs.

## Core Requirements
- Vendor a reviewed, revision-recorded Railspike subtree at `docs/railspike/`, with `RAILSPIKE.md` as the new-work entrypoint; align active guidance and plan-index instructions with its workflow.
- Provide `./v3 railspike sync` with the existing target-repository and explicit-source-override capabilities, clean-tree protection, and unexpected-source rejection. Retain `./v3 fdp sync` as a deprecated alias to Railspike updates; an unmigrated target must receive migration guidance without modification.
- New handoff wording uses Railspike; recognize `railspike` eligibility tags alongside existing tags. Preserve `fdp_step1` storage/API compatibility, historical draft text, approval controls, and runner behavior.
- Preserve historical plans and a clearly labeled, frozen FDP reference for already-started cycles, including this migration. Old documentation entrypoints explain the transition and link to Railspike; existing cycles finish under FDP unless explicitly replanned and approved.
- New cycles follow Railspike's planning and organization rules; no automatic rewriting, moving, or reapproval of unrelated existing plans.

## Delivery Scope
**Work type:** application change, classified as internal maintenance. Includes vendored instructions, transition guidance, active documentation links, CLI synchronization, handoff wording/tag recognition, and relevant regression coverage. Excludes historical-plan rewrites, data-contract renames, unrelated application changes, and redesigned handoff generation or execution.

## Completion Boundary
From contributor guidance or an eligible forum request, users reach Railspike instructions or a Railspike-labeled draft; maintainers can update the subtree. Failed updates preserve existing work and provide actionable recovery guidance. Release requires passing affected CLI, documentation, and handoff checks plus a documented transition and recovery path.

## Risks
- **Update incompatibility:** legacy subtree metadata and inconsistent branch assumptions can break updates. Validate upstream identity and import/update behavior in disposable repositories before Step 3; define recovery before scheduling dependent work.
- **Conflicting instructions:** upstream setup guidance and old FDP links can misdirect contributors. Validate entrypoints and workflow differences before Step 3; keep project-specific subtree guidance separate and label the frozen reference.
- **Handoff regression:** terminology changes can disrupt existing records or eligibility. Validate draft/API consumers before Step 3; retain compatibility fields, old tags, and authorization behavior.

## Shared Component Inventory
- Reuse `v3` command dispatch/synchronization and existing README, CLI reference, cookbook, and plan-index guidance.
- Reuse the public documentation catalog/controller/Markdown renderer for new and legacy documentation URLs.
- Extend `CodexHandoffDraftService` and `PostWorkflowService`; preserve `CodexHandoffStore`, handoff API responses, and runner contracts.
- Reuse both post-card and thread-root-card handoff previews and their existing approval controls; no new UI or API surface.

## Simple User Flow
1. Follow project guidance to Railspike and draft a new plan, or prepare an eligible forum handoff.
2. Review and approve through the existing process controls.
3. Update vendored instructions when needed; follow recovery guidance if updating fails.

## Success Criteria
An ordinary clone contains usable Railspike instructions; active entrypoints and legacy guidance resolve; canonical and alias updates pass success/failure checks; new drafts and tags work while existing records and approvals remain compatible; unrelated plans remain unchanged.
