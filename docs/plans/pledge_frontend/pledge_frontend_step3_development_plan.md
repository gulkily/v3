> **Feature plan:** [Step 1](./pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_frontend_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_frontend`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fill_review`. Replaces `v3/pledge_stages`.

Extended estimates: enabled

Status: Steps 1 (Option C) and 2 approved by the maintainer 2026-11-17. Step 3 approved by the maintainer 2026-11-17 ("approved step 3").

**Cycle estimate: 0.75**

# Step 3: Development Plan — Pledge frontend

## Completion Contract

- Entry: a campaign thread page with `FORUM_PLEDGES_ENABLED` on, on a phone and on desktop, signed in or not.
- End-to-end outcome: at 375 pixels the first screen shows the title, the sum against the goal, a themed bar, the status word and the pledge form. No used line shows in any post. Action replies with no other text are folded. Each pledge card leads with its amount and one status label. A pledge posted from the form counts like one from today's field.
- Recovery: a refused or offline post keeps the amount and note in the pledge form and can be sent again. A wrong pledge is withdrawn as today. Turning the flag off returns every page to what it is now.
- Deployment: no schema change, no rebuild, no new flag, no new dependency.
- Release condition: on zenmemes.com, Ilya opens the demo thread on his iPhone SE, reads the first screen, and posts and withdraws a pledge from the new form. Not possible from this session; it stays pending in Step 4.

## Findings from the checks Step 2 asked for

Checked by reading the code at `fc368349`.

- **Two composers.** `inline_reply_form.js` binds only composers inside `[data-inline-reply-compose]`, so a pledge form placed outside that wrapper is left alone. `pledge_field.js`, the phone dock and draft saving all work per form. Draft scope is a form attribute (`data-compose-draft-scope`), read by `browser_signing.js`. One lookup picks the first reply form on the page: `showUnsentReplies()` in `outbox_compose.js`. That one needs to skip the pledge form.
- **No-JavaScript posting.** The server writes `pledge: N` from the `pledge_amount` field in `ComposeAndAccountKeyController`, whichever form sent it. The pledge form needs no server change.
- **Hiding lines.** The root card already passes a display body to `$renderPostBody()`, and `post_card.php` can do the same. The thread page is the only place `$pledgeCampaign` is set, so hiding there leaves the Reply page, Forte and the `.txt` view alone.

## Key Risks

- **High risk: a pledge from the new form is lost or posted twice.** Impact: pledgers lose trust in the one new control. Early validation: Stage 4 runs the existing browser check unchanged first. It then adds a signed post, a failed first press and a no-JavaScript post from the pledge form. Mitigation: reuse `reply_form.php` and the composer unchanged, with its own draft scope.
- **Hiding a line that did not count.** Impact: a reader cannot see why a pledge is not counted. Early validation: Stage 2 tests an unreadable pledge, an uncounted withdraw and an uncounted review keep their lines. Mitigation: hide only lines the reader used.
- **Seventeen themes.** Impact: the restyled block looks broken somewhere. Early validation: Stage 1 takes 375-pixel screenshots in all 17 themes before anything builds on the block. Mitigation: theme variables only.

## Stage 1

**Estimate: 0.14**

- Goal: the totals block sits under the title and looks like the mock-up's totals.
- Dependencies: none.
- Expected changes: `thread_root_card.php` draws `pledge_totals.php` right after the title. The bar shows delivered in the theme's strong colour and the rest of pledged in a lighter shade, with a goal mark once pledged passes the goal. The block shows "Goal reached" when pledged reaches the goal and "Complete" when filled does. The count line reads "3 pledges · 15 delivered", with "(5 confirmed)" only when it differs. `PledgeCampaign::fromPosts()` gains `delivered_percent` for the bar; no rule changes. Pledge rules in `site.css` rewritten with theme variables.
- Verification approach: `PledgeCampaignTest` render checks for the status word, the count line and its order under the title. Screenshots in all 17 themes at 375 pixels, looked at. Smoke set.
- Estimate details: driver: the bar in 17 themes; uncertainty: themes with few colour variables.
- Risks or open questions:
  - Impact: the bar has no contrast in a theme. Early warning: the screenshots. Mitigation: fall back to `--ink` and `--line`, as today.
- Canonical components/API contracts touched: `pledge_totals.php` (moved and extended), `thread_root_card.php`, `PledgeCampaign::fromPosts()` result (one field added), pledge CSS in `site.css`.

## Stage 2

**Estimate: 0.14**

- Goal: the thread page stops showing records.
- Dependencies: Stage 1.
- Expected changes: a small helper, `PledgeLines::hide(string $body, string $kind): string`, removes a used line (goal, pledge, withdraw, filled, confirm, dispute) from the text shown. `PledgeCampaign` reports which posts' lines were used. `thread_root_card.php` and `post_card.php` pass the hidden-line body to `$renderPostBody()` on the thread page only. `WithdrawLeafFilter` folds any effective action reply whose only text is its line, including `filled: 5`.
- Verification approach: `PledgeCampaignTest`: used lines hidden on the thread page; an unreadable pledge, an uncounted withdraw and an uncounted review keep theirs; the `.txt` view keeps every line. `WithdrawLeafFilterTest`: `filled: 5` folds; one with a note stays.
- Estimate details: driver: keeping hiding off every page but the thread page; uncertainty: Markdown posts.
- Risks or open questions:
  - Impact: a hidden line leaves an empty post. Early warning: a pledge with no note in the test. Mitigation: the card still shows its amount and label.
- Canonical components/API contracts touched: `PledgeCampaign` (reports used lines), `WithdrawLeafFilter` (extended), `$renderPostBody()` (reused with a display body), directive readers (reused).

## Stage 3

**Estimate: 0.10**

- Goal: each pledge card leads with its amount and one status label.
- Dependencies: Stage 2.
- Expected changes: `pledge_amount.php` draws the amount first, then one label: Open, Part delivered 5 of 20, Delivered, Withdrawn or Not counted with its reason. Fill lines with their review labels and the existing buttons stay under it. `post_card.php` places the block above the note.
- Verification approach: run the browser check unchanged first; then `PledgeCampaignTest` render checks for each label; screenshots at 375 pixels in 3 themes.
- Estimate details: driver: the label rules over fills and reviews; uncertainty: little.
- Risks or open questions:
  - Impact: existing withdraw, fill and review buttons move or break. Early warning: the unchanged browser run. Mitigation: keep their forms and `pledge_withdraw.js` unchanged.
- Canonical components/API contracts touched: `pledge_amount.php` (reordered), `post_card.php`, `pledge_withdraw.js` (reused unchanged).

## Stage 4

**Estimate: 0.20**

- Goal: a visitor pledges from a form in the totals block.
- Dependencies: Stages 1 and 3.
- Expected changes: `pledge_totals.php` includes `reply_form.php` as a pledge placement: the amount field with the unit, a short note box, "Post pledge", and draft scope `pledge`. No phone dock and no inline-reply wrapper. The bottom reply box loses its Pledge field. `showUnsentReplies()` in `outbox_compose.js` skips the pledge placement.
- Verification approach: browser check unchanged first. Then: a signed pledge from the form counts; a failed first press keeps amount and note and sends again; a no-JavaScript pledge posts and shows "Not counted: no name"; a Reply link focuses the bottom box; drafts in the two forms do not mix. Smoke set.
- Estimate details: driver: the browser check for two composers; uncertainty: the reply dock on a phone.
- Risks or open questions:
  - Impact: a pledge is lost or posted twice. Early warning: the failed-press check. Mitigation: the composer's existing retry, unchanged.
  - Impact: unsent replies show above the pledge form. Early warning: an Outbox check with an unsent reply. Mitigation: the skip in `outbox_compose.js`.
- Canonical components/API contracts touched: `reply_form.php` and `composer.php` (new placement, no copy), `pledge_field.js` (reused), `outbox_compose.js` (one lookup changed), `ComposeAndAccountKeyController` `pledge_amount` (reused unchanged).

## Stage 5

**Estimate: 0.08**

- Goal: checked in every theme, replayed at the reported size, and documented.
- Dependencies: Stage 4.
- Expected changes: [pledge campaign](../../concepts/pledge_campaign.md) and [shared composer](../../concepts/shared_composer.md) concept entries; the spec, checklist, ledger and debug log; the Step 4 closeout.
- Verification approach: the browser check in 17 themes at 375 and 1280 pixels, plus `--count-unnamed`. A replay of the demo thread's posts in the fixture, looked at at 375 by 667 pixels, the iPhone SE screen. Smoke set with `PledgeCampaignTest` and `WithdrawLeafFilterTest`; `git diff --check`; review the whole diff. The full suite is not run. The iPhone SE check stays pending for the maintainer.
- Estimate details: driver: 34 theme passes; uncertainty: browser availability.
- Risks or open questions:
  - Impact: the headless pass is taken as the release check. Early warning: none needed. Mitigation: the closeout lists the iPhone SE check as pending.
- Canonical components/API contracts touched: concept inventory (updated).

Concepts touched: pledge campaign and shared composer, changed. No new entry. No matching item in `docs/feedback/items/`.

## Branch

Planned on `origin/main` at `fc368349`. After `Approved Step 3`, Step 4 cuts `feat/pledge-frontend` from a fresh `origin/main` and makes the planning commit first.

## Study checkpoint

- Step 3 handoff: **0.75** reaffirmed, scope v2. Five stages, inside Step 1's limit for keeping the form in this cycle. Stage work 0.14, 0.14, 0.10, 0.20, 0.08, not summed; Stage 5 owns shared verification and documentation. Baseline `fc368349`, R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
