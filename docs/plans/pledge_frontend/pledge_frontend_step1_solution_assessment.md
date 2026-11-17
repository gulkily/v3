> **Feature plan:** [Step 1](./pledge_frontend_step1_solution_assessment.md) · [Step 2](./pledge_frontend_step2_feature_description.md) · [Step 3](./pledge_frontend_step3_development_plan.md) · [Step 4](./pledge_frontend_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_frontend_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_frontend`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment. Parent: `v3/pledge_fill_review`. Replaces `v3/pledge_stages`, which was dropped before its Step 1 was approved.

Extended estimates: enabled

Status: Step 1 written 2026-11-17. The maintainer chose Option C ("option c, please"); revised to match. Approved by the maintainer 2026-11-17 ("approved step 1"). The maintainer approves every step in this cycle.

**Cycle estimate: 0.75**

# Step 1: Solution Assessment — Pledge frontend

Cycle 9 in [pledges_checklist.md](../pledges_checklist.md), which was replanned with this step. Target look: the cycle 1 [mock-up](https://claude.ai/artifact/GaT42e25otTieeNpiFawch), without the parts now cut. Base: `origin/main` at `fc368349`.

## Original Query

The session's first request asked for a staged cycle called pledge_stages: Open, Fully pledged, Cushioned and Complete in the header, the Fully pledged celebration, marker posts and the optional `cushion:` line. Step 1 was written for it and recommended splitting the markers into their own cycle. The maintainer then wrote:

> this epic is getting out of control, it was supposed to be an mvp, and the frontend still looks like crap. let us prioritize the frontend and replan the epic, cut any unnecessary features.
>
> https://zenmemes.com/threads/thread-20261117061140-37ee2928 (for ui example)

## Understood Intent

Finish the MVP by making the campaign thread look right on a phone, and stop adding mechanics. The replan in the checklist cuts the stages, cushion, markers, celebration and configuration cycles. The only part of stages that survives is a status word in the totals block.

## Problem

On the demo thread at 375 pixels (and in all 17 themes locally), the thread reads like raw records, not a campaign:

- The raw lines show as text in every post: `goal: 50 coffees`, `pledge: 20`, `filled: 5`.
- The totals block sits at the bottom of the opening post, under the pitch and the tags.
- The bar is a black slab. 60 of 50 looks almost the same as 50 of 50, and delivered amounts are not on it.
- "3 pledges · 15 filled · 0 confirmed" reads like a database row.
- On a pledge card the amount comes after the note, and "Delivered by claude · 5" does not say 5 of what.
- A reply such as `filled: 5` stays a full card, because only a reply with the line alone folds.
- The pledge field sits at the very bottom, joined to the reply box, with no Pledge button.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), in every option.

No matching item in `docs/feedback/items/`.

## Option A — Restyle in place

**Estimate: 0.30**

Change CSS and the order of what the templates already draw. Move the totals block directly under the title. Give the bar theme colours, with delivered and pledged as two shades and a goal mark. Put the amount first on each pledge card, with one status label: Open, Delivered 5 of 20, Withdrawn, Not counted.

- Smallest. Changes no rules, so every check from cycles 1 to 8 stays as it is.
- The raw lines and the `filled: 5` cards stay, so the page still reads like records.
- Pledging is still a hunt for the field at the bottom.
- Estimate details: driver: the bar and the label in 17 themes at 375 pixels; uncertainty: low.

## Option B — Option A, plus the page stops showing records

**Estimate: 0.50**

Option A, plus four changes:

- A line the reader used (goal, pledge, filled, withdraw, confirm, dispute) is left out of the shown text. An unread line, such as `pledge: a few`, stays, with its reason.
- Any action reply that took effect and has no other text folds into its pledge, including `filled: 5`.
- The totals block shows one status word: "Goal reached" once pledged reaches the goal, "Complete" once filled does. No banner, animation or stored state.
- An "Add a pledge" link in the totals block jumps to the amount field and works without JavaScript.

What it adds:

- The page matches the mock-up's main parts with the one reply form that exists today.
- Keeps everything derived, so nothing is stored.
- Hiding lines changes how post text is shown, but only on campaign threads with the flag on. The `.txt` view and the source keep the lines.
- Estimate details: driver: hiding the lines on the thread page only, without touching the shared post renderer elsewhere; uncertainty: the fold change's effect on cycle 2 and 8 tests.

## Option C — Option B, plus a separate pledge form

**Estimate: 0.75**

Option B, plus the mock-up's own pledge form in the totals block: amount, note and a Post pledge button, apart from the reply box.

- The cleanest pledging on a phone.
- A second composer on the page. Signing, Outbox and the no-JavaScript post would all need checks a second time. That is the kind of growth this replan is cutting.
- Estimate details: driver: a second composer instance; uncertainty: the shared composer's assumptions about one form per page.

## Recommendation

Option B. It fixes everything on the problem list with one reply form, adds no new mechanics, and leaves a thread that looks like the mock-up. The "Add a pledge" link covers most of what Option C's form would give.

## Revised direction — Option C (maintainer's choice, 2026-11-17)

The maintainer chose Option C over the recommendation. The cycle builds all of Option B, plus a pledge form in the totals block: amount, optional note and a Post pledge button. The reply box stays at the bottom for ordinary replies, and its Pledge field goes away, so a pledge has one obvious place.

- The form is the shared composer in a new placement with its own draft scope, as the calendar day page already does. It is not a second, hand-built form, so signing, Outbox and the no-JavaScript post work the same way.
- The "Add a pledge" link from Option B is no longer needed and is dropped.
- First risk for Step 2: page scripts that assume one reply form per thread page, such as the phone reply dock and the offline reader. Check them before planning further.
- Still under 1.0, but the largest pledge cycle. If Step 3 needs more than five stages, split the form into its own cycle.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

Questions for Step 2:
- Hide the goal line in the opening post too, or show it as a small code chip, as the mock-up does? Suggested: hide it; the totals block already says it.
- Keep the "confirmed" share in the block? Suggested: show "15 delivered" and add "(5 confirmed)" only when something is confirmed.

Enhancement requests:
- "Prioritize the frontend and replan the epic, cut any unnecessary features" (2026-11-17). Disposition: changed scope. The pledge_stages Step 1 was dropped before approval and this cycle replaces it; the checklist and spec record the cuts. Approval: pending `Approved Step 1`.
- "option c, please" (2026-11-17). Disposition: direction chosen; Step 1 revised to Option C. Approval: pending `Approved Step 1`.

## Study snapshots

- Intake (after reading the thread page, the totals and amount partials, the fold, the prod demo thread at 375 and 1280 pixels, the local check in 17 themes and the mock-up; no pristine intake): 0.50 projected total for Option B. Baseline `fc368349`, scope v1, R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.
- Step 1 handoff: 0.50 reaffirmed. Option estimates A 0.30, B 0.50, C 0.75, not summed. R1 hashes not re-verified in this session.
- Step 1 revision (2026-11-17): **0.75**, scope v2. Changed scope, not new understanding: the maintainer chose Option C, so the projection follows Option C's estimate. The "Add a pledge" link is dropped and the reply form's Pledge field moves into the new form. Same rater and exposure.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
