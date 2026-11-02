> **Feature plan:** [Step 1](key_retirement_step1_solution_assessment.md) · [Step 2](key_retirement_step2_feature_description.md) · [Step 3](key_retirement_step3_development_plan.md) · [Step 4](key_retirement_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/key_retirement — [ledger](../effort_estimation_trial.md#cycle-v3key_retirement)
Extended estimates: enabled
Status: Step 3 v3/A approved; Stage 1 contract complete; Stage 2 next

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


## Planning reconciliation — 2026-11-01

User asked whether Step 2 had been revised; only appended direction existed then. Main Step 2 and Step 3 requirements are now reconciled with the disabled-rename approach. User explicitly approved Step 2 v3/A. Revised Step 3 awaits approval before dependent implementation. Member actor/target must be unrenamed approved keys sharing unchanged bootstrap names with the rename flag off; operators retain their path; replacement counting excludes verified historical/imported/renamed-back keys. Keep all earlier characterization and scope history. No retirement runtime changes.

*— Codex · 2026-11-01 UTC*


## Revised entry and Stage 1 completion — 2026-11-01

Approval: User said “approved step 3” for v3/A. Revised planning commit 3aab4e975 precedes resumed implementation. Whole-cycle 1.00 reaffirmed; original estimates/history preserved, S1 revised whole-stage 0.20 retained; same original baseline/R1/P2 and exposed single rater.

**Stage 1 retrospective stage work: 0.20**

- Changes: Resolved authority via the explicitly approved flag-off/unchanged-bootstrap/no-verified-rename boundary, with independent operator authority and unrenamed replacement protection. [Record/acceptance contract](../../specs/key_retirement_record_v1.md) defines exact bytes, trusted acceptance/replay, retained private transfers and serialization. No broad account-association migration. Protected-path inventory remains the approved plan's auth, sessions, direct/prepared writes, approvals/invitations, operators and messaging consumers; each is verified in its implementing stage.
- Verification: Existing 35 focused gate/baseline checks from 0976ab96a establish current primitives and the rename failure case; source inspection confirms retained approval receipts, private transfer ownership, normal write serialization and signature verification. Contract paths and whitespace checked. Future retirement behavior is not claimed tested before implementation.
- Notes: Stage 1 policy gate resolved by user's revised approval. Existing trusted private transfers are retained rather than assigned invented historical dates. S2–S8 acceptance remains mandatory before enabling the writer. No dependencies, runtime retirement route, production change or measured labor.

*— Codex · 2026-11-01 UTC*
