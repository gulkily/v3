> **Feature plan:** [Step 1](approval_acceptance_text_step1_solution_assessment.md) · [Step 2](approval_acceptance_text_step2_feature_description.md) · [Step 3](approval_acceptance_text_step3_development_plan.md) · [Step 4](approval_acceptance_text_step4_implementation_summary.md)

Debug mode: enabled
Effort study: railspike-effort / v3/approval_acceptance_text — [ledger](../effort_estimation_trial.md)
Extended estimates: enabled

**Cycle estimate: 0.75**

## Execution entry

Approved Step 3 received. Branch: feature/approval-acceptance-text. Planning commit: aa9a7f7b. Scope v2/A; baseline d26a90f9b9bfa8eb67e72804a7c24707947ab7c0; R1/P2; Codex single exposed rater. 2026-10-29 UTC entry projection 0.75 reaffirmed before implementation. Original stage work S1–S6: 0.15/0.15/0.10/0.20/0.10/0.15, non-additive; S6 owns shared acceptance. Pristine intake missing; no measured labor. No production data mutation or deployment authorized. Dependency delta: zero planned.

*— Codex · 2026-10-29 UTC*

## Stage 1 - Readable contract and digest preservation

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Ordered named context headers, strict scalar/header validation, isolated legacy decoder, shared semantic validation, readable spec/example and touched concepts. Writers remain legacy until S2.
- Verification: `php tests/run.php ApprovalAcceptanceTest`: 3/3 pass, including ordered/reversed context, Unicode/escaping, digest mismatch, malformed text and duplicate legacy fields. Whitespace checked.
- Notes: No stage estimate revision; signed digest generation unchanged. Runtime browser/restore acceptance belongs to later stages.

*— Codex · 2026-10-29 UTC*

## Stage 2 - New writes and receipt resolution

**Original estimate: 0.15**
**Retrospective stage-work assessment: 0.15**

- Changes: Text-only new writes; shared validation of all existing receipts, conflict rejection, actual source paths in history/recovery, text source allowlist. Invalid legacy companions cannot be hidden by valid text.
- Verification: ApprovalAcceptance/History/Policy/InvitationIssuance 21/21; two actual Write API approval/source and signed-finalization checks pass, including rejected commit cleanup and successful retry. Updated source expectation to named fields and cleanup check to both extensions.
- Notes: S1 commit cee88cbc. Archive recognition follows in S3; no schema/dependency changes. Estimates unchanged.

*— Codex · 2026-10-29 UTC*

## Stage 3 - Replay and archive compatibility

**Original estimate: 0.10**
**Retrospective stage-work assessment: 0.10**

- Changes: Both extensions recognized; trusted restore preflights all source/destination acceptance evidence before copying, then deduplicates only equivalent facts. Grant path lookup handles flat/dated posts. Remote import authority exclusion remains intact.
- Verification: 25/25 focused receipt/archive/content-import/API/rebuild checks. Expanded invitation restore test covers text, mixed, legacy-only and conflicting archives through the actual import command. Signed account recovery and incremental/fresh equivalence pass.
- Notes: S2 commit 1aca4c67. No replay-policy changes or schema/dependency delta; estimates unchanged.

*— Codex · 2026-10-29 UTC*
