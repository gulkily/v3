> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement)
Extended estimates: enabled
Status: User selected rename-disabled public-instance direction; Stage 1 reconciliation pending; Stages 2–8 not started

**Cycle estimate: 1.00**

## Entry and approval

2026-11-01 UTC: User said “approved step 3.” Feature branch feature/key-retirement; planning commit 15373adbb precedes this summary and implementation. Scope v2/A, original baseline 4b14e622f3cca6cb8f041145fd1df954bacc74e4; R1/P2; Codex single exposed rater. Step 3 whole-cycle 1.00 reaffirmed at entry, historical 0.95 retained. Frozen references verified this session. No measured labor or acceptance increment.

Original stage-work estimates from the approved plan: S1 authority/history gate 0.15; S2 records/replay 0.16; S3 active enforcement 0.17; S4 message recovery 0.20; S5 signed API 0.15; S6 shared journeys 0.16; S7 restore 0.15; S8 release evidence 0.12. Values are independent, non-additive judgments, not elapsed hours. Retrospectives pending; no stage complete. Preserve unrelated drafts and ledger history; no merge/deployment authorization implied.

## Stage 1 - Authority and historical eligibility boundaries

**Original estimate: 0.15**

- Changes: Added a real-signature characterization showing that an ordinary approved key can rename into another approved account's current name while preserving its original, different approval context. [Findings](stage1_authority_findings.txt) record source paths, consequences and the concrete policy decision.
- Verification: Approval acceptance/history synchronization/session baseline 33/33 passed; new signed rename/approval/rebuild characterization and existing approval-history test 2/2 passed. Whitespace checked. No retirement writer or runtime change.
- Notes: Same-account authority cannot reuse current username classification; legacy grants can lack accepted account context. Current-name key counts also cannot prove that a replacement belongs to the target account. Stage 1 remains incomplete pending the policy decision; no dependent stage has begun. Actual fixture roots/signing homes were cleaned up.
- Estimate details: Original 0.15 retained; retrospective unavailable because the gate is unresolved. Whole-cycle 1.00 remains the approved v2/A projection, not a completion assessment; revised projection pending the authority/legacy-coverage decision. No measured labor.

*— Codex · 2026-11-01 UTC*


## Required Step 2 decision

The approved Stage 1 gate found a concrete gap in accepted account authority. [Proposed clarification](key_retirement_step2_feature_description.md#proposed-authority-clarification--stage-1-gate) narrows member self-service to proven accepted membership and routes missing/conflicting legacy evidence to operator assistance; the broader alternative is a separately scoped durable account-association flow. Neither is silently assumed approved. Preserve existing Step 1–3 approvals as v2/A history; new scope/coverage requires review before continuing.

*— Codex · 2026-11-01 UTC*


## User direction and rename warning — 2026-11-01

Direction: “let's do that for now, we'll just keep the rename feature disabled on public instances for now”; followed by “please add a warning to the feature flag for rename.” This supersedes pursuing the broader account-association redesign now. Keep the prior reproduction and proposals as history. The flag already defaults off; instance/environment overrides and historical/imported renames must not be assumed absent. No public-instance configuration was changed or verified remotely.

Immediate authorized change: Add the warning to the existing always-visible Username changes flag description: renamed keys retain approval and can appear under another account's username; turning the flag off does not undo saved names or approvals. This is a separately requested copy change, not completion of Stage 1 or implementation of retirement. Continue reconciliation of the approved retirement plan around the selected disabled-rename boundary; do not treat the warning alone as an authorization check.

*— Codex · 2026-11-01 UTC*
