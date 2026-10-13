> **Feature plan:** [Step 1](./instruction_debug_mode_step1_solution_assessment.md) · [Step 2](./instruction_debug_mode_step2_feature_description.md) · [Step 3](./instruction_debug_mode_step3_development_plan.md) · [Step 4](./instruction_debug_mode_step4_implementation_summary.md)

# Railspike instruction debug mode

## Problem
Feedback on Railspike's instructions is currently informal and easy to lose across sessions. Users need optional, evidence-backed diagnostics that persist without changing approval gates or silently changing the workflow.

## User stories
- As a maintainer, I want source-backed findings about useful and redundant instructions so that I can review improvements separately.
- As a user, I want my debug setting and prior findings preserved across steps and sessions so that I can resume or disable diagnostics without losing history.

## Core requirements
- **Durable mode:** New workflows default off; plain-language enable/disable requests control a visible field below the current artifact's navigation bar. Inherit into subsequent artifacts, including Step 4. Restore state on entry, resumption, and handoff; the latest explicit request wins, historical fields remain snapshots, and unresolved missing/conflicting resumed state requires clarification rather than silent reset.
- **Evidence-backed reports:** When enabled, each completed Step 1–4 handoff includes one concise diagnostic summary and log link before approval where applicable, never per phase or stage. Always include useful and redundant instruction groups, using “No supported findings” when empty. Each finding names its instruction source, observable evidence, and proposed retention or change; distinguish inapplicable and uncertain findings. Non-use alone does not establish redundancy. Disabled mode emits no diagnostics.
- **Persistent history:** Save full diagnostic output before presenting it in `{feature_name}_debug_log.md` beside the plans. Append UTC-stamped, step-identified entries; corrections append labeled revisions identifying superseded entries, unchanged reports are skipped, and disabling preserves history. Disclose write failures rather than claiming the output was saved. Move the log with the feature's plans and repair links.
- **Bounded commits:** Keep planning logs uncommitted through Steps 1–3; explicitly allow this feature's existing log in the initial planning commit. During Step 4, commit additions with the next authorized stage or a separate log-only handoff commit after the final stage. Record mode changes in the summary for the next authorized stage commit. Never amend completed commits or admit unrelated auxiliary content through this allowance.
- **Advisory only:** Mode requests and findings neither approve steps nor authorize branching, commits, or instruction edits. Preserve required instructions, plan revision, organization, and stage gates; diagnose workflow instructions, not application bugs or hidden reasoning.

## Delivery scope
**Documentation-only**, for Railspike users and agents. Allowed: `RAILSPIKE.md`, `docs/workflow/`, `README.md`, `docs/failure-modes.md`, `BACKLOG.md` to close the follow-up, and this cycle's artifacts/index in `docs/plans/`. No application/runtime, scripts, CI, deployment, generated assets, website changes, CLI, telemetry, compatibility aliases, or automatic instruction rewrites.

## Completion boundary
One canonical contract connects enablement, recovery, completed-step reporting, logging, and final handoff. Usage and recovery guidance agree with it. Correct contradictions and broken references before handoff; request approval for scope changes. Verification demonstrates document consistency and scenario coverage, not guaranteed agent adherence.

## Risks
- **State loss:** wrong diagnostics after resumption; review inheritance/conflict cases early and clarify unresolved state.
- **Unsupported or repetitive findings:** misleading feedback; review empty/inapplicable/revised cases and require evidence and unchanged-report suppression.
- **Log/commit confusion:** lost history or unauthorized changes; inspect write-failure, folder-move, initial-commit, and final-handoff cases; retain narrow allowances and append-only history.

## Shared component inventory
Extend the canonical `RAILSPIKE.md` contract and existing step entry/handoff guidance; reuse plan navigation, organization, and commit rules. README and failure guidance explain the same contract. No UI/API surface is introduced.

## User flow
1. Enable debug mode explicitly; save the setting in the current artifact.
2. Complete the approved step, save and present its diagnostic report, then request approval normally.
3. Resume from durable state, revise or disable as requested, and preserve logs through organization and authorized commits.

## Success criteria
Review scenarios cover default-off, enable/disable, inheritance, context loss, unresolved state, empty/inapplicable findings, revisions, unchanged reports, write failures, folder moves, and final-log commits. All four steps reference the shared contract; phase/stage boundaries produce no reports. Links, scope, and commit-rule consistency checks pass.
