> **Feature plan:** [Step 1](./installation_process_improvements_step1_solution_assessment.md) · [Step 2](./installation_process_improvements_step2_feature_description.md) · [Step 3](./installation_process_improvements_step3_development_plan.md) · [Step 4](./installation_process_improvements_step4_implementation_summary.md)

Debug mode: disabled

## Completion Contract

- Documentation-only: `README.md`, `WELCOME.md`, this feature's plans/checklist, and its `docs/plans/README.md` entry. Deliver reviewed-copy installation, recovery, manual updates, explicit submodules, and first-task planning.
- Require all 13 checklist dispositions plus evidence/link/scope checks; correct contradictions before handoff. Scenario review cannot guarantee agent compliance. Runtime/UI/deployment/migration checks are inapplicable; no application, script, or test changes.
- After approval: branch, organize approved plans/references and index, then make the planning-only commit. Include the checklist with Stage 1, not that initial commit. Create the summary afterward; verify, summarize, and commit each stage before proceeding. Three one-hour stages; minimum four commits.

## Key Risks

- **High risk:** Overwrites/partial copies threaten user work. Pre-draft branch review identified extraction/destination hazards; require bounded writes, occupied-path refusal, completeness comparison, and scoped cleanup. Validate before copy-flow completion.
- **High risk:** Updates can lose local edits. Lifecycle review requires durable source/revision records and baseline comparison; stop if the baseline is unavailable and preserve old contents until verification.
- **High risk:** Default planning could imply implementation approval. Welcome review separates first-task entry, persistent-guidance consent, and step approvals; preserve opt-out/small-task exceptions. Unresolved risks block dependents.

## Stage 1

- Goal: Complete default installation and recovery guidance.
- Dependencies: Planning commit; extraction/destination risk resolution.
- Expected changes: Rewrite README for inspection and copying the same snapshot, preserving structure/license without Git metadata; record provenance, verify contents, and report results. Keep submodules explicit. Organize checklist and repair evidence links; leave the supplied log unchanged.
- Verification approach: Review dirty/staged work, nested repositories, suspicious content, redirected/occupied paths, revision mismatch, and interrupted copying; record checklist evidence.
- Risks or open questions: Compare before/after state early; preserve unrelated files/staging and never stash for default installation.
- Canonical components/API contracts touched: README installation guidance, checklist, summary; no APIs.

## Stage 2

- Goal: Complete reuse/update guidance for both installation types.
- Dependencies: Verified, committed Stage 1; provenance requirements.
- Expected changes: Document manual copy updates with baseline/local-edit review and recovery; separate copied-project reuse from submodule initialization/updates. Preserve the marked submodule update block.
- Verification approach: Review unchanged/edited copies, missing provenance, partial replacement, and existing submodules; run the existing Git workflow fixture and record checklist evidence.
- Risks or open questions: Review replacement boundaries early; preserve prior contents and refuse automatic conversion.
- Canonical components/API contracts touched: README reuse/update guidance, checklist, summary; no APIs.

## Stage 3

- Goal: Connect verified installation to first-task planning.
- Dependencies: Verified, committed Stage 2.
- Expected changes: Align WELCOME and README on default workflow use, opt-out, small tasks, separate persistent-guidance consent, and unchanged approvals.
- Verification approach: Review substantial/small tasks, opt-out, unanswered/accepted/declined guidance, and conflicts. Complete all 13 checklist dispositions; verify changed-document links, whitespace, evidence, and scope.
- Risks or open questions: Review welcome branches early; remove language conflating installation with implementation approval.
- Canonical components/API contracts touched: WELCOME onboarding/guidance, README quick start, checklist, summary; no APIs.
