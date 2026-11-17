# Pledges Checklist

Working checklist for pledge campaigns on v3: a thread states a goal, replies pledge toward it, and no money moves through the site. It is not a Railspike artifact and approves no implementation. Each item below is its own staged Railspike cycle, following AGENTS.md. Record progress, checks and limitations here.

Requested by the maintainer on 2026-11-15. Design: [pledges_spec.md](./pledges_spec.md). No matching item was found in `docs/feedback/items/`.

Everything ships behind one site flag, `pledges`, added in the first cycle and off by default.

## Reuse inventory

Found on a first read at `9e5aa010`. Each cycle re-checks its own part before planning.

- `src/ForumRewrite/Support/ThemeDirective.php`, `EventStatusDirective.php` and `TitleDirective.php` read a `key: value` line from a post body, skip quoted lines and take the first match. `goal:` and `pledge:` should follow them.
- `src/ForumRewrite/View/ThreadTheme.php` and `src/ForumRewrite/Calendar/EventStatus.php` derive a thread's state from its posts when read and store nothing. `EventStatus::forThreads()` does it for many threads at once, which board rows will need.
- Tagsets (`resources/tagsets.txt`, `src/ForumRewrite/ReadModel/PostResolution.php`, `templates/partials/post_unlocked_tags.php`) give replies an exclusive state set by a reaction record. Candidate for withdrawn, delivered, confirmed and disputed.
- Retraction deletes a post's prose and leaves a notice (`docs/concepts/post_retraction.md`). It makes a pledge stop counting but cannot show it as withdrawn.
- The deploy celebration (`docs/plans/deploy_celebration/`) already has confetti and a banner.
- Feature flags are defined in `src/ForumRewrite/Support/FeatureFlags/FeatureFlagRegistry.php`.
- The shared composer (`docs/concepts/shared_composer.md`) is where a pledge box belongs.

## Cycles

