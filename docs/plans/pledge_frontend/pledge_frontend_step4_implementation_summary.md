> **Feature plan:** [Step 1](./pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_frontend_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_frontend`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fill_review`. Replaces `v3/pledge_stages`.

Extended estimates: enabled

Status: Steps 1 (Option C), 2 and 3 approved by the maintainer 2026-11-17. Step 4 complete on `feat/pledge-frontend`, cut from `origin/main` at `f381052a`. Release check on zenmemes.com from the maintainer's iPhone SE pending.

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

## Stage 3 - Pledge cards lead with the amount and one status

**Original estimate: 0.10**

**Retrospective: 0.08**

- Changes:
  - `templates/partials/pledge_amount.php`: a counted pledge's amount line gains one status, Open, "Part delivered: 30 of 120" or Delivered, using the totals block's pill style. The partial takes a `pledgePart` of `head` (the amount line) or `rest` (fill lines, buttons and reasons). An unreadable pledge has no amount, so its reason stays under the text.
  - `templates/partials/post_card.php`: draws the head above the post's text and the rest under it.
  - `public/assets/site.css`: one rule to sit the pill beside the amount.
  - Tests: the Stage 1 test gains the three labels and the amount-before-note order; four amount expectations gain "Open". The browser check's two card texts gain "Open".
- Verification:
  - Passed: the browser check unchanged before the reorder (Stage 2's run), then `./v3 test PledgeCampaignTest WithdrawLeafFilterTest` (28 tests).
  - Passed: the browser check, all 34 theme and width passes, and the `--count-unnamed` run. The withdraw, fill, confirm and dispute presses, each with a failed first press, still pass.
  - Passed: light, Gloss and Word97 at 375 pixels, looked at. Each card leads with "300 coffees · Delivered" or "1000 coffees · Open", then the note, then the fill lines.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest`. The full suite was not run.
- Notes:
  - "Delivered" is used whoever filled it; the fill line under it still says "Filled by" or "Delivered by" and any review.
  - Refinement within scope, found in the screenshots: the unreadable pledge's reason first rendered above its own line; moved under it.
  - Estimate details: original scope as in Step 3; no revision. Retrospective lower: splitting the partial needed no change to the buttons or `pledge_withdraw.js`.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 4 - A pledge form in the totals block

**Original estimate: 0.20**

**Retrospective: 0.18**

- Changes:
  - `templates/partials/pledge_totals.php`: under the count line, a compose root holding `reply_form.php` as the pledge placement: the amount field with the unit, a two-row "Note (optional)" box and "Post pledge". It replies to the opening post, has no phone dock and sits outside the inline-reply wrapper.
  - `templates/partials/reply_form.php`: the amount field now appears only with `pledgePlacement`, which also marks the form `data-pledge-form`, gives it draft scope `pledge`, and leaves out Options. The bottom reply box loses its Pledge field.
  - `templates/partials/composer.php`: a placement may leave out the Options row (`composerShowOptions`); the post is then plain, as an unticked Markdown box makes it. Every other placement is unchanged.
  - `public/assets/outbox_compose.js`: `showUnsentReplies()` skips the pledge form, so replies saved on the device list by the reply box.
  - `public/assets/site.css`: spacing for the form.
  - Tests: the amount-field test checks the field appears once, in the pledge form with its draft scope, note box, button and no Options, and not in the bottom box. The browser check pledges from the new form throughout and adds four checks (below).
- Verification:
  - Passed: `./v3 test PledgeCampaignTest WithdrawLeafFilterTest OfflineOutboxComposeTest` (33 tests).
  - Passed: the browser check, all 34 theme and width passes. New in it: the bottom box has no amount field; text in the reply box does not reach the pledge form; the opening post's Reply link focuses the reply box; a failed first press shows the error in the pledge form, keeps the amount and note, and a second press posts; an unsent reply in the Outbox lists above the reply box, not in the totals block. The signed pledges, the second visitor's two pledges and the no-JavaScript pledge ("Not counted: no name") all go through the pledge form.
  - Passed: light, Gloss, Word97, Arena, Bubblegum and Console at 375 pixels, looked at. The first screen holds the title, the sum, the status, the bar and the form; the pitch follows.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest OfflineOutboxComposeTest`. The full suite was not run.
  - Not exercised: sending a pledge through the Outbox while offline; the offline reader, which has no pledge form (accepted in Step 2).
- Notes:
  - Refinement within scope, found in the screenshots: the composer's Options row (Markdown, theme) pushed the pitch below the first screen and means nothing for a pledge, so the placement leaves it out. This adds one switch to the shared composer.
  - While a signed pledge posts, the composer's "Posting…" card appears briefly above the pledge form, inside the totals block, until the page moves to the new reply. It is the composer's usual behaviour and was left alone.
  - Estimate details: original scope as in Step 3; no revision. Retrospective slightly lower: the composer already worked per form, so only one lookup changed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 5 - Themes, replay at the reported size, documentation and closeout

**Original estimate: 0.08**

**Retrospective: 0.08**

- Changes:
  - [Pledge campaign](../../concepts/pledge_campaign.md) concept: the scenario uses the pledge form; a "Pledge form" entry replaces "Amount field"; a "Thread page" entry for the block, the statuses and hidden lines; the fold covers `filled: 100`; the replan's cuts replace "Not yet"; a source link for `PledgeLines`.
  - [Shared composer](../../concepts/shared_composer.md) concept: the pledge placement, its draft scope and the Options switch.
  - [Spec](../pledges_spec.md) Pages line, [checklist](../pledges_checklist.md), ledger closeout and this summary.
- Verification:
  - Passed: the browser check in all 17 themes at 375 and 1280 pixels on the final code (Stage 4's last run, after the last code change), and the `--count-unnamed` run.
  - Passed: a local replay of the demo thread's posts (goal 50; pledges 20, 15, 10, 30 and "a few"; a withdrawal, a full fill and `filled: 5`) at 375 by 667 pixels, the iPhone SE screen, with and without JavaScript. The first screen holds the title, "60 of 50 coffees pledged", Goal reached, the bar and the pledge form. No `goal:`, `pledge:`, `filled:` or `withdraw:` line shows; the cards read "Part delivered: 5 of 20", "Delivered" and "Open"; the withdrawal and fills are folded. The replay script was kept outside the repository.
  - Passed: `./v3 test --smoke PledgeCampaignTest WithdrawLeafFilterTest OfflineOutboxComposeTest ThreadActivityTest` (512 tests). The full suite was not run.
  - Passed: `git diff --check` over the cycle; concept, spec and checklist links resolve.
  - Final diff review: 25 files from `f381052a`. No schema, flag or dependency change. One new support class (`PledgeLines`), one composer switch, one Outbox lookup. Leftover: the fold no longer calls the directives' `isBare()`; their own tests still use them, so they stay.
  - Pending: the release check on zenmemes.com from the maintainer's iPhone SE (read the first screen; post and withdraw a pledge from the new form). The headless replay does not satisfy it.
  - Not exercised: an offline pledge through the Outbox; the paned layouts.
- Notes:
  - Estimate details: original scope as in Step 3; no revision.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Cycle retrospective

**Retrospective scope assessment: 0.70**

- Delivered scope: as approved in Steps 2 and 3, with four refinements inside it: the bar drawn in the ink on the card; the blank-line collapse; the unreadable reason under its line; the pledge form without Options. Correction to Step 2: there is no thread `.txt` view to check, so the post's own page stands in for it.
- Starting revision `f381052a`; planning commit `f0657102`; five stage commits. Stage retrospectives 0.14, 0.12, 0.08, 0.18, 0.08, not summed.
- Corrective work inside the cycle: two extra theme runs for the bar colours; browser expectations reworded for the new wording; one leftover "Post reply" press. No new requests.
- Maintainer comfortable-fit judgment: pending. Release check: pending.
- Single exposed rater Claude (claude-opus-5-5), 2026-11-17 UTC; R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
