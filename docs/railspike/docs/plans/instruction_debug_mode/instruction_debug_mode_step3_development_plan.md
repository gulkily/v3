> **Feature plan:** [Step 1](./instruction_debug_mode_step1_solution_assessment.md) · [Step 2](./instruction_debug_mode_step2_feature_description.md) · [Step 3](./instruction_debug_mode_step3_development_plan.md) · [Step 4](./instruction_debug_mode_step4_implementation_summary.md)

# Instruction debug mode — development plan

## Completion Contract
- Documentation-only outcome for users and agents: explicit enablement → durable state/recovery → evidence-backed completed-step reports saved to an append-only log → organized, authorized commits and final handoff; disabling preserves history.
- Allowed: `RAILSPIKE.md`, `docs/workflow/`, `README.md`, `docs/failure-modes.md`, `BACKLOG.md` for this follow-up, and this cycle's artifacts/index in `docs/plans/`. No runtime, scripts, CI, deployment, generated assets, website, telemetry, legacy compatibility, or automatic instruction changes.
- Complete when one canonical contract, every step handoff, and usage/recovery guidance agree; links, file scope, and scenario reviews pass. Resolve ambiguous state or failed writes visibly; return for approval if repairs expand scope. Checks establish document consistency, not guaranteed agent adherence. Runtime/UI/deployment/migration/release checks are not applicable.
- After approval, branch, organize these plans, and commit them with only existing organization allowances before implementation. This request has not enabled debug mode: record disabled state in the implementation summary when introducing the contract; preserve approved planning content and create no diagnostic log unless subsequently enabled. Each stage commit includes its summary and checklist update.

## Key Risks
- **State loss or stale snapshots:** inspect enable/disable, inheritance, and resumed/conflicting-state cases before wiring handoffs; latest explicit requests win, unresolved state requires clarification.
- **Unsupported or duplicated findings:** inspect empty, inapplicable, revised, and unchanged reports early; require observable evidence and append-only corrections, never infer redundancy from non-use.
- **High risk: lost logs or widened commits:** review organization, failed writes, and final-handoff ordering before delivery; preserve history, admit only this feature's log, and never amend completed commits.

## Stage 1
- Goal: Deliver the complete debug contract through all four steps (≤1 hour).
- Dependencies: Approved Step 3, new branch, organized plans, and initial planning commit.
- Expected changes: Define durable state, recovery precedence, evidence categories, report cadence, append-only logging, write-failure disclosure, and log-specific commit allowances in `RAILSPIKE.md`. Connect canonical step entry/resumption and final handoffs, including summary inheritance, organization, and final log-only commit handling. Preserve approval gates and exclude phase/stage reports.
- Verification approach: Trace default-off, enable, inheritance, context loss, unresolved state, disable/re-enable, empty/inapplicable findings, revisions, unchanged output, write failure, folder moves, initial-commit exclusions, and final-log cases. Check every step's references/anchors, approved-content preservation, links, whitespace, and allowed paths; record results before committing.
- Risks or open questions: Recovery or log exceptions may contradict existing gates; compare all affected clauses against Step 2 before edits and resolve discrepancies within the approved contract.
- Canonical components/API contracts touched: `RAILSPIKE.md`, existing phase/step instructions, plan organization and commit rules, this cycle's summary; no new UI/API.

## Stage 2
- Goal: Make the feature discoverable and complete its recovery guidance and handoff (≤1 hour).
- Dependencies: Stage 1 verified, summarized, and committed.
- Expected changes: Add README enable/disable prompts and a clearly illustrative evidence-backed report; align README commit wording with the log allowance. Extend failure guidance for ambiguous state, failed logging, and duplicated reports. Close the backlog follow-up and finalize the summary/checklist.
- Verification approach: Compare examples and recovery text with the canonical contract; recheck initial/final commit boundaries and all four handoffs. Run local Markdown links, the existing Git workflow fixture, whitespace and scope checks; verify the planning commit and both stage commits contain only permitted files with summary updates.
- Risks or open questions: Examples may imply measured results or broader authority; label them illustrative and preserve explicit approvals and separate authorization for instruction changes.
- Canonical components/API contracts touched: README, failure guide, backlog, and this cycle's summary; existing checker scripts remain unchanged.
