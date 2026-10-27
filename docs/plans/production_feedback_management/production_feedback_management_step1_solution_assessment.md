> **Feature plan:** [Step 1](./production_feedback_management_step1_solution_assessment.md) · [Step 2](./production_feedback_management_step2_feature_description.md) · [Step 3](./production_feedback_management_step3_development_plan.md) · [Step 4](./production_feedback_management_step4_implementation_summary.md)

Debug mode: enabled (project marker).  
Effort study: `railspike-effort` / `v3/production_feedback_management`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Original Query

Please look for our summary documents that combine user feedback from prod. I want a better way to manage these requests, since we'll be adding more of them.

## Understood Intent

Make incoming production feedback easy to add, deduplicate, prioritize and follow through to verified resolution without repeatedly reconciling growing summaries.

## Problem and evidence

Requests have useful provenance but no single cross-batch record of current status: [October 18 inventory](../zenmemes_meta_feedback_2026-10-18_summary.md) covers 24 threads/129 posts; [October 21 inventory](../zenmemes_meta_feedback_2026-10-21_summary.md) adds 16 items; its [mini-triage](../zenmemes_meta_feedback_2026-10-21_mini_triage.md) repeats workflow and completion checks; the [older imported summary](../imported_feature_requests_summary_v1.md) groups requests thematically. Reused item numbers require a batch/category prefix. Imports are partial and local verification does not establish production resolution. Existing [minimal triage guidance](../../reference/minimal_feedback_triage.md) can be reused; [historical intake assessment](../backlog/feature_request_fdp_intake_step1_solution_assessment.md) addresses forum-to-planning handoff, a related but distinct problem.

## Option A — Keep batch summaries with a shared index

Add one index linking each dated batch and standardize future summary fields.

- Pros: smallest disruption; preserves readable historical context.
- Cons: cross-batch duplicates, current status and next actions still require manual reconciliation.

## Option B — Canonical request register in the repository

Keep batch summaries as source inventories; maintain one compact register of stable request IDs and current state, with individual request notes only when needed. Each request links all reporting posts, its original batch/category/item aliases, completion checks, next action and relevant triage/implementation evidence. Repeated reports attach to the existing request; related but different outcomes remain linked requests.

- Pros: one place to choose work and update status; reviewable history; no new service; preserves source lineage and existing plans.
- Cons: requires consistent updates; a large Markdown register may eventually need generated views.

## Option C — Move requests into an issue tracker

Manage each request as an issue with labels, relationships and saved views, retaining source and plan links.

- Pros: filtering, assignment and discussion suit a larger team/backlog.
- Cons: adds an external source of truth, access/setup decisions and migration/synchronization work. No particular service or integration is proposed yet.

## Recommendation

Choose **B**, initially as a documentation-only slice: migrate the October 21 batch end to end, establish the intake/update rules and link older inventories for later reconciliation. Preserve old identifiers and estimates; never treat a migration as a fresh resolution audit. Separate workflow state (untriaged, ready, planned, in progress, blocked, closed) from delivery evidence (local verification, deployment, production acceptance); record closure reasons and reopen on contrary evidence. Keep uncertain duplicates separate until reviewed. Rank a short next-work list by benefit, effort and uncertainty; estimates do not authorize work. Source-comment replies remain explicit actions requiring authorization.

Success for the first slice: a new report can be added or matched, chosen for work, linked to its plan, and updated with evidence without editing several current-status tables. Older batches remain clearly marked as not yet reconciled. Existing source inventories retain their historical evidence; migrated entries point to the register for current status. No application UI, automation, external component, dependency change or `todo.txt` edit. Broader migration is follow-up scope.

## Effort snapshots

Intake and Step 1 handoff, 2026-10-23T09:36:24Z: **0.35 projected total**, scope v1, R1/P2, Codex single exposed rater; intake recorded after summary/workflow inspection, not a pristine prospective rating. Starting revision `1a358bc9be3cdb725277c792625a678e7bc85436`. Scope includes planning, first-batch migration, duplicate/alias review, status/evidence rules, link/inventory checks and documentation recovery through Git. Step 1 reaffirms 0.35; no implementation inspection or independent rating. R1 report/diff hashes verified at `/home/wsl/railspike-dev/docs/research/effort_estimation/`; exact frozen feedback verified from `4f6cfe2b436fae8d939b1349fe724a3879ee0a0e` against the ledger hash. No measured effort or accepted outcome. Implementation and subsequent steps unapproved; planning remains uncommitted.
