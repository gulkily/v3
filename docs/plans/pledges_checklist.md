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
- [ ] **2. `pledge_withdraw`** — a pledger withdraws a pledge; it stays visible, marked withdrawn, and stops counting.
  - Open: the mechanism. Plain retraction does not fit (see inventory).
- [ ] **3. `pledge_fills`** — the pledger marks a pledge delivered; the author confirms or disputes; the author and operators mark fills directly; partial fills add up. Totals gain filled and confirmed.
  - Open: whether tagset states can carry an amount for partial fills, and who may mark when the pledger's key is not approved.
- [ ] **4. `pledge_stages`** — Open, Fully pledged, Cushioned and Complete in the header; the Fully pledged celebration that still shows the pledge box; marker posts; the optional `cushion:` line.
  - Open: what the celebration looks like; it must work without JavaScript.
- [ ] **5. `pledge_board_rows`** — campaign rows on the board show the bar and "1300 / 1000 coffees".
- [ ] **6. `pledge_agent_text`** — the thread's text output and the agent API report goal, totals and each pledge's state; `llms.txt` documents the lines.
- [ ] **7. `pledge_configuration`** — calling method (author decides by default, first come first served, scaled) and the display word for "pledge".
  - Open: per instance, per campaign, or an instance default a campaign can override.

Notes for later cycles from cycle 1: `PledgeCampaign::fromPosts()` is the one place that decides what counts, so withdrawal and fills extend it; `pledge_amount.php` is where a state badge goes; the amount field should also reach the separate Reply page.

Cycles 2 and 3 need 1. Cycle 4 needs 3. Cycles 5 and 6 need 1 and gain detail after 3. Cycle 7 needs 3.

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

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
