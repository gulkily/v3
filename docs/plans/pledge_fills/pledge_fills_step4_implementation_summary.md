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

## Stage 2 - Show fills on each pledge and in the block

**Original estimate: 0.08**

**Retrospective: 0.08**

- Changes:
  - `ThreadAndPostPageController` passes `ApprovalPolicy::isActiveOperator()` as the operator check.
  - `templates/partials/pledge_amount.php` lists each counted fill on its own line under the amount: "Delivered by mara" or "Filled by ilyag", linked to the fill reply, with the amount when it is not the whole pledge. A fill reply that does not count shows its reason; one capped at what was left shows "Counted 300 of 500".
  - `templates/partials/pledge_totals.php` adds "· N filled" to the count line, and "· M confirmed" when that is lower. No CSS change: the lines reuse `pledge-amount`.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (16 tests, 1 new): both labels, the partial amount, the cap note, a reason, the totals line, a baked page dropped by a fill reply and rebuilt with the new totals, and the flag off.
  - The page test uses posts with no name, with "Count pledges with no name" on, because the fixture has one key. That key fills as the pledger on its own pledge and as an operator on a pledge with no name, which goes through the controller's operator check.
- Notes:
  - Refinement within Step 3: the block shows "filled" only once something is filled, so a new campaign does not read "0 filled".
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*

## Stage 3 - Fold bare fill replies

**Original estimate: 0.05**

**Retrospective: 0.04**

- Changes:
  - `src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`: `apply()` takes the fills map as a third argument and folds a bare `filled` reply by the withdraw rule (took effect, line alone, no shown answer). The class keeps its name; its comment names both lines.
  - `templates/pages/thread.php` passes the fills map.
- Verification:
  - Passed: `./v3 test WithdrawLeafFilterTest` (2 tests, 1 new): bare, partial, noted, answered and uncounted fill replies; a withdrawal answered only by a folded fill; a withdrawal map alone does not fold fill replies.
  - Passed: `./v3 test PledgeCampaignTest` (16): the thread page folds the bare fill and keeps a partial fill and an uncounted one.
  - Passed: `./v3 test ThreadActivityTest RetractedLeafFilterTest TagOnlyLeafFilterTest` (4).
- Notes:
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*

## Stage 4 - Mark delivered and Mark filled buttons

**Original estimate: 0.12**

**Retrospective: 0.10**

- Changes:
  - `PledgeCampaign::fromPosts()` also returns the campaign's `author`, so the template can tell the author from other viewers.
  - `templates/partials/pledge_amount.php`: one fill button on an open pledge (counted, not withdrawn, not fully filled). The pledger sees "Mark delivered". The campaign author or an operator sees "Mark filled", using the page's existing `viewerCanRetractOthers` test for operators. The button posts the bare `filled` line as a reply to the pledge. "Withdraw pledge" no longer shows on a fully filled pledge.
  - `public/assets/pledge_withdraw.js` handles both forms by role (`pledge-withdraw`, `pledge-line`) and takes the failure message from the form's `data-failure`. Same file and same signing calls; no new script.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (17 tests, 1 new): no viewer, the pledger, the campaign author, an operator on a campaign that is not theirs, and a stranger; no fill or withdraw button on a fully filled pledge; the withdraw button test was updated because the author now also sees "Mark filled" on a pledge with no name.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/pledge_campaign.mjs --screenshots=/tmp/pledges` in headless Chrome. The withdraw press still works first; then "Mark delivered" fails once (aborted request, message shown, button enabled), works on the second press, shows "Delivered by guest", the block reads "2 pledges · 300 filled · 0 confirmed", no card is added and the filled pledge loses both buttons. No dialogs or page errors.
  - Passed: the same check with `--count-unnamed`.
  - `playwright-core` was installed from the repository's own `package.json` with `npm install --no-save`; `node_modules/` is ignored. No dependency changed.
- Notes:
  - "Mark filled" is not shown to the author on the author's own pledge; there the author is the pledger and sees "Mark delivered".
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-16 UTC · Model: Claude Opus 5.5*
