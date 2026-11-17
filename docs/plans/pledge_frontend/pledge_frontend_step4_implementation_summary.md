> **Feature plan:** [Step 1](./pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_frontend_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_frontend`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fill_review`. Replaces `v3/pledge_stages`.

Extended estimates: enabled

Status: Steps 1 (Option C), 2 and 3 approved by the maintainer 2026-11-17. Step 4 in progress on `feat/pledge-frontend`, cut from `origin/main` at `f381052a`.

**Cycle estimate: 0.75**

# Step 4: Implementation Summary — Pledge frontend

## Entry

- Branch: `feat/pledge-frontend` from `origin/main` at `f381052a`. Since Step 3's `fc368349`, unrelated PRs landed; one touched `site.css` outside the pledge rules. Planning commit `f0657102` moved Steps 1–3 and the debug log into `docs/plans/pledge_frontend/` and updated the plans index, the pledges checklist and the spec (the replan). The ledger was left out of the planning commit and goes in with Stage 1.
- Entry projection: **0.75** reaffirmed from Step 3. Stage originals: 0.14, 0.14, 0.10, 0.20, 0.08, not summed. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.

## Stage 1 - Totals under the title

**Original estimate: 0.14**

**Retrospective: 0.14**

- Changes:
  - `templates/partials/thread_root_card.php`: the totals block is drawn right after the title, before the pitch. A QDB quote root keeps it after the body, as before.
  - `templates/partials/pledge_totals.php`: a status pill, "Goal reached" or "Complete", beside the sum; the bar gains a delivered part over the pledged part; the count line reads "3 pledges · 15 delivered", with "(5 confirmed)" only when confirmed is above zero and differs. The block carries `data-pledge-status`.
  - `src/ForumRewrite/View/PledgeCampaign.php`: the result gains `delivered_percent` and `status` (open, goal_reached or complete), derived when read. No rule changes.
  - `public/assets/site.css`: pledge totals rules rewritten. Delivered is the theme's ink; pledged is the ink at 35%; the track is the card itself; the pill uses the active colours, and "Complete" uses `--status-ok`.
  - `tests/PledgeCampaignTest.php`: one new test; two count-line expectations reworded. `tests/browser/pledge_campaign.mjs`: sum and count expectations reworded ("Goal reached" over the goal; "delivered" for "filled").
- Verification:
  - Passed: `./v3 test PledgeCampaignTest` (23 tests, 1 new): the block sits between the title and the pitch; open, Goal reached and Complete; the delivered width and the bar's label; the count line with and without confirmed.
  - Passed: `CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/pledge_campaign.mjs --screenshots=…`, all 34 theme and width passes.
  - Passed: the 375-pixel screenshots in all 17 themes, looked at. The block is under the title in every theme; the delivered part, the faded pledged part and the goal mark are told apart in every theme.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest` (503 tests). The full suite was not run.
- Notes:
  - Refinement within scope, found in the screenshots: the first colours (theme accent for pledged, active ink for delivered, active background for the track) drew a one-colour bar in Arena, Sticker and Word97, where those match the ink. Drawing both parts in the ink on the card fixed every theme. The bar loses the accent colour; the pill keeps it.
  - The raw `goal:` and `pledge:` lines still show; Stage 2 hides them.
  - Estimate details: original scope as in Step 3; no revision. Retrospective equal: the colour rework took two extra theme runs, offset by the test changes being small.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 2 - The thread page stops showing records

**Original estimate: 0.14**

**Retrospective: 0.12**

- Changes:
  - New `src/ForumRewrite/Support/PledgeLines.php`: `hide()` removes the first unquoted line a given reader accepts (goal, pledge, withdraw, filled, review), plus the extra blank lines that leaves; `isOnlyLine()` says whether a body is that line and nothing else. It reuses each directive's own reader, so the rules cannot drift.
  - `PledgeCampaign::fromPosts()` gains `used_lines`: the root's goal line, every pledge line except an unreadable one, and every withdraw, fill and review line that took effect.
  - `templates/partials/thread_root_card.php` and `post_card.php` pass the body without its used line to `$renderPostBody()`, on the thread page only (the one page that sets `$pledgeCampaign`). A post left empty draws no text box.
  - `WithdrawLeafFilter` folds an effective action reply that is only its line, with or without an amount. A fill counted only in part keeps its card for its "Counted 300 of 500" note.
  - Tests: two new (`PledgeLines` rules; lines that did not count stay). The fills render test now expects `filled: 100` to fold and checks the post's own page keeps `pledge: 300`. `WithdrawLeafFilterTest` expects `filled: 100` to fold. The browser check expects the no-JavaScript pledge's line hidden and its note shown.
- Verification:
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest` (28 tests).
  - Passed: the browser check, all 34 theme and width passes, before the blank-line fix. The light theme at 375 pixels, looked at: no `goal:` or `pledge:` line shows; the unreadable "pledge: a few" stays with its reason.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest` (505 tests). The full suite was not run.
- Notes:
  - Refinement within scope, found in the screenshot: removing the goal line from between two paragraphs left a double gap; `hide()` now collapses it.
  - Correction to Step 2 and 3: there is no thread `.txt` view yet (it was cycle 6's, now parked). The check uses the post's own page, which keeps every line, as the Reply page and the API do.
  - A disputed fill reply keeps its `filled` line, since it no longer counts and its reason shows.
  - Estimate details: original scope as in Step 3; no revision. Retrospective lower: the directive readers already worked per line.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
