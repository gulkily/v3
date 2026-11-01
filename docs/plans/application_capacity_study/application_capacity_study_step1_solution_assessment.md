> **Feature plan:** [Step 1](./application_capacity_study_step1_solution_assessment.md) · [Step 2](./application_capacity_study_step2_feature_description.md) · [Step 3](./application_capacity_study_step3_development_plan.md) · [Step 4](./application_capacity_study_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/application_capacity_study; [P2 ledger](.././effort_estimation_trial.md#cycle-v3application_capacity_study)
Extended estimates: enabled

## Original Query

Choose Option A, write another plan for expanding to Option C.

## Understood Intent

Keep the [QDB memory audit](.././qdb_memory_audit/qdb_memory_audit_step1_solution_assessment.md) at selected Option A and separately plan its Option C: an application-wide capacity study. This is a request for a plan, not approval to run production load, change settings or implement optimizations.

Problem: Current source findings and isolated PHP failures do not establish sustainable per-operation or concurrent-host capacity.

Concepts touched: Public reading, derived-model rebuild, static/offline publication and resource measurement; inventory entries proposed if execution proceeds. Existing [content snapshot export](../../concepts/content_snapshot_export.md), [instance import](../../concepts/instance_content_import.md) and [Fastmod scoring](../../concepts/fastmod_scoring.md) used unchanged.

## Option A — Single integrated measurement campaign

**Estimate: 0.75**

Exercise a documented matrix across major request/command families and publish one capacity report using isolated fixtures and existing tools.

- Shortest route to broad evidence; workload setup and unexplained interactions can make failures harder to attribute. Main uncertainty is representative data and deployment behavior.

## Option B — Staged measurement by workload family

**Estimate: 0.90**

Use a shared reproducible measurement method, establish isolated per-family costs, then test controlled overlap and lifecycle behavior before consolidating the capacity report.

- Recommended: individual family reports are useful vertical slices and clarify where shared-host headroom goes.
- More harness/fixture and recovery validation; actual PHP 8.3/FastCGI and host constraints may remain unverified without access.

## Proposed scope and recommendation

Choose B. Cover public QDB/shared reading and search; authenticated reads/writes; rebuild/static/offline jobs; activity/Git evidence; imports/exports/images; scoring and queue maintenance. Give each major entry-point family a tested, inapplicable or deferred status with reasons. Reuse the [existing findings and probes](.././qdb_memory_audit/qdb_memory_audit_findings.txt), preserving their synthetic provenance.

Evaluate proposed public 16 MiB typical/32 MiB ceiling and maintenance 48/64 MiB budgets. Compare fixed-result workloads at 1000/10000/40000 records; vary body size, reactions, history, pinned content and concurrency separately. Include cold/warm repeated requests, long-lived worker behavior, one maximum-size valid record, large thread/commit and interrupted-job recovery. Track PHP allocations, process/child RSS, time, I/O and records loaded/used separately; PHP limits do not cap aggregate host memory.

Deliver reproducible fixtures/commands, per-family and concurrent measurements, semantic parity checks, a documented safe operating envelope with uncertainty, and ranked remediation slices. Local runs must never target production; live measurements require a separately agreed workload and environment. No new dependencies, permanent monitoring, content limits, server-setting changes or fixes are included. Unavailable host evidence stays an explicit gap.

This expands the memory audit, not the separate [load-before-selection audit](.././load_before_selection_audit/load_before_selection_audit_step1_solution_assessment.md), whose discovery scope and option selection are unchanged. Step 2 requires Approved Step 1 for this plan.

## Study checkpoint

Intake and Step 1: 2026-10-30 UTC; v1/B; baseline cfb8364f6db03cc09e27cd26afb93c109c723a08; R1/P2. Parent's Option C estimate 0.90 retained as inherited; new whole-cycle projection 0.90 reaffirmed, alternative A 0.75, non-additive. Codex single exposed rater with prior audit/probe exposure; frozen R1 verification carried from this session. Scope includes planning, local harness/fixtures, measurements, recovery checks and reporting, not remediation or guaranteed host access. No measurement campaign, independent rating, measured labor or accepted outcome claimed. Reused findings are not new discoveries.

*— Codex · 2026-10-30 UTC*

## Approval — 2026-10-31 UTC

User: “approved step 1”, following the explanation of recommended Option B. Step 1 v1/B approved; proceed to Step 2 requirements. Measurement execution, production load and remediation remain outside this approval.

*— Codex · 2026-10-31 UTC*
