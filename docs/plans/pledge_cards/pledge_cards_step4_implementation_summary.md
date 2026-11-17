> **Feature plan:** [Step 1](./pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_cards_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_cards`; [ledger](../effort_ledger/pledge_cards.md). Standing P2 enrollment. Parent: `v3/pledge_frontend`.

Extended estimates: enabled

Status: Steps 1 (Option A), 2 and 3 approved by the maintainer 2026-11-17. Step 4 in progress on `feat/pledge-cards`, cut from `origin/main` at `6e82449d`.

**Cycle estimate: 0.65**

# Step 4: Implementation Summary — Pledge cards

## Entry

- Branch: `feat/pledge-cards` from `origin/main` at `6e82449d`. Planning commit `2752eebb` moved Steps 1–3 and the debug log into `docs/plans/pledge_cards/` and updated the plans index and the pledges checklist. The ledger file goes in with Stage 1.
- Entry projection: **0.65** reaffirmed from Step 3. Stage originals: 0.18, 0.20, 0.17, 0.10, not summed. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.

## Stage 1 - Only approved members' pledges count; the count-unnamed flag is gone

**Original estimate: 0.18**

**Retrospective: 0.22**

- Changes:
  - `src/ForumRewrite/View/PledgeCampaign.php`: a private `approved()` check (`author_is_approved` is 1 and attribution counts) decides pledges, fills, withdrawals and reviews. A signed pledge from a key not yet approved gets state `waiting`, "Waiting for approval" (the old `unverified` state folds into it). A pledge with no name keeps "Not counted: no name". Fills, withdrawals and reviews from such keys read "Not counted: waiting for approval". `fromPosts()` loses its `$countUnnamed` parameter.
  - `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`: `PLEDGES_COUNT_UNNAMED_ENABLED` and its definition removed; the Pledges description says "from approved members". `ThreadAndPostPageController` stops passing it.
  - `tests/FeatureFlagEvaluatorTest.php`: the flag dropped from two lists; a new test loads a stored site record that still names it.
  - `tests/PledgeCampaignTest.php`: post builders mark authors approved; "unverified" expectations become waiting; the count-unnamed cases are replaced by an unapproved pledge, a later approval and an unapproved withdrawal; tests that used a post with no name as the second person now link a real second key (`secondIdentity()`, from the signature fixtures) and approve it (`approveKeys()`); one new render test.
  - `tests/browser/pledge_campaign.mjs`: the `--count-unnamed` mode removed. A helper approves a visitor by writing an approval seed record and updating its profile. The writer is approved once it posts the campaign; the second visitor's first pledge reads "Waiting for approval" and stays out of the sum, then counts once approved.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (26 tests, 1 new) and `FeatureFlagEvaluatorTest` (24, 1 new).
  - Passed: the browser check, all 34 theme and width passes, with the new approval step.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest FeatureFlagEvaluatorTest` (507 tests). The full suite was not run.
- Notes:
  - Two wrong first attempts, both in tests: marking `posts.author_is_approved` (the column lives on profiles and is joined when read), and approving a bare key string (the write service refuses authors with no identity). Then, in the browser check, a direct profile update was undone by the incremental read-model updater, which recomputes approval from records; an approval seed record fixed it. The PHP tests' `approveKeys()` updates the profile only, which holds there because no later write recomputes that key's approval in those tests.
  - Estimate details: original scope as in Step 3; no revision. Retrospective higher: the test fixtures needed real second identities and canonical approval.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
