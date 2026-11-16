> **Feature plan:** [Step 1](./pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_campaigns_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_campaigns`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 4 in progress on branch `feat/pledge-campaigns`. Planning commit `613cef37`. Steps 2 and 3 were approved by Claude under the maintainer's one-time delegation ([record](./pledge_campaigns_step2_feature_description.md#delegated-approval)).

**Cycle estimate: 0.50**

# Step 4: Implementation Summary — Pledge campaigns

## Step 4 Entry

2026-11-16: Step 3 projection **0.50 reaffirmed** before implementation; scope v1, baseline `9e5aa010`, R1 not re-verified, Claude single exposed rater. Stage originals copied from Step 3: S1 0.10, S2 0.18, S3 0.14, S4 0.08.

## Stage 1 - Lines, numbers and the flag

**Original estimate: 0.10**
**Retrospective: 0.08**

- Changes:
  - `src/ForumRewrite/Support/PledgeAmount.php`: `parse()` reads a number into whole hundredths; `format()` prints it without trailing zeros.
  - `src/ForumRewrite/Support/GoalDirective.php` and `PledgeDirective.php`, following `ThemeDirective.php`: quoted lines skipped, first match wins.
  - `FORUM_PLEDGES_ENABLED` in `FeatureFlagRegistry.php` (Forum experience group, off by default) and the two flag lists in `FeatureFlagEvaluatorTest.php`.
  - `tests/PledgeCampaignTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test PledgeCampaignTest`: passed, 3 of 3.
  - `./v3 test FeatureFlagEvaluatorTest`: passed, 23 of 23.
- Notes:
  - A goal line counts only when its first word is a number, so "goal: to have fun" stays prose. A unit longer than 60 characters is also read as prose.
  - A number holds at most 12 digits before the point.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 2 - Totals block and amounts on the thread page

**Original estimate: 0.18**
**Retrospective: 0.12**

- Changes:
  - `src/ForumRewrite/View/PledgeCampaign.php`: `fromPosts()` derives goal, unit, sum, count, bar positions and each reply's state from the rows `fetchThreadPosts()` already returns. No query and no stored value is added.
  - `ThreadAndPostPageController.php` passes `pledgeCampaign` to the thread page when `FORUM_PLEDGES_ENABLED` is on.
  - `templates/partials/pledge_totals.php`, included by `thread_root_card.php` after the event block.
  - `templates/partials/pledge_amount.php`, included by `post_card.php` under the reply body.
  - Styles at the end of `public/assets/site.css`, using only `--line`, `--input-bg` and `--ink`.
- Verification:
  - `./v3 test --smoke PledgeCampaignTest PostSignalsTemplateTest ThreadThemeTest EventStatusTest TagUnlocksTest`: passed, 500 of 500. The full suite was not run.
  - `PledgeCampaignTest` covers counted, unverified, unnamed, unreadable, wrong-unit, quoted and retracted pledges; flag off; a pledge line outside a campaign; and a baked thread page before and after a pledge.
- Notes:
  - Reuse: reply rows, `attribution_counted` and the event block's placement were used as they are. The two partials are new because no existing block shows a sum or a per-reply amount.
  - An uncounted amount is struck through and followed by its reason. Every reader sees the reason.
  - "coffee" and "coffees" are read as the same unit; any other trailing word leaves the pledge uncounted.
  - The goal mark appears only once pledges pass the goal. Under the goal, the end of the bar is the goal.
  - Not checked in a browser yet; Stage 4 owns the theme check.
- Estimate details: original scope unchanged; no revisions. Lower in retrospect because the thread rows already carried every needed value.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