- [x] **1. `pledge_campaigns`** — a thread with a `goal:` line shows a totals block; replies with a `pledge:` line show their amount and add to the total; a Pledge field on the reply form writes the line. Adds the `pledges` flag (`FORUM_PLEDGES_ENABLED`, off by default).
  - Status: built on `feat/pledge-campaigns`, [PR #268](https://github.com/gulkily/v3/pull/268) merged 2026-11-16. Steps 2 and 3 were approved by Claude under the maintainer's one-time delegation. Pending: the maintainer's review and the release check on zenmemes.com from an iPhone SE. [Step 1](./pledge_campaigns/pledge_campaigns_step1_solution_assessment.md) · [Step 2](./pledge_campaigns/pledge_campaigns_step2_feature_description.md) · [Step 3](./pledge_campaigns/pledge_campaigns_step3_development_plan.md) · [Step 4](./pledge_campaigns/pledge_campaigns_step4_implementation_summary.md) · [concept](../concepts/pledge_campaign.md)
  - Decided in Step 2 (self-approved; open to the maintainer's overrule): unverified and unnamed pledges show their amount and stay out of the sum; approval is not required; decimals to two places.
  - A pledge made without JavaScript has no name, so it is shown and not counted. Since 2026-11-16 the flag "Count pledges with no name" (`FORUM_PLEDGES_COUNT_UNNAMED_ENABLED`, off by default) lets an instance count them. [Record](./pledge_count_unnamed/pledge_count_unnamed_one_shot.md)
  - Checks: `PledgeCampaignTest` (7 tests) with the smoke set, and `tests/browser/pledge_campaign.mjs` in 17 themes at 375 and 1280 px. The full suite was not run. Outbox and offline sending were not exercised.
  - Left out: no field on the separate Reply page or in paned layouts; the Pledge/Reply switch from the mock-up became one optional field.
- [x] **2. `pledge_withdraw`** — a pledger withdraws a pledge; it stays visible, marked withdrawn, and stops counting.
  - Status: built on `feat/pledge-withdraw` (from `origin/main` at `b9ffa6b8`, after PR #268 merged), [PR #275](https://github.com/gulkily/v3/pull/275) merged 2026-11-16. The maintainer approved Steps 1 (Option A), 2 and 3 on 2026-11-16. Pending: the maintainer's review and the release check on zenmemes.com from an iPhone SE. [Step 1](./pledge_withdraw/pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw/pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw/pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw/pledge_withdraw_step4_implementation_summary.md) · [concept](../concepts/pledge_campaign.md)
  - Decided: a `withdraw` line in a reply to the pledge, read when the thread is shown; retracting that reply undoes it. A bare withdraw reply folds into the pledge; one with a note or an answer stays. Only the pledger withdraws; operators can still retract. The line takes no number. Nothing was built for part-filled pledges.
  - For review first: "the pledger" is the same key, so a pledge made with one key cannot be withdrawn with another key of the same username. A counted pledge with no name cannot be withdrawn by anyone. Undo has no button: open the withdraw reply from "Withdrawn" and retract it.
  - Checks: `PledgeCampaignTest` (12 tests) and `WithdrawLeafFilterTest` with the smoke set; `tests/browser/pledge_campaign.mjs` in headless Chrome, including a failed first press, in 17 themes at 375 and 1280 px. The full suite was not run. The signed retraction used for undo, Outbox and offline sending were not exercised.
  - Left out: no button on the separate Reply page or in paned layouts; the text output and agent API do not report the withdrawn state (cycle 6).
- [x] **3. `pledge_fills`** — the pledger marks a pledge delivered; the author and operators mark fills directly; partial fills add up. Totals gain filled and confirmed. (Confirm and dispute moved to cycle 8.)
  - Status: built on `feat/pledge-fills` (from `origin/main` at `c98b5221`) in five stages. The maintainer approved Steps 1 (Option B), 2 and 3 on 2026-11-16. Pending: the maintainer's review and the release check on zenmemes.com from an iPhone SE. [Step 1](./pledge_fills/pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills/pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills/pledge_fills_step3_development_plan.md) · [Step 4](./pledge_fills/pledge_fills_step4_implementation_summary.md) · [concept](../concepts/pledge_campaign.md)
  - Scope (Option B): a `filled` reply line from the pledger, the author or an operator; partial amounts add up to the pledge; a filled total and a confirmed share. Confirm and dispute moved to cycle 8, `pledge_fill_review`.
  - Decided in Step 2 (approved): withdrawing a part-filled pledge withdraws only the unfilled part; a fill larger than what is left counts up to what is left; a bare `filled` reply folds like a bare `withdraw`; "Delivered" for the pledger, "Filled" for the author or an operator; Released goes to cycle 8.
  - Answered: who may mark when the pledger's key is not approved. A reply line needs no approval, only counted attribution.
  - Built: fills are read before withdrawals, so reply order never changes the result. A fully filled pledge loses Withdraw and the fill button. The block shows "filled" only once something is filled. "Mark filled" for operators uses the page's `viewerCanRetractOthers` test.
  - Checks: `PledgeCampaignTest` (17 tests) and `WithdrawLeafFilterTest` (2) with the smoke set; `tests/browser/pledge_campaign.mjs` in headless Chrome, including a failed first press, in 17 themes at 375 and 1280 px. The full suite was not run. Outbox and offline sending were not exercised.
  - Found and fixed: `WithdrawLeafFilterTest` from cycle 2 was never on the runner's list in `tests/run.php`, so it had never run.
  - Left out: no buttons on the separate Reply page or in paned layouts; the text output and agent API do not report fills (cycle 6).
- [x] **8. `pledge_fill_review`** — the author confirms or disputes a pledger's "Delivered"; a disputed fill stops counting and both stay visible. Added 2026-11-16 at cycle 3 Step 1 (Option B). Numbered 8 so the other numbers stay stable; it runs straight after cycle 3.
  - Status: built on `feat/pledge-fill-review` (from `origin/main` at `6bdc52d7`) in four stages, [PR #305](https://github.com/gulkily/v3/pull/305) merged 2026-11-17. The maintainer approved Step 1 (Option A) on 2026-11-16, and Steps 2 and 3 on 2026-11-17. Pending: the maintainer's review and the release check on zenmemes.com from an iPhone SE. The maintainer approves every step. [Step 1](./pledge_fill_review/pledge_fill_review_step1_solution_assessment.md) · [Step 2](./pledge_fill_review/pledge_fill_review_step2_feature_description.md) · [Step 3](./pledge_fill_review/pledge_fill_review_step3_development_plan.md) · [Step 4](./pledge_fill_review/pledge_fill_review_step4_implementation_summary.md)
  - Scope (Option A): `confirm` and `dispute` reply lines from the author or an operator, answering a pledger's fill reply. Buttons sit on the pledge's fill line, since a bare fill reply is folded.
  - Released: dropped by the maintainer at Step 1 approval; not planned for the near future.
  - Built: reviews are read before fills, so a disputed fill takes no room and its part of the pledge is open again (the pledger can mark it delivered again or withdraw it). The latest counting review decides. A bare confirm folds; a dispute and the fill it answers stay as cards. Buttons sit on the pledge's fill line and show only on an unreviewed pledger fill, for the author or an operator.
  - Checks: `PledgeCampaignTest` (22 tests) and `WithdrawLeafFilterTest` (3) with the smoke set; `tests/browser/pledge_campaign.mjs` in headless Chrome, with a second visitor as pledger and a failed first Confirm press, in 17 themes at 375 and 1280 px, plus `--count-unnamed`. The full suite was not run. Outbox and offline sending were not exercised.
  - Left out: no review buttons on the separate Reply page or in paned layouts; the text output and agent API do not report reviews (cycle 6).
## Replan, 2026-11-17

The maintainer: "this epic is getting out of control, it was supposed to be an mvp, and the frontend still looks like crap. let us prioritize the frontend and replan the epic, cut any unnecessary features."

The MVP is cycles 1 to 3 and 8, which are built, plus cycle 9, which makes the thread look right. Nothing else is planned.

- [x] **9. `pledge_frontend`** — the campaign thread on a phone looks like the [mock-up](https://claude.ai/artifact/GaT42e25otTieeNpiFawch), minus the cut parts: the totals block under the title, a themed bar, the amount first on each pledge with one status label, raw lines and action replies out of sight, "Goal reached" and "Complete" as a status word, and a pledge form (amount, note, Post pledge) in the totals block.
  - Status: built on `feat/pledge-frontend` (from `origin/main` at `f381052a`) in five stages. The maintainer chose Option C at Step 1 and approved Steps 1, 2 and 3 on 2026-11-17. Pending: the maintainer's review and the release check on zenmemes.com from an iPhone SE (read the first screen; post and withdraw a pledge from the new form). [Step 1](./pledge_frontend/pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend/pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend/pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend/pledge_frontend_step4_implementation_summary.md) · [concept](../concepts/pledge_campaign.md)
  - Built: the totals block under the title, with "Goal reached" or "Complete", a bar with delivered over pledged in the theme's ink, and "3 pledges · 15 delivered"; a pledge form there (the reply composer with an amount, a note and Post pledge, its own draft, no Options); used lines left out of the thread page's text; any action reply that is only its line folds, except a fill counted in part; each pledge card leads with its amount and Open, Part delivered or Delivered.
  - Checks: `PledgeCampaignTest` (25 tests), `WithdrawLeafFilterTest` (3) and `OfflineOutboxComposeTest` with the smoke set; `tests/browser/pledge_campaign.mjs` in 17 themes at 375 and 1280 px, plus `--count-unnamed`, with new checks for the pledge form's failed first press, separate drafts, the Reply link and unsent replies; a local replay of the demo thread at 375 by 667 px with and without JavaScript. The full suite was not run.
  - Left out: no pledge form offline, on the Reply page or in paned layouts (a typed `pledge:` line still works). There is no thread `.txt` view yet (cycle 6, parked).
- [x] **10. `pledge_cards`** — each pledge on a campaign thread collapses to one row (name, amount, status), and the rows form a list; opening a row shows the full pledge and its actions. Only approved users' pledges count; others show as "Waiting for approval" rows outside the total; the count-unnamed flag goes. Requested 2026-11-17 ("the comments … look very busy").
  - Status: built on `feat/pledge-cards` (from `origin/main` at `6e82449d`) in four stages; Steps 1 (Option A), 2 and 3 approved by the maintainer 2026-11-17. Pending: the maintainer's review, whether the "claude" key is approved on zenmemes.com (otherwise the demo's pledges wait), and the release check from an iPhone SE (see the list, open a row, confirm a delivery from "More"). [Step 1](./pledge_cards/pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards/pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards/pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards/pledge_cards_step4_implementation_summary.md)
  - Built: only approved members' pledges, fills, withdrawals and reviews count, read when shown; others are "Waiting for approval" rows. `FORUM_PLEDGES_COUNT_UNNAMED_ENABLED` removed. Each pledge is a closed one-line row; adjacent rows form a list and never merge into a same-author run. Every reply on a campaign thread has the list byline and its buttons in "More".
  - Checks: `PledgeCampaignTest` (31), `WithdrawLeafFilterTest`, `FeatureFlagEvaluatorTest` with the smoke set; `tests/browser/pledge_campaign.mjs` in 17 themes at 375 and 1280 px with closed-row checks, an approval step and presses inside "More"; a local replay at 375 by 667 px with and without JavaScript. The full suite was not run.
  - Left out: the opening post's action row; compact cards on other threads. Word97 and Bubblegum keep rows as separate windows.
  - Correction after merge (PR #317): approving someone now clears the baked pages of the threads they posted in, so their "Waiting for approval" rows go on the next view.
- [x] **11. `pledge_form_place`** (one-shot) — the pledge form moves below the opening post's text and gets a bordered panel; the totals stay under the title. Requested 2026-11-17 ("move the pledge form below the essay", "some kind of boundary around the pledge form"). [Record](./pledge_form_place/pledge_form_place_one_shot.md). Release check pending.
- [ ] ~~**4. `pledge_stages`**~~ — cut 2026-11-17. Its Step 1 was written and dropped before approval. Only the status word survives, in cycle 9. Cut: Cushioned and the `cushion:` line, marker posts, the first-pledge marker and quarter marks, and the Fully pledged banner and confetti.
- [ ] **5. `pledge_board_rows`** — parked, not planned. Campaign rows on the board would show "60 / 50 coffees". Revisit after cycle 9 if campaigns are hard to find.
- [ ] **6. `pledge_agent_text`** — parked, not planned. The `.txt` view already carries every line, since posts are plain text; only computed totals are missing.
- [ ] ~~**7. `pledge_configuration`**~~ — cut 2026-11-17. "Author decides" stays the only calling method, and the word stays "pledge".

Notes for later cycles from cycle 8: `PledgeCampaign::fromPosts()` gains `reviews`, and a reviewed `fill_list` entry carries `review`, `review_by` and `review_post_id`, which cycle 6 can report. Cycle 4's "Complete" can read `confirmed` as it is.

Notes for later cycles from cycle 3: `PledgeCampaign::fills()` gives each fill a role (pledger, author, operator), which is what cycle 8's confirm and dispute need; `fills` and `withdrawals` carry the reasons; the fold and the button script take a new line by adding to their maps and roles.

Notes for later cycles from cycle 2: reply lines are read in `PledgeCampaign::withdrawals()`, with a reason for each one that does not take effect; `WithdrawLeafFilter` is the fold; `pledge_withdraw.js` shows how a button posts a signed reply line. A `filled:` line can follow all three.

Notes for later cycles from cycle 1: `PledgeCampaign::fromPosts()` is the one place that decides what counts, so withdrawal and fills extend it; `pledge_amount.php` is where a state badge goes; the amount field should also reach the separate Reply page.

Cycles 2 and 3 need 1. Cycle 8 needs 3. Cycle 9 needs 8. Cycle 10 needs 9.

## Still open in the design

Since the 2026-11-17 replan, nothing below is planned; each stays as it is unless a real campaign shows the need.

- Quiet campaigns: suggested answer is to show the last activity date and build nothing else.
- Closing a campaign to new pledges.
- Whole numbers only, or decimals. Cycle 1 Step 2: decimals to two places (self-approved under delegation).
- Whether an unverified or unapproved post's pledge counts. Cycle 1 Step 2: unverified and unnamed pledges do not count, unapproved keys do (self-approved under delegation).
- The plain theme drawn for the mock-ups: cut 2026-11-17.

## Log

- 2026-11-15: design discussion; spec and mock-ups drafted outside the repository.
- 2026-11-16: spec copied to `docs/plans/pledges_spec.md` with a code check; this checklist created; cycle 1 Step 1 written.
- 2026-11-16: at the maintainer's request, the Step 1 draft, this checklist, the spec, the debug log and the ledger entry were committed on branch `plan/pledge-campaigns` before Step 1 approval, so a new session can resume. This is an explicit exception to keeping Steps 1 to 3 uncommitted. Step 1 is still awaiting `Approved Step 1`.
- 2026-11-16: maintainer approved cycle 1 Step 1 (Option A). Step 2 written with answers to the two Step 1 questions; awaiting `Approved Step 2`. Mock-ups are on the [design canvas](https://claude.ai/artifact/GaT42e25otTieeNpiFawch).
- 2026-11-16: the maintainer delegated step approval to Claude for cycle 1 only, as an experiment. Steps 2 and 3 approved on that basis; plans moved to `docs/plans/pledge_campaigns/`; implementation started on `feat/pledge-campaigns`.
- 2026-11-16: cycle 1 built in four stages and pushed as a PR. Release check on zenmemes.com pending.
- 2026-11-16: cycle 2 Step 1 written and pushed on `plan/pledge-withdraw` for a new session to resume. Cycle 1's delegated approval does not apply to cycle 2.
- 2026-11-16: new session. PR #268 checked: open, not merged, so `plan/pledge-withdraw` needed no update (0 commits behind `origin/main`). Maintainer approved cycle 2 Step 1 (Option A). Step 2 written, shared and approved. Step 3 written in five stages, shared and approved; plans moved into `docs/plans/pledge_frontend/`. Cycle 9 built in five stages on `feat/pledge-frontend` from `origin/main` at `f381052a` and pushed as a PR. Release check on zenmemes.com pending. Nothing else is planned. Spec's withdrawal lines reworded.
- 2026-11-16: maintainer cut Step 2's part-filled groundwork by review comment, then approved Step 2. Step 3 written in four stages and shared as a file; awaiting `Approved Step 3`.
- 2026-11-17: maintainer approved cycle 8 Step 3. Plans moved into `docs/plans/pledge_fill_review/`; building on `feat/pledge-fill-review` from `origin/main` at `6bdc52d7`.
- 2026-11-17: cycle 8 built in four stages and pushed as a PR. Release check on zenmemes.com pending. Cycle 4 is next.
- 2026-11-17: PR #305 merged. Demo thread posted on zenmemes.com as claude, showing cycles 1 to 3 on prod: [Pledge campaigns demo: 50 coffees](https://zenmemes.com/threads/thread-20261117061140-37ee2928) (60 of 50 pledged, a withdrawal, a full and a partial delivery, an unreadable pledge). Confirm and dispute need a second key, so the thread asks the maintainer to try them once cycle 8 is deployed; that is the release check for cycles 3 and 8.
- 2026-11-17: PR #309 merged. New session on `origin/main` at `fc368349`. Cycle 4 Step 1 was written, then dropped before approval: the maintainer asked to prioritize the frontend and cut unnecessary features. Epic replanned: cycle 9 `pledge_frontend` is next; cycles 4 and 7 cut; 5 and 6 parked. Cycle 9 Step 1 written (recommends Option B) and shared as a file. The maintainer chose Option C; Step 1 revised and shared again, then approved. Step 2 written and shared as a file; awaiting `Approved Step 2`.
- 2026-11-17: PR #316 merged. The maintainer reported "Waiting for approval" rows staying after approval until reload; a baked-page defect, fixed as a cycle 10 correction in PR #317. The maintainer asked to move the pledge form below the essay and frame it; cycle 11 `pledge_form_place` built as a one-shot. Updating rows in place without a reload was answered with costs and not chosen.
- 2026-11-17: PR #313 opened for cycle 9. The maintainer asked to iterate on the reply cards, which look busy. Cycle 10 `pledge_cards` added; Step 1 written (recommended Option B) and shared as a file. PR #313 merged. The maintainer asked to hide the less relevant buttons until a card is opened; Step 1 revised (Option D, a "More" disclosure) and shared again. The maintainer asked to collapse pledges to the bare minimum; Step 1 revised again (recommends Option F, one row per pledge) and shared. The maintainer decided Reply goes inside "More" and ordinary replies get compact cards, and asked that only approved users' pledges appear; Step 1 revised a third time (adds the approved-only rule and removes the count-unnamed flag). After a review of the choices, the maintainer chose waiting rows; Step 1 rewritten clean (recommends Option A, 0.65), shared and approved. Step 2 written, shared and approved. Step 3 written in four stages, shared and approved; plans moved into `docs/plans/pledge_cards/`. Cycle 10 built in four stages on `feat/pledge-cards` and pushed as a PR. Release check pending.
- 2026-11-16: maintainer approved cycle 2 Step 3. PR #268 had merged by then, so cycle 2 was built in four stages on a new branch from `origin/main` and pushed as a PR. Release check on zenmemes.com pending.
- 2026-11-16: PR #275 merged. Cycle 3 Step 1 written and pushed on `plan/pledge-fills` for a new session to resume.
- 2026-11-16: new session. `plan/pledge-fills` was 0 commits behind `origin/main`, so it needed no update. Maintainer approved cycle 3 Step 1 (Option B). Cycle 8 `pledge_fill_review` added. Step 2 written and shared as a file; awaiting `Approved Step 2`.
- 2026-11-17: maintainer approved cycle 8 Step 2. Plan moved onto `origin/main` at `4a6c8bd0` (unrelated PRs only). Step 3 written in four stages and shared as a file; awaiting `Approved Step 3`.
- 2026-11-16: maintainer approved cycle 3 Step 3. Built in five stages on `feat/pledge-fills` from a fresh `origin/main` and pushed as a PR. Release check on zenmemes.com pending.
- 2026-11-16: maintainer approved cycle 3 Step 2. Two unrelated PRs had landed, so `plan/pledge-fills` was rebased onto `origin/main` locally. Step 3 written in five stages and shared as a file; awaiting `Approved Step 3`.
- 2026-11-16: PR #282 merged. New session on a fresh `origin/main` (`f13ed676`); `plan/pledge-fills` is stale and not used. Cycle 8 Step 1 written (recommends Option A) and shared as a file; awaiting `Approved Step 1`. Cycle 2's Withdrawn noted as a reply line, not a reaction.
- 2026-11-16: maintainer approved cycle 8 Step 1 (Option A) and dropped Released. Step 2 written and shared as a file; awaiting `Approved Step 2`.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5 (cycle 8 entries)*
