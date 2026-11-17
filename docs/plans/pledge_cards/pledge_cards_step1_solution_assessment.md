> **Feature plan:** [Step 1](./pledge_cards_step1_solution_assessment.md) · [Step 2](./pledge_cards_step2_feature_description.md) · [Step 3](./pledge_cards_step3_development_plan.md) · [Step 4](./pledge_cards_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_cards_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_cards`; [ledger](../effort_ledger/pledge_cards.md). Standing P2 enrollment. Parent: `v3/pledge_frontend`.

Extended estimates: enabled

Status: Step 1 written 2026-11-17, revised four times the same day with the maintainer, and rewritten clean. Approved by the maintainer 2026-11-17 ("approved step 1"). The maintainer approves every step in this cycle.

**Cycle estimate: 0.65**

# Step 1: Solution Assessment — Pledge cards

Cycle 10 in [pledges_checklist.md](../pledges_checklist.md). Builds on cycle 9, [pledge frontend](../pledge_frontend/pledge_frontend_step4_implementation_summary.md) ([PR #313](https://github.com/gulkily/v3/pull/313), merged). Base: `origin/main` at `964966c1`.

## Original Query

The maintainer's messages, in order:

1. "this is great, and i want to iterate on the comments, because they look very busy."
2. "My main concern is all the buttons on each card, perhaps we can hide all the less relevant ones unless the item itself is opened? Or allow expanding them?"
3. "ok, i don't mind your more/... design, it just still looks like a lot of things on the cards that's kind of overwhelming. perhaps the pledges can be just collapsed down to the bare minimum so that people can just see that they're there? this will also help us show that there are a lot of pledges?"
4. "only approved users' pledges should appear, is that how it is now? / reply goes inside more, yes / compact cards for replies, yes" and "i think we made it a feature flag, just trying to cut down on complexity"
5. "does all of this make sense or did we make some questionable choices?"
6. On the "Waiting for approval" row: "This sounds good to me."

## Understood Intent

Make a campaign thread calm: each pledge is a single line that shows who pledged what and where it stands, so a reader sees how many pledges there are. Buttons appear only when someone opens an item. Only approved members' pledges count. Someone not yet approved still sees that their pledge arrived.

## Problem

On a phone, a pledge card today has a two-line byline, a two-line row of six actions (three of them from the `#meta` label's tagset), and, for the author, three review buttons one per line. Seven pledges fill several screens, so a campaign with many pledges looks like a long argument. Separately, any signed pledge counts today, approved or not. Cycle 1 decided that under delegated approval, and the maintainer now overrules it.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed in every option.

No matching item in `docs/feedback/items/`.

## Option A — One row per pledge, opening to the full pledge (recommended)

**Estimate: 0.65**

- **Rows.** On a campaign thread, each pledge reply is one row: name, amount, status ("5 of 20", Delivered, Open, Withdrawn). Pledge rows next to each other join into one list. Other replies stay cards and break the list where they sit.
- **Opening.** A row is the thread's existing fold, the disclosure that already folds resolved posts. It opens without JavaScript to the note, the fill lines and a short header. The buttons sit behind "More", Reply included.
- **Compact cards.** Ordinary replies on a campaign thread get the same short header and "More".
- **Approved only.** A pledge counts only when its author's key is approved and its attribution counts. Approval is read when the thread is shown, so a pledge starts counting the moment its key is approved. The same rule covers fills, withdrawals and reviews.
- **Waiting rows.** A pledge from a key that is not approved shows as a row marked "Waiting for approval", outside the total. Its pledger sees that it arrived, and nobody mistakes it for counted.
- **One flag less.** `FORUM_PLEDGES_COUNT_UNNAMED_ENABLED` is removed. A post with no name cannot be approved, so the flag can no longer do anything. The unverified and unnamed states fold into "Waiting for approval".

Trade-offs:

- A browser mock with seven pledges fit six rows in about the height of one of today's cards.
- Every action is one press further away. The reply box at the bottom still replies in one press.
- Notes are hidden until a row is opened.
- Campaign threads look different from other threads until compact cards go site-wide.
- Estimate details: driver: joining rows across continuation runs and folded action replies, plus the approval rule's tests; uncertainty: the fold's existing rules for resolved and claimed posts.

## Option B — Full cards with a short header and "More"

**Estimate: 0.45**

Pledges stay as cards, but each gets a one-line header with the status and has its buttons behind "More". This includes the approved-only rule and waiting state as in Option A.

- Notes stay visible, and every card is shorter.
- The maintainer judged the mock still "a lot of things on the cards", and many pledges still read as a long column.
- Estimate details: driver: the header and "More" on pledge cards; uncertainty: low.

## Option C — Option A, with compact cards on every thread

**Estimate: 1.00**

Option A, plus the short header and "More" on every reply on every thread.

- One look across the site, without a campaign-only variant.
- Changes the site's main component on every page and in every theme, and it is outside the pledge epic. Better as its own cycle once the campaign version has been used.
- Estimate details: driver: every post card consumer and its tests; uncertainty: high.

## Recommendation

Option A. It does what the maintainer asked in messages 3, 4 and 6, and keeps every action one press away. To keep the campaign-only variant small, the compact card should be one switch on the post card, not a second template. Going site-wide (Option C) can then be its own small cycle.

Decided by the maintainer: Reply goes inside "More"; ordinary replies on campaign threads get compact cards; unapproved pledges show as "Waiting for approval" rows outside the total.

Questions for Step 2:
- Campaign threads only for now, or site-wide compact cards in this cycle (Option C)? Suggested: campaign threads only; site-wide next.
- How does the author see a delivery awaiting review on a closed row? Suggested: no special sign on rows; the status reads "Delivered", and the review buttons are inside the opened row. This avoids viewer-specific rows.
- Does a row show the first words of the note ("mara · 20 · I'll order these…")? Suggested: no; the note is one press away.
- The compact header drops "(unapproved)". Suggested: yes on campaign threads; the waiting row already says it for pledges.
- The opening post's action row. Suggested: leave it for the site-wide cycle.

## History of this step

Earlier versions recommended a "…" menu (0.45), then hiding buttons behind "More" with pending reviews left out (0.45), then one row per pledge (0.50), then adding the approved-only rule (0.60). A tap-the-card variant (0.60) was dropped because keyboard and screen-reader users still need a real control. The maintainer then chose the waiting row (+0.05), and this clean version replaced the stacked revisions. Each version was shared in the chat; the ledger keeps their estimates.

Enhancement requests: messages 2 to 6 changed the direction within this cycle's request. Disposition: adopted as Option A. Approval: pending `Approved Step 1`.

## Study snapshots

- Intake (after rendering the thread at 375 pixels as visitor and author, reading `post_card.php` and the pledge partials, and mocking options in the browser; no pristine intake): 0.45 for the first recommendation. Baseline `feat/pledge-frontend` at `b7e99459`, then `origin/main` at `964966c1` after PR #313 merged; R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.
- Step 1 revisions: 0.45 (v1), 0.50 (v2, rows), 0.60 (v3, approved only), **0.65** (v4, waiting rows). Changed scope each time, not new understanding. Option estimates in this version: A 0.65, B 0.45, C 1.00, not summed. R1 hashes not re-verified in this session.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
