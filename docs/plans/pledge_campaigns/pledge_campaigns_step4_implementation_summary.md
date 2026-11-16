> **Feature plan:** [Step 1](./pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_campaigns_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_campaigns`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 4 Stages 1–4 complete on branch `feat/pledge-campaigns`; release check pending maintainer. [PR #268](https://github.com/gulkily/v3/pull/268). Planning commit `613cef37`. Steps 2 and 3 were approved by Claude under the maintainer's one-time delegation ([record](./pledge_campaigns_step2_feature_description.md#delegated-approval)).

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

## Stage 3 - Pledge amount on the reply form

**Original estimate: 0.14**
**Retrospective: 0.16**

- Changes:
  - `templates/partials/reply_form.php`: on a campaign's thread page the form gains one optional field, "Pledge [amount] coffees", above the body. `composer.php` gains a `$composerLeadFields` slot for it.
  - `PledgeDirective::withAmount()` and `ComposeAndAccountKeyController::submitComposeReply`: without JavaScript the server writes `pledge: N` as the first line of the body. A body that already has the line is left alone.
  - `public/assets/pledge_field.js`, loaded only on campaign threads: keeps the line in the body field and the amount field in step, both ways. Signed, unsigned and Outbox sending read the body as before.
  - `public/assets/compose.css`: the amount field stays visible while the reply box is collapsed. Field styles in `site.css`.
  - `tests/browser/pledge_campaign.mjs`: the whole flow in a browser.
- Verification:
  - `./v3 test --smoke PledgeCampaignTest ThreadThemeTest PostSignalsTemplateTest`: passed, 486 of 486. The full suite was not run.
  - `node tests/browser/pledge_campaign.mjs`: passed. A signed pledge posted from the amount field at 375 px; a pledge past the goal showed the goal mark; a no-JavaScript pledge posted through the form.
  - `node tests/browser/composers.mjs`: passed, so composers outside campaigns are unchanged.
- Notes:
  - **A no-JavaScript pledge posts but is not counted.** Without JavaScript there is no browser key, so the post has no name, and Step 2 decided that unnamed pledges stay out of the sum. The reply shows "Not counted: no name". This follows the approved rule, but it is the decision most worth the maintainer's second look. Counting unnamed pledges is a one-line change in `PledgeCampaign::fromPosts()`.
  - Change from Step 3: an amount that is not a number is not refused. The line is written as typed and the reply shows why it is not counted. The Reply page the refusal would have redrawn has no amount field, so the number would have been lost.
  - The first browser run found the amount field hidden while the reply box was collapsed; fixed in `compose.css`.
  - Outbox and offline sending were not exercised in a browser. They read the same body field, which the browser check shows carries the line.
- Estimate details: original scope unchanged. Slightly higher in retrospect: the collapsed-composer rule and the both-ways sync were not foreseen.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 4 - Themes and documentation

**Original estimate: 0.08**
**Retrospective: 0.08**

- Changes:
  - `docs/concepts/pledge_campaign.md` created; one line each added to `shared_composer.md` and `post_presentation.md`.
  - `docs/plans/pledges_checklist.md`: cycle 1 marked built, with checks, omissions and notes for later cycles.
  - Ledger closeout in `docs/plans/effort_estimation_trial.md`.
- Verification:
  - `node tests/browser/pledge_campaign.mjs --screenshots=/tmp/pledges`: passed in 17 themes (light, dark, gloss, glossdark, console, lcd, chicago, vapor, forge, sticker, arena, thermal, whitehot, word97, midnight, bubblegum, spearmint) at 375 and 1280 px: no page overflow, the bar drawn and distinct from the card, the amount field present.
  - Screenshots looked at by eye: gloss, glossdark, midnight, chicago, bubblegum, console, lcd, word97, vapor and thermal at 375 px; gloss at 1280 px. Sum, bar, goal mark, amounts, reasons and the field were readable in each. The other themes passed the automated check only.
  - `git diff --check` on the branch: passed.
  - Whole-branch diff read for duplication and scope: only pledge files, the two template hooks, the composer slot, the controller wiring, tests and docs.
- Notes:
  - Engagify, chouse, qdb and mitrapclub themes were not checked.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Completion against the Step 3 contract

- Entry and end-to-end outcome: **passed** in a local browser. An author posted a campaign from New post; a signed pledge from the reply form changed the sum.
- Recovery: uncounted pledges show a reason, **passed**. Flag off removes the block and field, **passed**. "A refused pledge keeps its number and text" was **not exercised**: a refused reply redraws the Reply page with the body, which holds the line when JavaScript is on; without JavaScript the amount itself is not redrawn.
- Deployment: no schema change, rebuild or dependency. **Passed** by inspection.
- Release condition (Ilya, iPhone SE, zenmemes.com, flag on): **pending**. Not reachable from this session.
- Full test suite: **not run**, per `AGENTS.md`.

## Enhancement requests

- **Request:** the plain theme drawn for the mock-ups. **Disposition:** separate work. **Approval:** pending. **Follow-up:** listed under "Still open" in the checklist.

## Cycle retrospective

- Retrospective scope assessment: **0.45** (not a projection, not measured time). The thread rows already carried every value the totals needed, which removed the expected query work; the collapsed reply box and the no-name finding added some.
- Delivered: stages 1–4 as planned, 4 stage commits after the planning commit. No scope added. Three refinements recorded in Step 3 and one change in Stage 3.
- Process note: this cycle's Steps 2 and 3 were approved by the agent that wrote them. No one else read the plans before implementation. The decisions that most need a second reader are listed in the checklist.
- Maintainer comfortable-fit judgment: pending.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
