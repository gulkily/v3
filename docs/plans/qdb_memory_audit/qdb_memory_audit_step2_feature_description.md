> **Feature plan:** [Step 1](./qdb_memory_audit_step1_solution_assessment.md) · [Step 2](./qdb_memory_audit_step2_feature_description.md) · [Step 3](./qdb_memory_audit_step3_development_plan.md) · Step 4 (not started)

Debug mode: enabled
Effort study: railspike-effort / v3/qdb_memory_audit; [P2 ledger](../effort_estimation_trial.md#cycle-v3qdb_memory_audit)
Extended estimates: enabled

**Cycle estimate: 0.40**

## Problem and users

QDB's rebuild and publication paths can allocate entire collections before selecting results. Operators need a trustworthy inventory of remaining risks and bounded follow-up scopes; this source audit does not promise that the application meets a memory budget.

- As an operator, I want a reported failure mapped to its allocation pattern so that I can identify the relevant follow-up.
- As a maintainer, I want ranked, evidence-backed findings so that I can approve focused fixes without duplicating related audits.

## Core requirements

- Trace rebuild/static publication and shared readers, screening adjacent maintenance/import paths; retain stable M01–M10 IDs and inspect relevant callers, not just isolated allocation syntax.
- Each finding states source location, trigger/scale, affected entry points, evidence strength, impact/priority and smallest safe remediation boundary. Distinguish necessary complete traversal, bounded collections and avoidable materialization.
- Preserve existing synthetic results and their conditions; separate source-only risks, reproduced failures and unverified host behavior. Keep 128 MiB incident evidence and proposed 32/64 MiB targets distinct.
- Link shared findings with the selection audit and capacity expansion. No new benchmark campaign or remediation is included; preserve content, signatures, ordering, visibility and existing limits.

## Delivery and completion boundary

Work type: Documentation-only internal maintenance. Allowed edits: this cycle's text/Markdown artifacts in docs/plans/, its entries in effort_estimation_trial.md and the planning index, and concise read-model/static-publication concept documentation in docs/concepts/. Retain the existing probe as historical evidence; do not extend executable files, application/runtime code, CI or deployment configuration.

Deliver the reviewed findings inventory, coverage/exclusions and independently approvable follow-up scopes for operators and maintainers. Completion requires every retained finding to have the required fields and valid references; correct stale claims or mark evidence unavailable rather than repeating unsupported measurements. No new runtime verification is required for documentation-only corrections.

Known environment: The reported QDB PHP 8.3 shared host fails during ./v3 build-static at 128 MiB. The report must map phase/error to finding, state whether remediation is delivered or proposed, and identify retrying the original command after an approved fix as the verification path. Local PHP 8.1 fixtures do not establish host recovery; production success remains unverified.

## Shared components and risks

Inventory, reused unchanged: rebuild/delete-record/build-static; QDB welcome/latest/top/1337/random/search/detail and shared board readers; offline snapshot; Activity/Git evidence; high-traffic refresh; Fastmod audit; canonical parsing/import. No new UI/API component.

Concepts touched: Derived read-model rebuild and static publication, proposed inventory entries; existing [reaction records](../../concepts/reaction_record.md), used unchanged.

Risks: Syntax-only false positives—trace consumers before ranking. Synthetic/host mismatch—label provenance at each claim. Overlapping audits—share finding IDs and link ownership before defining follow-ups.

## Flow and success criteria

1. Reconcile the existing findings with source and caller paths.
2. Review priority, evidence, legitimate exceptions and follow-up ownership.
3. Hand off the inventory with explicit unresolved questions and links to separate plans.

Success: All ten existing findings accounted for without silent deletion; every retained finding has the required fields; all source/plan links resolve; overlapping causes are not counted twice; no application or configuration changes. Diagnostic completion and outstanding fixes/host acceptance remain distinct.

## Approval and study checkpoint

Step 1 Option A explicitly approved, clarified by the user. Step 2 handoff: 2026-10-30 UTC, v2/A; original baseline cfb8364f6db03cc09e27cd26afb93c109c723a08, R1/P2, debug/extended mode and single exposed Codex rater unchanged. Total 0.40 reaffirmed, including previously performed probes; original A 0.20 and v1/B 0.40 snapshots preserved. No measured labor, accepted outcome or implementation approval. Await Approved Step 2; the two other assessments remain at Step 1.

Approval update, 2026-10-30: User approved Step 2. Proceed to the documentation-only Step 3 plan, with no scope additions or runtime work. The two other assessments remain at Step 1.

*— Codex · 2026-10-30 UTC*
