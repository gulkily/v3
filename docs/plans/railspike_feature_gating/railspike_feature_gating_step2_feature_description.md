> **Feature plan:** [Step 1](./railspike_feature_gating_step1_solution_assessment.md) · [Step 2](./railspike_feature_gating_step2_feature_description.md) · [Step 3](./railspike_feature_gating_step3_development_plan.md) · [Step 4](./railspike_feature_gating_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/railspike_feature_gating — [ledger](../effort_estimation_trial.md#cycle-v3railspike_feature_gating), standing P2 enrollment
Extended estimates: enabled
Status: Step 2 approved 2026-10-29; implementation unapproved

**Cycle estimate: 0.50**

## Problem and user stories

Production browsing exposes repeated Railspike requests on unrelated threads, with no feature switch. Polling belongs to an open Railspike thread.

- As a reader, I want ordinary browsing to make no workflow requests so that optional development activity stays out of it.
- As an operator, I want live status on an open Railspike thread and a feature switch so that I can use or suspend development without losing its records.

## Core requirements

- A default-off site flag controls this browser workflow through existing Feature Flags controls and precedence. Disabled UI is absent, workflow API calls return an actionable disabled response, and no new assessment starts. Stored workflows remain intact; already-started assessments may finish and retain their results. Re-enabling permits queued work to resume without duplicate execution.
- Poll only for an approved operator viewing an eligible Railspike thread, including a direct post view in that context. Use existing #railspike tag/label eligibility; do not require a started, queued or running workflow. Ordinary threads and listings make zero workflow requests. Preserve existing eligibility limits for starting actions.
- Polling pauses while hidden and stops on navigation away, known disablement or lost authorization; no overlapping or accumulating timers. A stale open page may receive one disabled/authorization response before stopping. Returning to a visible eligible page resumes only while permitted; after disablement or authorization failure, re-enable/sign in and reload to recover.
- Keep static server HTML shared, decide viewer-specific controls before first paint, and avoid late layout shifts. Refreshes preserve typed answers and open controls. A transient request failure must not cause an unbounded retry loop; explicit refresh/reload remains available.
- Preserve signed actions, approval boundaries, existing API paths and workflow history. Feature disablement cannot discard drafts or records; retain drafts and show necessary recovery beside an interrupted action.

## Delivery and completion boundary

Work type: Application change, tests and workflow/API/flag documentation, plus this cycle's plans, debug log and study ledger. Reuse existing components; no new dependencies, navigation destinations or planned schema changes. Private-message synchronization, legacy localhost handoffs and broader workflow-reading redesign are excluded.

Shared surfaces: Extend Feature Flags registry/evaluation and existing controls; thread and direct-post workflow mounts and client; workflow GET summaries and signed POST actions; assessment worker and queued-work recovery. Reuse existing static-page/client availability patterns. Activity listings remain free of workflow integration.

Flow: Operator enables flag → opens a Railspike thread → starts/reviews an assessment with live status → leaves the thread or disables the feature → re-enables and reloads to resume retained work.

## Risks and early validation

- Stale pages or identity state could leak requests or flash controls. Before implementation planning, trace flag delivery, thread eligibility and static rendering; require current-page and stale-page acceptance cases.
- Disabling during queued/running work could lose progress or duplicate assessments. Trace worker claim/reconciliation before fixing the stage plan; enforce the finish-existing/pause-new contract above.
- Eligibility changes could hide existing records. Check tag removal and direct-post entry early; preserve stored history and provide re-tag/reload recovery without expanding polling to ordinary threads.

## Success criteria and production evidence

Browser network checks across multiple polling intervals show zero workflow requests on ordinary threads, listings and freshly loaded disabled pages; visible eligible operator pages poll even in waiting/completed states. Hidden tabs stop requests, authorization/disabled responses stop retries, and enable/start/result/disable/re-enable journeys retain work. Verify phone/desktop, relevant themes, first paint and signed recovery.

The reported environment is production browsing: after authorized deployment, repeat the same ordinary-thread and #railspike-thread visits in production, toggling the flag and verifying network traffic and retained results. Re-enable/sign in and reload is the unaided recovery path. Local checks cannot establish production success; deployment and live verification remain separately authorized release conditions, reported as pending until performed.

## Study checkpoint and approval

2026-10-29T18:36:23Z: v2/A clarified; baseline cdb7a4e75a8b0e9065140656ec79dba5cf12e229; R1/P2; Codex single exposed rater. Whole-cycle 0.50 reaffirmed; verified frozen references carried forward. Original 0.60 and revised 0.50 snapshots remain in Step 1; no measured labor or accepted outcome. Await Approved Step 2; documents uncommitted, implementation unapproved.

*— Codex · 2026-10-29 UTC*

Approval: User replied “Approved Step 2”; requirements above are approved for development planning. Historical checkpoint wording is retained.
