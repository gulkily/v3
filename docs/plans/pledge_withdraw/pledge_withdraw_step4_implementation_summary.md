> **Feature plan:** [Step 1](./pledge_withdraw_step1_solution_assessment.md) · [Step 2](./pledge_withdraw_step2_feature_description.md) · [Step 3](./pledge_withdraw_step3_development_plan.md) · [Step 4](./pledge_withdraw_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_withdraw_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_withdraw`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_campaigns`.

Extended estimates: enabled

Status: Steps 1 to 3 approved by the maintainer 2026-11-16. Step 4 in progress on `feat/pledge-withdraw`.

**Cycle estimate: 0.40**

# Step 4: Implementation Summary — Pledge withdraw

## Entry

- Branch: [PR #268](https://github.com/gulkily/v3/pull/268) was merged when Step 3 was approved, so Step 3's Branch question fell away. `feat/pledge-withdraw` starts from a fresh `origin/main` at `b9ffa6b8`. The Step 1 commit from `plan/pledge-withdraw` was carried over; nothing was pushed to an existing branch.
- Planning commit: `fc318f72`, Steps 1 to 3 and the debug log, moved to this folder.
- Entry projection: **0.40**, Step 3 value reaffirmed. Starting revision `b9ffa6b8` (Step 3 was written against `c1572e57`). Stage work estimates copied from Step 3: 0.12, 0.08, 0.14, 0.06; not summed. R1, P2; Claude single exposed rater, 2026-11-16 UTC; R1 hashes not re-verified.
- Discovery at entry, new understanding and same scope: `main` gained "Count pledges with no name" (`FORUM_PLEDGES_COUNT_UNNAMED_ENABLED`, PR #271) after Step 3 was written. A counted pledge with no name has no author to match, so nobody can withdraw it; an operator can still retract it. Stage 1 tests this. No estimate change.
- Reuse decisions carried from Steps 2 and 3: extend `View/PledgeCampaign.php` and `templates/partials/pledge_amount.php`; follow `Support/PledgeDirective.php` for the line reader, `ReadModel/TagOnlyLeafFilter.php` for the fold, and `templates/partials/retract_post_control.php` for the button's viewer test and feedback.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*

## Stage 1 - A withdraw reply marks the pledge and changes the sum

**Original estimate: 0.12**

**Retrospective stage work: 0.10**

- Changes:
  - New `src/ForumRewrite/Support/WithdrawDirective.php`: `extract()` reads `withdraw` or `withdraw:` and returns the text after it; `isBare()` says whether the body is that line alone.
  - `View/PledgeCampaign::fromPosts()` gains the `withdrawn` state and a `withdrawals` list keyed by reply ID, each with whether it took effect and, if not, the reason. A withdrawn pledge's entry names the withdraw reply and its time. Other entries keep their cycle 1 shape.
  - `templates/partials/pledge_amount.php` shows a withdrawn pledge as its struck amount, "Withdrawn" linked to the withdraw reply's page, and the date. A withdraw reply that did not take effect shows its reason.
  - No style rule was needed: the existing rule strikes every amount that is not counted.
- Verification:
  - Passed: `./v3 test --smoke PledgeCampaignTest`, 478 run, 478 passed. Three new tests cover the line reader; the pledger, another key, a second withdrawal, text after the line, unverified, no name, a reply to a non-pledge, a retracted withdraw reply (undo), a retracted pledge and the "count pledges with no name" flag; and the thread page with a baked page before and after a withdrawal, and the flag off.
  - Not run: the full suite.
- Notes:
  - The reason for text after the line reads "Not counted: write “withdraw” alone on its line". Step 2 asked for a reason and did not word it.
  - A reply that carries both a `pledge:` and a `withdraw` line is read as both: it withdraws its parent and pledges anew. Step 2's "withdraw it and pledge again" can be one reply.
  - Reuse: `PledgeDirective.php` pattern for the reader; `PledgeCampaign.php` and `pledge_amount.php` extended; `pledge_totals.php` unchanged.
- Estimate details: original scope as Step 3 Stage 1; no revision. Retrospective judged 2026-11-16T05:37Z, Claude single exposed rater.

*— Claude · 2026-11-16 UTC · Model: Claude Fable 5.2*
