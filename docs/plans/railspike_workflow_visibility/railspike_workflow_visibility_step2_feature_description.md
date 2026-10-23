> **Feature plan:** [Step 1](./railspike_workflow_visibility_step1_solution_assessment.md) · [Step 2](./railspike_workflow_visibility_step2_feature_description.md) · [Step 3](./railspike_workflow_visibility_step3_development_plan.md) · [Step 4](./railspike_workflow_visibility_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort / v3/railspike_workflow_visibility`, scope v2, [standing P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2). Step 1 Option A approved.

## Problem

Activity prepends private workflow-database summaries as unexplained JavaScript-generated links outside its normal feed. Removing that block moves Activity toward repository-backed content, without establishing complete repository coverage.

## User Stories

- As an Activity reader, I want a consistent page with or without scripts so that unrelated workflow links do not interrupt navigation.
- As an operator, I want existing workflow controls in their original threads to keep working so that Activity cleanup does not interrupt assessments.

## Core Requirements

- Remove the detached workflow block and its Activity-only fetching, rendering, polling and workflow initialization; add no replacement heading, link or destination.
- Preserve ordinary Activity entries, filters, pagination, RSS and access rules; change no inclusion rules or repository records.
- Preserve thread/standalone-post workflow status, history, signed actions, authorization and recovery. Retain existing documented workflow API contracts.
- Remove Activity-specific workflow asset dependencies only where other Activity behavior does not need them; shared authentication and other consumers must remain functional.
- Record cleanup, rendered verification and any limitations in the UI audit checklist; maintain this cycle’s plans, debug log and effort ledger.

## Delivery Scope and Completion Boundary

**Work type: application change.** Deliver the normal `/activity/` journey without the detached workflow UI, then verify that filters, paging and opening an ordinary entry still work. Existing failure and pagination recovery remain usable. No new route, event feed, read-model schema, general rendering audit or script-free workflow controls are included. Release requires rendered regression evidence and the existing workflow journey checks.

**Known environment:** the reported entry is `https://zenmemes.com/activity/`, including browsing without JavaScript. After release, reloading that page must show its normal heading/navigation and feed without workflow links above them for both operator and anonymous readers. Ordinary filters/paging and their existing recovery actions remain the next steps. Local rendered evidence supports the change; production verification must be identified separately if deployment is outside the authorized work.

## Risks

- Shared scripts may support other Activity behavior: identify consumers before Step 3 and remove only proven workflow-only dependencies.
- Removing shared refresh logic could break thread controls: validate consumer boundaries before Step 3; preserve the thread path and verify its signed-action journey.
- Operators lose the cross-thread workflow list: deliberate Option A tradeoff; original threads remain available, with broader discovery/reading saved separately.

## Shared Component Inventory

- Standard Activity page/template and shared workflow browser renderer: remove their workflow integration; reuse ordinary Activity rendering/navigation.
- Thread and standalone-post controllers, root/reply cards and shared workflow mount/renderer: retain existing workflow presentation/actions.
- Workflow API thread reads, private summary reads and signed writes, plus API discovery: preserve contracts; Activity stops consuming summary reads.
- CLI queue status and private workflow storage: retain status/data. RSS, Forte and static/offline consumers gain no private workflow projection.

## Simple User Flow and Success Criteria

1. Open Activity as an operator or anonymous reader, with scripts enabled or disabled: no detached workflow block appears and no Activity-only workflow request/poll runs.
2. Use existing filters, paging and entry links: ordinary Activity results and recovery behave as before.
3. Open a workflow’s original thread or standalone post: existing authorized status/actions and failure recovery still work.

Verify phone/desktop and affected themes for layout/overflow; update the existing browser expectation that currently requires the removed link. No new navigation icon is introduced.

## Follow-ups and Study Checkpoint

**Request:** move toward Activity showing only repository-backed material and all relevant repository changes / **Disposition:** removal addresses this one non-repository block; broader provenance/coverage audit is separate, not a completion claim or added implementation scope / **Approval:** no coverage implementation authorized / **Follow-up:** retain the existing [coverage checklist](../backlog/activity_repository_coverage_checklist.md); [workflow reading](../railspike_workflow_reading_step1_solution_assessment.md) remains separately unapproved.

2026-10-23T05:33:24Z, Step 2 handoff: scope v2, baseline `cd2f84087a6584d2b1cb3ac8ebaa2395fa897ec6`, R1/P2, Codex single exposed rater. Targeted controller/template/API/browser-consumer inspection supplements earlier exposure. Projected total remains **unavailable** pending exact frozen R1 recovery; original snapshots and split relationship retained. No measured effort, implementation or accepted outcome. Awaiting **Approved Step 2**; planning remains uncommitted.

Approval update, 2026-10-23: user explicitly approved Step 2 scope v2. Step 3 is drafted for review; no runtime changes authorized yet.
