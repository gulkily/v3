> **Feature plan:** [Step 1](./pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills_step3_development_plan.md) · [Step 4](./pledge_fills_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fills_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fills`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_withdraw`.

Extended estimates: enabled

Status: Steps 1 (Option B), 2 and 3 approved by the maintainer 2026-11-16. Step 4 in progress on `feat/pledge-fills`, cut from `origin/main` at `c98b5221`.

**Cycle estimate: 0.50**

# Step 4: Implementation Summary — Pledge fills

## Entry

- Branch: `feat/pledge-fills` from `origin/main` at `c98b5221`. Cycle 2's PR #275 had merged, so no stacking. The Step 1 commit from `plan/pledge-fills` was carried over, then the planning commit `c7c6d6c` moved Steps 1–3 and the debug log into `docs/plans/pledge_fills/`, and updated the plans index and the pledges checklist (reference fixes and the approved cycle 8). The ledger was left out of the planning commit and goes in with Stage 1.
- Entry projection: **0.50** reaffirmed from Step 3. Stage originals: 0.14, 0.08, 0.05, 0.12, 0.06, not summed. Claude single exposed rater (claude-opus-5-5), 2026-11-16 UTC.

## Stage 1 - Read and count fills

**Original estimate: 0.14**

**Retrospective: 0.12**

- Changes:
  - New `src/ForumRewrite/Support/FilledDirective.php`: `extract()` and `isBare()`, built like `WithdrawDirective`.
  - `src/ForumRewrite/View/PledgeCampaign.php`: `fromPosts()` takes an optional operator check. A new private `fills()` runs before `withdrawals()`. The result gains `filled`, `confirmed` and `fills` (reasons keyed by reply). A pledge with a counted fill gains `filled` and `fill_list` (reply, display name, role, amount). Withdrawal gains "Not counted: already filled". A withdrawn pledge counts in "pledged" as its filled amount.
  - The pledge entry keys are added only when a fill counts, so existing pledge shapes are unchanged.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (15 tests, 3 new): the line reader; pledger, author and operator fills; partial fills adding up; over-fill ("Counted 298 of 350"); every reason; no operator check; retracting a fill; fill and withdraw in both orders; fully filled withdraw in both orders.
  - Passed: `./v3 test --smoke` (483 tests). The full suite was not run.
- Notes:
  - The operator check is called once per distinct fill author per page.
  - Estimate details: original scope as in Step 3; no revision. Retrospective lower: the reasons table fitted the withdraw pattern directly.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
