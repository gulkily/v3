> **Feature plan:** [Step 1](./railspike_workflow_visibility_step1_solution_assessment.md) · [Step 2](./railspike_workflow_visibility_step2_feature_description.md) · [Step 3](./railspike_workflow_visibility_step3_development_plan.md) · [Step 4](./railspike_workflow_visibility_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_visibility`, standing enrollment under [P2](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Original Query

Now that we understand the issue, please write Step 1 for fixing it.

## Understood Intent

Remove the unexplained workflow block above https://zenmemes.com/activity/. The phone-development feature added it (`40106400`); copy cleanup removed its heading (`6bf5bb65`). The user selected Option A; server-rendered workflow discovery and reading are separately proposed in the [follow-up assessment](../railspike_workflow_reading_step1_solution_assessment.md).

## Problem Statement

Private development workflow visibility bypasses Activity’s structure and depends on JavaScript for discovery and reading, leaving operators with an unexplained link and no equivalent without scripts.

## Option A: Remove the Activity block

Remove the separate workflow list and rely on the original forum threads for discovery and status.

- **Pros:** Smallest change; restores Activity’s coherent layout.
- **Cons:** Loses cross-thread discovery; existing thread status is also client-rendered, so removal alone does not solve readable access without scripts.

## Option B: Integrate private workflow events into Activity

Present authorized workflow changes as ordinary, server-rendered Activity entries with understandable labels and compatible filtering and pagination.

- **Pros:** One chronological activity experience; native links work without JavaScript.
- **Cons:** Current workflow summaries are not an event feed; requires decisions about event history, ordering, filters and private/public feed boundaries, expanding the fix substantially.

## Option C: Give workflows a coherent private reading surface

Provide an authorized, server-rendered development-workflow list and readable workflow detail, reachable through existing navigation and original threads. Remove the detached Activity block; retain JavaScript as an enhancement for refresh and signed actions.

- **Pros:** Makes discovery and reading usable without scripts; gives current workflow state a suitable home while preserving privacy and existing actions.
- **Cons:** Adds a destination and requires consistent navigation, thread links and authenticated-session recovery; signing actions may still require JavaScript.

## Recommendation and Vertical-Slice Viability

Recommend **Option A**, as selected by the user: remove the detached Activity workflow block and its Activity-only client behavior. Retain existing thread workflow controls, status and signed actions. Do not introduce a destination, feed events or a replacement link in this cycle.

This is a complete small cleanup release, not a claim that workflow reading works without JavaScript. Verify Activity with scripts on/off, operator and anonymous access, phone/desktop and affected themes; check that ordinary Activity navigation/pagination and thread workflow behavior still work. Record cleanup and verification in the UI audit checklist. Broader discovery/reading remains in the separate follow-up, which does not block removal.

## Revision and Follow-up

**Request:** “let's choose option a and save another step 1 document for what remains of option c” / **Disposition:** narrow this cycle to removal; save the remaining discovery/reading proposal [separately](../railspike_workflow_reading_step1_solution_assessment.md) / **Approval:** option selection and document revision authorized; Step 1 advancement and implementation await explicit approval / **Outcome:** two linked Step 1 assessments, no application changes. Original Option C recommendation is superseded; its v1 study snapshot below remains historical.

## Effort Snapshots

Intake and Step 1 handoff, 2026-10-23T05:26:58Z: scope **v1**, baseline `cd2f84087a6584d2b1cb3ac8ebaa2395fa897ec6`; R1/P2; Codex, single exposed rater. Intake follows the live HTML/source/history investigation and original phone-workflow plan review; no pristine prospective intake is claimed. Total includes planning, authorized reading/navigation recovery, implementation, verification and documentation. Numeric projection **unavailable**: frozen R1 report/diff are absent and current feedback hash differs from R1; retain R1 and restore exact sources before rating. Handoff reaffirms this limitation; no measured effort, implementation approval or accepted outcome is claimed.

Revision handoff, 2026-10-23T05:30:45Z: scope **v2** narrows this same cycle to Activity block removal and regression verification. Baseline, R1/P2 and exposed rater unchanged; total projection remains **unavailable** pending exact frozen-reference recovery. v1 is superseded, not erased. Follow-up work is linked separately and must not be counted as an independent split success toward the original broader outcome.

Awaiting **Approved Step 1** before Step 2; planning remains uncommitted.

Approval update, 2026-10-23: user explicitly approved Step 1 for Option A / scope v2. [Step 2](./railspike_workflow_visibility_step2_feature_description.md) is drafted for review; implementation remains unapproved. The separate reading follow-up is not approved by this action.
