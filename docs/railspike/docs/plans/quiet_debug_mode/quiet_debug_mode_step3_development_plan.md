> **Feature plan:** [Step 1](./quiet_debug_mode_step1_solution_assessment.md) · [Step 2](./quiet_debug_mode_step2_feature_description.md) · [Step 3](./quiet_debug_mode_step3_development_plan.md) · [Step 4](./quiet_debug_mode_step4_implementation_summary.md)

Debug mode: enabled

## Completion Contract
- Documentation-only outcome for users and agents: complete diagnostics in Markdown, one short link per completed-step handoff, no routine inline findings or diagnostic narration. Preserve ordinary communication and instruction-reprint rules.
- Allowed changes: debug-related wording in `RAILSPIKE.md`, `docs/workflow/*.md`, `README.md`, and `docs/failure-modes.md`; this feature's plans, summary, debug log, organization under `docs/plans/quiet_debug_mode/`, and its `docs/plans/README.md` entry. Preserve historical approved content. No application/runtime, tests, CI, deployment, generated artifacts, or unrelated files.
- Complete when contract, four handoffs, examples, and recovery guidance agree and scenario/integrity/link/scope checks pass. Briefly disclose failed saves or clarify unresolved state; no fallback chat dumps. Repair contradictions before handoff; scope expansion requires approval. Evidence establishes document consistency, not agent adherence. Runtime/UI/deployment/migration/release checks are inapplicable.
- After Step 3 approval: branch, organize plans/log, repair links/index, commit approved plans plus organization allowances, then create the enabled summary. Verify, summarize, and commit each stage before the next; commit final Step 4 diagnostics using the log-only handoff allowance.

## Key Risks
- **Conflicting handoffs:** Early review found summary directives in the canonical contract, all four handoffs, and README. They could restore chat noise; align every active directive and example, leaving historical plans intact.
- **Over-suppression:** Failure and state-recovery guidance needs visible exceptions. Early review confirms these paths exist; retain brief exceptions and explicit user-requested findings without weakening ordinary approvals.
- **High risk: incomplete/lost history:** Short handoffs could be mistaken for shorter logs. Early comparison confirms timestamp, group, evidence, revision, and commit obligations; retain them and verify append-only history through moves. Unresolved material risks block dependent work.

## Stage 1
- Goal: Apply file-first reporting across the canonical contract and every completed-step handoff (≤1 hour).
- Dependencies: Approved Step 3; feature branch; organized plans/log and initial planning commit.
- Expected changes: Keep full findings/groups in the log; replace chat summaries with one link before approval. Define exceptions, unchanged-report links, disabled-mode silence, and no collection narration. Preserve state recovery, log lifecycle, phase/stage silence, and approvals.
- Verification approach: Trace enabled Steps 1–4, empty groups, resumption, revisions, unchanged reports, disable/re-enable, explicit chat requests, failed saves, and unresolved state. Compare all handoffs to the canonical contract; check local references, whitespace, allowed paths, and retained history. Record evidence in the summary and commit.
- Risks or open questions: A stale summary directive or broad exception could defeat the limit; inspect every active handoff before edits and resolve conflicts before Stage 2.
- Canonical components/API contracts touched: `RAILSPIKE.md`, completed-step instructions under `docs/workflow/`, and this feature's summary; no UI/API changes.

## Stage 2
- Goal: Align user guidance and complete the documented end-to-end workflow (≤1 hour).
- Dependencies: Stage 1 verified, summarized, and committed.
- Expected changes: Update README with separate illustrative log content and a one-line conversational link; align failure guidance with brief exceptions and no fallback diagnostic dump. Complete the summary/checklist.
- Verification approach: Review guidance scenarios for one routine link, zero inline findings, complete logs, and disabled-mode silence. Check affected links, whitespace, scope, planning/stage commits, and log preservation; summarize and commit before the final log-only commit.
- Risks or open questions: Examples could reintroduce summaries or imply proven agent behavior; label examples illustrative and keep evidence claims limited to checks performed.
- Canonical components/API contracts touched: `README.md`, `docs/failure-modes.md`, and this feature's summary/log; reuse the existing debug contract.
