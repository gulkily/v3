> **Feature plan:** [Step 1](./same_username_operator_step1_solution_assessment.md) · [Step 2](./same_username_operator_step2_feature_description.md) · [Step 3](./same_username_operator_step3_development_plan.md) · [Step 4](./same_username_operator_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/same_username_operator`; [host ledger](../effort_estimation_trial.md#cycle-v3same_username_operator). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55** (Step 3 projection, reaffirmed at entry)

Branch: `feat/same_username_operator`. Planning commit: `1ccbadc`.

## Step 4 entry

2026-11-09T05:26:30Z, `railspike-effort` / `v3/same_username_operator`, Step 4 entry. Step 3 approved by the maintainer on 2026-11-09 ("Approve step 3"). Scope v2 unchanged. Projected total **0.55** reaffirmed. Stage originals copied from Step 3: 0.08, 0.12, 0.12, 0.06, 0.05, 0.07, non-additive. Same R1/P2, single exposed Claude rater. Copying is not a new rating.

Starting revision: `f60d639` (`main`, which includes PR #88).

## Stage 1 - Operator grant record

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.07**

- Changes:
  - New spec `docs/specs/operator_grant_record_v1.md`: headers `Record-ID`, `Created-At`, `Operator-Identity-ID`, `Target-Identity-ID`, `Target-Username`; no body; signed `.asc` beside it; evidence only, excluded from import.
  - New `OperatorGrantRecord` and `OperatorGrantRecordParser`, copied from the flag change pair. They also reject a grant whose target is its own operator.
  - `CanonicalPathResolver::operatorGrant()`, `SourcePathValidator` (record path; the `.asc` check reuses it), `CanonicalRecordRepository::loadOperatorGrant()`.
  - Approval seeds take an optional `Operator-Grant-ID` header, parsed into `ApprovalSeedRecord::$operatorGrantId`. This replaces the plan's idea of reading the grant ID from the free-text seed reason. `user_approval_seed_record_v1.md` documents the header and no longer says seeds "only bootstrap" trust.
- Verification:
  - Passed: `./v3 test CanonicalRecordParsersTest CommitMapTest RootApprovalPageTest ApprovalSeedCommandTest`, 84 run, 84 passed. The new test covers a valid grant, six rejections, the path checks, a seed with and without `Operator-Grant-ID`, and a bad `Operator-Grant-ID`.
  - Inapplicable: UI and deployment; this stage adds no route or page.
- Notes:
  - Reuse: `FeatureFlagChangeRecord`, its parser and its spec as the model; `GenericTextRecordParser` for the `Key: value` format.
- Estimate details (extended mode only): original scope as in Step 3; uncertainty low; no revision. The seed header added a few lines and replaced reason parsing planned for Stage 4.

*— Claude · 2026-11-09 UTC · Model: claude-opus-5-5*
