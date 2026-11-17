> **Feature plan:** [Step 1](./pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_frontend_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_frontend`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fill_review`. Replaces `v3/pledge_stages`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-17 (Option C). Step 2 approved by the maintainer 2026-11-17 ("approved step 2").

**Cycle estimate: 0.75**

# Step 2: Feature Description — Pledge frontend

Cycle 9 in [pledges_checklist.md](../pledges_checklist.md), the last planned pledge cycle. Target look: the cycle 1 [mock-up](https://claude.ai/artifact/GaT42e25otTieeNpiFawch). Base: `origin/main` at `fc368349`.

## Problem

On a phone, a campaign thread reads like raw records: the lines show as text, the totals sit under the pitch, the bar is a black slab, and pledging means finding a field at the bottom of the page.

Approach (Step 1): Option C. Restyle, hide the lines the reader used, fold action replies, add a status word, and put a pledge form in the totals block.

## User stories

- As a visitor on a phone, I want to see how far the campaign has got as soon as the thread opens, so that I know whether to pledge.
- As a visitor, I want to pledge from a form beside the totals, so that I do not hunt for a field under the replies.
- As a reader, I want each pledge to lead with its amount and one status, so that I can scan the list.
- As the campaign author, I want the thread to look like a campaign, not like the records behind it, so that I can share it.

## Core requirements

- With the `pledges` flag on, the totals block sits directly under the thread title, above the pitch. It shows the pledged sum against the goal, a bar in the theme's colours, and one status word: "Goal reached" once pledged reaches the goal, "Complete" once filled does.
- The totals block holds a pledge form: amount with the unit beside it, an optional note and a "Post pledge" button. Posting adds a pledge reply exactly as today's Pledge field does. The reply box at the bottom loses its Pledge field.
- On the thread page, a line the reader used (goal, pledge, filled, withdraw, confirm, dispute) is left out of the shown text. A line it could not use stays, with its reason.
- An action reply that took effect and has no other text folds into its pledge, including one with an amount, such as `filled: 5`.
- Each pledge card leads with its amount and one status label: Open, Delivered 5 of 20, Delivered, Confirmed, Disputed, Withdrawn, or Not counted with its reason. The existing buttons stay.

## Decisions on the Step 1 questions

Approval of Step 1 left both open; the suggested answers are used here, open to overrule.

1. **The goal line** is hidden in the opening post too. The totals block already says it.
2. **The count line** reads "3 pledges · 15 delivered" and adds "(5 confirmed)" only when something is confirmed and it differs from delivered.

## Smaller rules

- A pledge posted without JavaScript has no name, so it shows and does not count, as today. The form says nothing extra about this.
- The `.txt` view, the Reply page, paned layouts and the source keep every line as written.
- With the flag off, every thread page is unchanged.

## Delivery scope

Work type: application change.

Not in this cycle (cut or parked in the 2026-11-17 replan): stages beyond the status word, the cushion, markers, the banner and confetti, board rows, the agent text view, configuration.

## Completion boundary

- Entry: a campaign thread page, on a phone and on desktop, signed in or not.
- End-to-end outcome: on the demo thread at 375 pixels, the first screen shows the title, "60 of 50 coffees", the bar, "Goal reached" and the pledge form. No raw line appears in any post. A visitor types 5 and a note and presses Post pledge. The total becomes 65, and a card reading "5 coffees · Open" with the note joins the replies.
- Recovery: a refused or offline post keeps the amount and note in the form, shows the composer's usual failure line, and can be sent again. A wrong pledge is withdrawn as today.
- Release: with the flag on at zenmemes.com, Ilya opens the demo thread on his iPhone SE, reads the first screen, and posts and withdraws a pledge from the new form.

## Known user and environment

- The maintainer on an iPhone SE, on zenmemes.com. The reported mismatch: the thread "looks like crap" there. His next step is to open the demo thread. He verifies by reading the first screen and posting a pledge; a failed post can be sent again from the same form. Evidence: his check on the device. A pass in headless Chrome at 375 pixels does not satisfy it; that stays pending until he reports.

## Risks

- **Two reply composers on one page.** Impact: scripts that pick the first reply form act on the pledge form. Checked during this step: `outbox_compose.js` lists unsent replies above the first reply form it finds, and `inline_reply_form.js` binds every Reply link to each inline reply composer. Validate early: a browser check with both forms on the page, where a Reply link focuses the bottom box and unsent replies list there. Mitigation: give the pledge form its own placement mark and draft scope, as the calendar day page does, and make those two lookups skip it.
- **Hiding lines.** Impact: hiding them in the shared post renderer would also hide them on the Reply page, in Forte and in the `.txt` view. Validate early: a test that the thread page hides a used line and the `.txt` view keeps it. Mitigation: hide only on the campaign's thread page, from the reader's own list of used lines.
- **Seventeen themes.** Impact: the bar, label and form look broken in some themes, as the black bar does now. Validate early: screenshots of the restyled block in all 17 themes at 375 pixels, before the pledge form is built. Mitigation: theme variables only, as `pledge_totals.php` already uses.
- **Offline.** Impact: the offline reader mounts only the thread's reply form, so the pledge form is missing offline. Mitigation: accepted; offline, a pledge can still be typed as a `pledge:` line in the reply box. Recorded as a limitation.

## Shared component inventory

- Totals block (`templates/partials/pledge_totals.php`): moved and restyled; gains the status word and the pledge form. Not copied.
- Shared composer (`templates/partials/composer.php`, `reply_form.php`): the pledge form is a new placement of the reply composer with its own draft scope, as the calendar day page uses (`data-compose-draft-scope`). No hand-built form.
- Pledge field (`templates/partials/reply_form.php` lead-fields slot, `public/assets/pledge_field.js`): moves from the bottom reply box to the new placement.
- Amount on a reply (`templates/partials/pledge_amount.php`): reordered to lead with the amount and one status label.
- Fold (`src/ForumRewrite/ReadModel/WithdrawLeafFilter.php`): extended from bare lines to any effective action reply with no other text.
- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`): unchanged rules; may report which line each post used, for hiding.
- Thread page (`templates/pages/thread.php`, `templates/partials/thread_root_card.php`): place the block under the title.
- Single-form lookups (`public/assets/outbox_compose.js`, `inline_reply_form.js`): taught to skip the pledge placement.
- CSS (`public/assets/site.css` pledge rules): rewritten with theme variables. The deploy celebration is not used.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (the page, the form, hidden lines, the fold); [shared composer](../../concepts/shared_composer.md), changed (a new placement; the Pledge field moves). No new entry. No matching item in `docs/feedback/items/`.

## Simple user flow

1. A visitor opens the campaign on a phone and sees "60 of 50 coffees", the bar and "Goal reached".
2. Under it, they type 5 in the pledge form, add "Decaf, please" and press Post pledge.
3. The page shows 65, and their card leads with "5 coffees · Open" and the note.
4. Ilya, the author, marks it filled. The card reads "5 coffees · Delivered", and no `filled` card appears.

## Success criteria

- At 375 pixels, the first screen of a campaign shows the title, the sum, the bar, the status word and the pledge form, in all 17 themes.
- No used line appears as text on the thread page; every unused line still shows with its reason.
- A pledge posted from the new form counts the same as one posted from today's field, signed, unsigned and through the Outbox.
- Reply links, unsent replies and the phone reply dock still act on the bottom reply box.
- With the flag off, thread pages are unchanged.

## Enhancement requests

- **Request:** "prioritize the frontend and replan the epic, cut any unnecessary features." **Disposition:** changed scope; this cycle replaces `pledge_stages`. **Approval:** Step 1 approved. **Follow-up:** the checklist records the cuts.
- **Request:** "option c, please." **Disposition:** adopted in Step 1. **Approval:** Step 1 approved.

## Study snapshots

- Step 2 handoff: **0.75** reaffirmed, scope v2. The two single-form lookups add work; hiding lines on the thread page only, and leaving the offline reader alone, take some away. Baseline `fc368349`, R1, P2. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
