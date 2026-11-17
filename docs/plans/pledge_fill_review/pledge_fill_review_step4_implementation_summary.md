> **Feature plan:** [Step 1](./pledge_fill_review_step1_solution_assessment.md) · [Step 2](./pledge_fill_review_step2_feature_description.md) · [Step 3](./pledge_fill_review_step3_development_plan.md) · [Step 4](./pledge_fill_review_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_fill_review_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_fill_review`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fills`.

Extended estimates: enabled

Status: Steps 1 (Option A), 2 and 3 approved by the maintainer 2026-11-16 and 2026-11-17. Step 4 in progress on `feat/pledge-fill-review`, cut from `origin/main` at `6bdc52d7`.

**Cycle estimate: 0.40**

# Step 4: Implementation Summary — Pledge fill review

## Entry

- Branch: `feat/pledge-fill-review` from `origin/main` at `6bdc52d7`. Two more PRs had landed since Step 3's `4a6c8bd0`; neither touches pledge code. Planning commit `52787a55` moved Steps 1–3 and the debug log into `docs/plans/pledge_fill_review/` and updated the plans index and the pledges checklist. The ledger was left out of the planning commit and goes in with Stage 1.
- Entry projection: **0.40** reaffirmed from Step 3. Stage originals: 0.12, 0.06, 0.12, 0.06, not summed. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.

## Stage 1 - Read reviews and follow them in the totals

**Original estimate: 0.12**

**Retrospective: 0.10**

- Changes:
  - New `src/ForumRewrite/Support/ReviewDirective.php`: `extract()` returns the word (confirm or dispute) and any text after it; `isBare()`. Built like `FilledDirective`.
  - `src/ForumRewrite/View/PledgeCampaign.php`: a private `reviews()` runs before `fills()`. A review counts when it answers a pledger's fill of a counted pledge, has a name and counted attribution, has nothing after the line, and comes from the campaign author or an operator other than the fill's author. The latest counting review on a fill decides. `fills()` skips a disputed fill ("Not counted: disputed by opal") but lists it on the pledge with what it claimed, and adds a confirmed pledger fill to `confirmed`. The result gains `reviews`, keyed by reply ID.
  - The operator check is memoised once in `fromPosts()`, so reviews and fills share it.
  - `review`, `review_by` and `review_post_id` are added to a fill entry only when it was reviewed, so unreviewed fill shapes are unchanged.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (20 tests, 3 new): the line reader; author and operator confirm and dispute; every reason; no operator check; retracting a dispute; fill, dispute, fill again; confirm, dispute, confirm; a capped fill confirmed; withdrawing after a dispute.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest` (497 tests). The full suite was not run.
- Notes:
  - A bare `confirm` or `dispute` line in any other reply in a campaign thread gets a "Not counted" reason, as stray `withdraw` and `filled` lines already do.
  - Estimate details: original scope as in Step 3; no revision. Retrospective lower: the reasons table and the reading order fitted the fills pattern directly.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
