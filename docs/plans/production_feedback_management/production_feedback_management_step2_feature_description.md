> **Feature plan:** [Step 1](./production_feedback_management_step1_solution_assessment.md) · [Step 2](./production_feedback_management_step2_feature_description.md) · [Step 3](./production_feedback_management_step3_development_plan.md) · [Step 4](./production_feedback_management_step4_implementation_summary.md)

Debug mode: enabled.  
Effort study: `railspike-effort` / `v3/production_feedback_management`; standing [P2 enrollment](../effort_estimation_trial.md#continuing-enrollment--2026-10-21-protocol-p2).

## Problem and user stories

Production feedback is split across dated inventories and triage documents, making duplicates, next actions and current resolution hard to follow as more reports arrive.

- As a maintainer, I want one request register so that I can add reports, choose work and follow its outcome.
- As a contributor, I want reporting posts, completion checks and evidence linked to each request so that I can act without losing context or overstating resolution.

## Core requirements

- Give each request a permanent ID; preserve every October 21 feature/bug alias, reporting-post link, request nuance and inherited estimate with its provenance. Keep distinct outcomes separate even when they share a source post.
- Maintain current status in the register; batch inventories and triage retain historical context and point there. Match repeated reports to existing requests only on sufficient evidence; preserve aliases and relationships when merging or splitting, and flag uncertain matches for review.
- Separate workflow state (untriaged, ready, planned, in progress, blocked, closed) from delivery evidence (unknown/pending, locally verified, deployed, production accepted), with dated evidence and limits. No closure based solely on migration or adjacent work; require completion evidence or an explicit non-delivery disposition. Reopen when contradicted.
- Record next action, completion checks and existing triage/plan links; distinguish proposed work from approved work. Keep a short reasoned next-work list, reusing the existing benefit/effort/uncertainty triage guidance.
- Document intake, duplicate review, progress updates, closure and correction; preserve partial-import coverage and clearly identify older inventories as not yet reconciled. Source-comment replies require separate authorization.

## Delivery scope and shared surfaces

**Documentation-only**, for repository maintainers/contributors; application/runtime changes are not permitted. Allowed implementation documents: new `docs/plans/production_feedback_register.md` and `docs/reference/production_feedback_management.md`; existing `docs/plans/zenmemes_meta_feedback_2026-10-21_summary.md`, `docs/plans/zenmemes_meta_feedback_2026-10-21_mini_triage.md`, `docs/reference/minimal_feedback_triage.md`, and `docs/plans/README.md`; this cycle's planning/debug/implementation artifacts and `docs/plans/effort_estimation_trial.md` bookkeeping. Cycle-artifact moves/reference repairs follow Railspike organization rules.

Reuse both October 21 inventories and minimal triage guidance rather than duplicating live status. Older October 18/imported inventories remain linked, unchanged and outside migration. No existing application UI/API is changed; no new dependencies, automation, external tracker, production writes or `todo.txt` edits.

## Flow and completion boundary

1. Enter through the planning index; open the register and inspect next actions.
2. Find a matching request or add one with source, alias and explicit uncertainty; use the guide for triage and planning handoff.
3. Update that request as work/evidence changes; verify closure against its completion checks.
4. Correct mistakes through reviewable edits/Git history; preserve retired IDs and source lineage, then recheck affected aliases, links and counts.

Complete when all **16 October 21 actionable entries** have traceable register mappings, all source links/estimates and known local-verification limits are retained, historical status tables direct readers to the register, and maintainers can follow intake through evidence-backed closure from the documents. Document checks cover coverage, unique IDs, valid local links, status consistency and correction examples; no new production audit is claimed. Handoff includes verification results and older-batch reconciliation as follow-up.

## Risks

- **False closure:** stale or differing triage status could imply production resolution. Compare existing evidence before assigning state; retain conflicts/pending checks explicitly.
- **Lost identity/context:** reused numbers or shared source posts could merge different requests. Check all 16 aliases and source mappings before migration acceptance; retain separate outcomes and review uncertain overlap.
- **Continued drift:** several editable status tables could diverge. Identify authoritative fields before editing; make existing inventories historical with clear register links.

## Approval and study checkpoint

User approved Step 1 Option B. Step 2 handoff, 2026-10-23T09:45:50Z: **0.35 projected total reaffirmed**, scope v1, R1/P2, Codex single exposed rater. Starting revision `1a358bc9be3cdb725277c792625a678e7bc85436`; outcome and Git correction/recovery remain within Step 1 scope. Reviewed October 21 inventory and prior planning; no new implementation inspection or independent rating. R1 report/diff and historical feedback hashes reverified using the sources recorded in Step 1. Earlier snapshots preserved; no measured effort or accepted outcome. Await Approved Step 2; no implementation authorized.
