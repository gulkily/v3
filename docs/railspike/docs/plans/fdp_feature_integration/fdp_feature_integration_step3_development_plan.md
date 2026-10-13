> **Feature plan:** [Step 1](./fdp_feature_integration_step1_solution_assessment.md) · [Step 2](./fdp_feature_integration_step2_feature_description.md) · [Step 3](./fdp_feature_integration_step3_development_plan.md) · [Step 4](./fdp_feature_integration_step4_implementation_summary.md)

# Plan organization — development plan

## Completion Contract
- Documentation-only, for Railspike users and agents: approval → branch → feature-folder organization → bounded planning commit → summary and verified stages, including skipped Step 1 and existing folders.
- Allowed scope: `RAILSPIKE.md`, `docs/workflow/`, `README.md`, `docs/failure-modes.md`, explanatory copy in `index.html` and `HN_POST_DRAFT.md`, `BACKLOG.md` for debug follow-up, and this cycle's artifacts/index in `docs/plans/`. No runtime, scripts, CI, deployment, styling, generated artifacts, or debug implementation.
- Handoff requires consistent rules, valid navigation, reviewed scenarios, and stage evidence. Stop on collisions or substantive plan changes; correct references within scope or return for approval. Document checks establish consistency, not guaranteed agent compliance. Runtime/deployment checks are not applicable; review rendered copy if changed.
- Bootstrap: this cycle starts under the existing rules with a branch and approved-plans-only commit. Stage 1 introduces the new rule and groups this cycle's artifacts when its summary is created; do not retroactively amend the planning commit. Later workflows use the new organization timing.

## Key Risks
- **High risk: conflicting destinations or lost references.** Inspect root/existing-folder scenarios before moves; preserve filenames/content, repair links, and stop rather than overwrite.
- **Commit scope drift.** Compare every planning-commit statement before editing; use one canonical allowance, retaining approval and stage gates. Bootstrap must not silently apply unimplemented rules.
- **Debug scope leakage.** Check source differences against Step 2; log/state exceptions remain a separate cycle.

## Stage 1
- Goal: Deliver a coherent organization rule through setup, recovery, and README guidance (≤1 hour).
- Dependencies: Approved Step 3; new feature branch; initial commit containing only approved Steps 1–3.
- Expected changes: Extend the canonical organization and planning-commit contract; align affected step guidance, README folder/commit descriptions, and failure recovery. Cover skipped Step 1, existing folders, necessary reference/index repairs, exclusions, and summary creation after the planning commit. Group this cycle's artifacts, add its index entry, and create the summary with a stage checklist.
- Verification approach: Review root-level, pre-grouped, skipped-Step-1, missing-index, collision, and substantive-revision scenarios. Check local links, approved-content preservation, allowed paths, whitespace, and agreement of commit rules. Record results and checklist completion in the stage commit.
- Risks or open questions: Moves can break references or widen the commit; inspect destinations and content diffs first, keep the bootstrap explicit, and stop on ambiguity.
- Canonical components/API contracts touched: `RAILSPIKE.md` planning contract; existing workflow handoffs; README and recovery guide; plan navigation/index. No UI/API additions.

## Stage 2
- Goal: Align public explanations and complete the documentation handoff (≤1 hour).
- Dependencies: Stage 1 verified, summarized, and committed.
- Expected changes: Update relevant website/HN copy to match organization timing; record debug mode as a separate backlog cycle. Complete the summary and stage checklist with evidence and remaining follow-up.
- Verification approach: Review changed copy and internal anchors; run local Markdown links, existing Git workflow fixture, whitespace, and allowed-scope checks. Search active guidance for obsolete artifact-count and planning-commit restrictions; confirm planning/stage commit contents and summary cadence.
- Risks or open questions: Public copy may contradict the canonical rule; compare it directly and avoid unrelated rewrites. Recheck only affected layout if copy length changes.
- Canonical components/API contracts touched: Existing website/HN explanations, backlog, and implementation summary; no new interfaces.
