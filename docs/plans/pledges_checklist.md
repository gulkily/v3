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
- [ ] **3. `pledge_fills`** — the pledger marks a pledge delivered; the author and operators mark fills directly; partial fills add up. Totals gain filled and confirmed. (Confirm and dispute moved to cycle 8.)
  - Status: on branch `plan/pledge-fills` (from `origin/main` at `66856253`, rebased onto `origin/main` at `c98b5221` on 2026-11-16, locally). The maintainer approved Step 1 (Option B) and Step 2 on 2026-11-16. Step 3 written in five stages, awaiting `Approved Step 3`. [Step 1](./pledge_fills/pledge_fills_step1_solution_assessment.md) · [Step 2](./pledge_fills/pledge_fills_step2_feature_description.md) · [Step 3](./pledge_fills/pledge_fills_step3_development_plan.md)
  - Scope (Option B): a `filled` reply line from the pledger, the author or an operator; partial amounts add up to the pledge; a filled total and a confirmed share. Confirm and dispute moved to cycle 8, `pledge_fill_review`.
  - Decided in Step 2 (approved): withdrawing a part-filled pledge withdraws only the unfilled part; a fill larger than what is left counts up to what is left; a bare `filled` reply folds like a bare `withdraw`; "Delivered" for the pledger, "Filled" for the author or an operator; Released goes to cycle 8.
  - Answered: who may mark when the pledger's key is not approved. A reply line needs no approval, only counted attribution.
- [ ] **8. `pledge_fill_review`** — the author confirms or disputes a pledger's "Delivered"; a disputed fill stops counting and both stay visible. Added 2026-11-16 at cycle 3 Step 1 (Option B). Numbered 8 so the other numbers stay stable; it runs straight after cycle 3.
  - Open: Released, an author's or operator's way to stop someone else's pledge counting without deleting it.
- [ ] **4. `pledge_stages`** — Open, Fully pledged, Cushioned and Complete in the header; the Fully pledged celebration that still shows the pledge box; marker posts; the optional `cushion:` line.
  - Open: what the celebration looks like; it must work without JavaScript.
- [ ] **5. `pledge_board_rows`** — campaign rows on the board show the bar and "1300 / 1000 coffees".
- [ ] **6. `pledge_agent_text`** — the thread's text output and the agent API report goal, totals and each pledge's state; `llms.txt` documents the lines.
- [ ] **7. `pledge_configuration`** — calling method (author decides by default, first come first served, scaled) and the display word for "pledge".
  - Open: per instance, per campaign, or an instance default a campaign can override.

Notes for later cycles from cycle 2: reply lines are read in `PledgeCampaign::withdrawals()`, with a reason for each one that does not take effect; `WithdrawLeafFilter` is the fold; `pledge_withdraw.js` shows how a button posts a signed reply line. A `filled:` line can follow all three.

Notes for later cycles from cycle 1: `PledgeCampaign::fromPosts()` is the one place that decides what counts, so withdrawal and fills extend it; `pledge_amount.php` is where a state badge goes; the amount field should also reach the separate Reply page.

Cycles 2 and 3 need 1. Cycle 8 needs 3. Cycle 4 needs 3 only, not 8. Cycles 5 and 6 need 1 and gain detail after 3. Cycle 7 needs 3.

## Still open in the design

- Quiet campaigns: suggested answer is to show the last activity date and build nothing else.
- Closing a campaign to new pledges.
- Whole numbers only, or decimals. Cycle 1 Step 2: decimals to two places (self-approved under delegation).
- Whether an unverified or unapproved post's pledge counts. Cycle 1 Step 2: unverified and unnamed pledges do not count, unapproved keys do (self-approved under delegation).
- The plain theme drawn for the mock-ups: requested, not in any cycle yet.

## Log

- 2026-11-15: design discussion; spec and mock-ups drafted outside the repository.
- 2026-11-16: spec copied to `docs/plans/pledges_spec.md` with a code check; this checklist created; cycle 1 Step 1 written.
- 2026-11-16: at the maintainer's request, the Step 1 draft, this checklist, the spec, the debug log and the ledger entry were committed on branch `plan/pledge-campaigns` before Step 1 approval, so a new session can resume. This is an explicit exception to keeping Steps 1 to 3 uncommitted. Step 1 is still awaiting `Approved Step 1`.
- 2026-11-16: maintainer approved cycle 1 Step 1 (Option A). Step 2 written with answers to the two Step 1 questions; awaiting `Approved Step 2`. Mock-ups are on the [design canvas](https://claude.ai/artifact/GaT42e25otTieeNpiFawch).
- 2026-11-16: the maintainer delegated step approval to Claude for cycle 1 only, as an experiment. Steps 2 and 3 approved on that basis; plans moved to `docs/plans/pledge_campaigns/`; implementation started on `feat/pledge-campaigns`.
- 2026-11-16: cycle 1 built in four stages and pushed as a PR. Release check on zenmemes.com pending.
- 2026-11-16: cycle 2 Step 1 written and pushed on `plan/pledge-withdraw` for a new session to resume. Cycle 1's delegated approval does not apply to cycle 2.
- 2026-11-16: new session. PR #268 checked: open, not merged, so `plan/pledge-withdraw` needed no update (0 commits behind `origin/main`). Maintainer approved cycle 2 Step 1 (Option A). Step 2 written and shared as a file; awaiting `Approved Step 2`. Spec's withdrawal lines reworded.
- 2026-11-16: maintainer cut Step 2's part-filled groundwork by review comment, then approved Step 2. Step 3 written in four stages and shared as a file; awaiting `Approved Step 3`.
- 2026-11-16: maintainer approved cycle 2 Step 3. PR #268 had merged by then, so cycle 2 was built in four stages on a new branch from `origin/main` and pushed as a PR. Release check on zenmemes.com pending.
- 2026-11-16: PR #275 merged. Cycle 3 Step 1 written and pushed on `plan/pledge-fills` for a new session to resume.
- 2026-11-16: new session. `plan/pledge-fills` was 0 commits behind `origin/main`, so it needed no update. Maintainer approved cycle 3 Step 1 (Option B). Cycle 8 `pledge_fill_review` added. Step 2 written and shared as a file; awaiting `Approved Step 2`.
- 2026-11-16: maintainer approved cycle 3 Step 2. Two unrelated PRs had landed, so `plan/pledge-fills` was rebased onto `origin/main` locally. Step 3 written in five stages and shared as a file; awaiting `Approved Step 3`.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
