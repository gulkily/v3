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

## Stage 2 - Show each review and why a review did not count

**Original estimate: 0.06**

**Retrospective: 0.08**

- Changes:
  - `templates/partials/pledge_amount.php`: a reviewed fill line ends with "· confirmed by ilyag" or "· disputed by ilyag", linked to the review reply. A disputed line wraps "Delivered by mara" (and its amount) in `<s>`, with `data-pledge-state="fill-disputed"`. A review reply that does not count shows its reason (`data-pledge-state="review-uncounted"`). The disputed fill reply's own card shows "Not counted: disputed by …" through the existing fill-reason line. Totals block unchanged.
  - `tests/PledgeCampaignTest.php`: one render test. The fixture has one key, so the pledger is a second key written as post records and read back by a rebuild.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (21 tests, 1 new): both labels and links, the struck-through disputed line with its amount, the disputed fill's reason, a stray review's reason, nothing with the flag off.
  - Pending to Stage 3: the look at one theme at 375 pixels. It needs a reviewed fill on a served page, which the Stage 3 browser run creates.
- Notes:
  - Refinement within scope: `<s>` instead of the withdrawn state's CSS. That rule strikes the `<strong>` amount, and a fill line has none; `<s>` strikes in every theme with no CSS change.
  - Estimate details: original scope as in Step 3; no revision. Retrospective higher: the fixture's single key needed a records-and-rebuild test setup.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 3 - Confirm and Dispute buttons; a bare confirm folds

**Original estimate: 0.12**

**Retrospective: 0.14**

- Changes:
  - `src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`: `apply()` takes the `reviews` map and folds an effective bare `confirm`. A dispute never folds. The existing repeat-until-stable loop then folds the bare fill the confirm answered. `templates/pages/thread.php` passes the map.
  - `templates/partials/pledge_amount.php`: "Confirm" and "Dispute" forms under an unreviewed pledger fill line, for the campaign author or an operator (the same test as "Mark filled"), and not on the viewer's own pledge. Each is a `pledge-line` reply to the fill whose body is the bare line, with "Review failed. Try again." on failure. `pledge_withdraw.js` unchanged.
  - `tests/PledgeCampaignTest.php`: the records-and-rebuild setup became a `writeThread()` helper, used by the Stage 2 test and a new button test.
  - `tests/browser/pledge_campaign.mjs`: a second visitor pledges 20 and 30 and marks both delivered. The author confirms one after a failed first press and disputes the other. The check covers the totals, both labels, the strike-through, the dispute and its fill staying as cards, and no buttons for the pledger. The theme pass checks the disputed line is drawn. The existing fill-link locator takes `.first()`, since the page now has more than one.
- Verification:
  - Passed: the unchanged browser check before any change (17 themes at 375 and 1280 pixels).
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest` (26 tests; 1 new in each): fold for bare confirm, noted confirm, dispute and an uncounted review; buttons only for the author, only on the open pledger fill, none for a signed-out viewer or the pledger.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/pledge_campaign.mjs`, all 34 theme and width passes, and the `--count-unnamed` run.
  - Passed: the Stage 2 visual look. The light theme at 375 pixels shows "Delivered by guest · confirmed by guest", the struck-through disputed line, and the dispute card under the fill.
- Notes:
  - The browser check ran with the preinstalled `playwright-core` 1.63.0, linked into an ignored `node_modules/`; `package.json` declares 1.58.1. No dependency change.
  - Estimate details: original scope as in Step 3; no revision. Retrospective higher: the browser fixture has one key, so the second visitor's automatic guest identity plays the pledger.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
