# Pledge campaigns on v3: spec (v1 draft)

Status: draft for discussion, 2026-11-15; code check added 2026-11-16. Cycles are tracked in [pledges_checklist.md](./pledges_checklist.md). Working name: "pledges". Feature flag: `pledges`.

## Summary

A campaign is a thread that states a goal in a unit the author picks ("1000 coffees"). People reply with pledges, each a number in that unit. No money moves through the site. The thread shows a running total, and the author marks pledges filled as they come through. Pledges can be withdrawn. Going over the goal is allowed and expected, because some pledges will not be filled.

## Checked against v3 (2026-11-16)

This draft was written before reading the code. A first read of v3 at `9e5aa010` found:

- **Directive lines fit.** `theme:`, `event:` and `title:` lines are already read from post bodies when a thread is shown, with nothing stored. `goal:` and `pledge:` can follow them.
- **Withdrawal cannot be a plain retraction.** Retraction deletes the post's prose and leaves a deletion notice. A retracted pledge would stop counting, but it would not stay visible as "withdrawn". Withdrawal needs its own mark; the reply states from tagsets (claimed, resolved, closed) are the closest existing mechanism. To be settled in the `pledge_withdraw` cycle.
- **Fill marks have a precedent.** Tagset states are reaction records on a reply, and the latest mark from an approved key decides. Delivered, confirmed and disputed can likely reuse that, with a wider rule for who may mark.
- **A celebration exists.** The deploy celebration already shows confetti and a banner. The Fully pledged moment should reuse it.

The sections below are unchanged from the draft; where they disagree with this list, this list is newer.

## What is decided

- A campaign is a thread; a pledge is a reply.
- The campaign author sets the unit. Every pledge is a number in that unit, so the total is a plain sum.
- Pledging transfers nothing. How a pledge gets paid is settled later, outside the site (pay directly, order 300 coffees yourself, and so on).
- A pledge can be withdrawn.
- Flaking is handled by oversubscribing, so there is no cap at the goal.
- A pledge can be marked filled.
- A campaign is complete when fills reach the goal, not when pledges do.
- Pledges reaching the goal is still a milestone worth marking (see Stages).
- Campaigns and pledges are written as plain `goal:` and `pledge:` lines.
- Withdrawal is a `withdraw` line in a reply to the pledge, made by the pledger. Retracting that reply undoes it. (Reworded 2026-11-16 after `pledge_withdraw` Step 1; the draft said "reuses the existing retraction method", which deletes the pledge's text.)
- Pledgers can mark their own pledges delivered. That counts right away; the author can confirm or dispute it. The author and operators can also mark fills directly. Partial fills add up.
- When a campaign is oversubscribed, the author decides whose pledges to call on. That is the default. The calling method is a configurable choice (see Configuration).
- The word is "pledge" for now. The name may be revisited, and may become a configurable choice.
- Fully pledged is a big celebration moment, and the thread makes it obvious that pledges are still being accepted.
- A pledge that can't be read as a number is kept as an ordinary reply.

## Concepts

**Campaign.** A thread whose first post carries a goal line:

    goal: 1000 coffees

Number first, then the unit as free text. The rest of the post is the pitch, written as usual. A thread with a goal line is a campaign; nothing else changes about it. It still takes ordinary replies, likes, tags and themes.

**Pledge.** A reply in a campaign thread that carries a pledge line:

    pledge: 300

The rest of the reply is an optional note ("I'll order these from the roaster myself"). A reply without a pledge line is ordinary discussion. One person may pledge more than once; each pledge is its own record and they add up.

**Withdrawal.** A reply to a pledge, by the pledger, carrying the line `withdraw`. The pledge stays visible, shown as withdrawn, and stops counting. Retracting the reply undoes it. (Reworded 2026-11-16; see [pledge_withdraw Step 1](./pledge_withdraw/pledge_withdraw_step1_solution_assessment.md).)

**Fill.** A record that targets a pledge and says it was delivered. A fill may carry a number for a partial fill ("filled: 200" against a pledge of 300); with no number it fills the whole pledge. Who made it decides how it is labelled:

- By the pledger: "Delivered (reported by mara)". It counts toward the filled total immediately.
- By the campaign author or an operator: "Filled (confirmed by ilyag)".

**Confirm.** The author's record on a pledger-reported fill. It changes the label to confirmed and nothing else.

**Dispute.** The author's (or an operator's) record on a pledger-reported fill, saying it did not arrive. The fill stops counting. The fill and the dispute both stay visible. A dispute can itself be retracted.

The pledger is the one who knows whether they delivered, so an author who goes quiet cannot freeze a campaign. The label always says who made the claim, so a reader can tell a campaign that is complete and confirmed from one that is complete on self-reports.

All of these are plain text records in the Git repo, the same as any other post. The visible-line format follows the `theme:` line precedent, so campaigns and pledges can be written from any client, including with no JavaScript and by agents.

## Totals

Computed from the records, never stored as the source of truth:

- **Pledged** = sum of pledges that are not withdrawn
- **Filled** = sum of fills that are not disputed or retracted (pledger-reported and confirmed alike)
- **Confirmed** = the part of filled that the author or an operator has confirmed or marked directly
- **Open** = pledged minus filled
- **To go** = goal minus filled (never below zero)

The thread header shows goal, pledged and filled. Pledged may exceed the goal ("1300 of 1000 pledged").

## Stages and milestones

A campaign has two numbers that can each reach the goal, and each gets its own moment.

| Stage | When | What the thread shows |
|---|---|---|
| Open | pledged is under the goal | "620 of 1000 coffees pledged" |
| Fully pledged | pledged reaches the goal | Header reads "Fully pledged"; the bar reaches the goal mark |
| Cushioned (optional) | pledged reaches the author's margin, e.g. 120% | "Cushioned: 1200 of 1000 pledged" |
| Complete | filled reaches the goal | Header reads "Complete"; board row shows a Complete badge |

Fully pledged is the celebratory stage: the crowd has said yes. Complete is the end: the thing has arrived. Keeping them apart means a campaign never looks finished while the author is still short, and people keep pledging past the goal, which is the cushion against flaking.

**The Fully pledged moment.** This is the big one and the page should show it: a celebration banner at the top of the thread, the bar visibly hitting the goal mark, and the marker post in the replies. The pledge box stays exactly where it was, with a line directly under the banner saying the campaign is still open, for example "Still taking pledges. Anything past the goal is the cushion." Nothing about the celebration may read as closed: no greyed-out controls, no "ended" wording.

**Complete, by whom.** When fills reach the goal the header reads "Complete", with the confirmed share beside it ("Complete: 700 of 1000 confirmed") until everything is confirmed.

**Marker posts.** When a stage is first reached, the thread gets a visible marker at that point in the replies, naming the pledge or fill that did it ("Goal reached with okafor's pledge of 400"). Markers are derived from the records, not stored separately.

**Smaller beats.** The first pledge gets a marker. The bar shows quarter, half and three-quarter marks; passing one is shown on the bar but does not add a post.

**Losing a stage.** If withdrawals take pledged back under the goal, the header returns to the plain count ("900 of 1000 pledged"). The marker post stays in the thread as history. If the goal is reached again, a new marker is added; the old one is not reused or removed.

**Cushion margin.** Optional, set by the author on its own line in the first post:

    cushion: 1200

Same unit as the goal. With no cushion line, the Cushioned stage is skipped.

## Rules

1. **Nothing is rejected.** A pledge line that can't be read as a number ("pledge: a few") is kept as an ordinary reply and the pledger sees a note that it was not counted. A pledge posted in a non-campaign thread is also just a reply.
2. **Records add up; they don't overwrite.** Changing a pledge means withdrawing it and posting a new one. A second fill on the same pledge adds to the first, up to the pledge amount.
3. **A fill can be undone** by retracting the fill record, the same way any record is retracted.
4. **Withdrawing a filled pledge** only withdraws the unfilled part. What was delivered stays counted. A fully filled pledge cannot be withdrawn. Fills are read before withdrawals, so reply order does not change the result. (Added 2026-11-16 with [pledge_fills](./pledge_fills/pledge_fills_step3_development_plan.md).)
5. **Who may pledge:** anyone who may reply in the thread.
6. **Who may fill:** the pledger, for their own pledge; the campaign author and operators, for any pledge. Only the author and operators may confirm or dispute.
7. **The goal line can't be silently changed.** A changed goal is a new visible record in the thread, so pledgers can see what they signed up for.

## Pages

- **Campaign thread.** The normal thread page with a summary block under the first post: goal, pledged, filled, a bar, and a pledge box (one number field, one optional note, one button). Each pledge reply shows its amount and state: open, filled, part filled, or withdrawn.
- **Board.** A campaign's row shows a compact "620 / 1000 coffees" next to the title.
- **Text view.** `<thread-url>.txt` includes the goal line, the totals, and each pledge with its state, so agents can read and pledge without scraping HTML.
- **User page (later).** Pledged and filled counts per username.

URLs stay the ordinary thread URLs. No new opaque state.

## Configuration

Two choices are meant to be configurable, in keeping with v3's feature-flag approach. Whether each is set per instance, per campaign, or both is still open.

**Calling method.** How pledges are called on when a campaign is oversubscribed.

| Method | What it does |
|---|---|
| Author decides (default) | No ordering. The author asks whoever they like; fills show what happened. |
| First come, first served | Pledges are called in the order they were made until the goal is covered. Later ones are shown as "in reserve" and move up if an earlier one is withdrawn or disputed. |
| Scaled | Every pledger is asked for the same share of their pledge (goal divided by pledged). |

A per-campaign setting would be one more line in the first post, for example `calling: first-come`.

**Wording.** The noun shown for a pledge ("pledge", "commitment", or an instance's own word). The record format keeps the `pledge:` line whatever the display word is, so threads stay portable between instances.

## Not in v1

- Payments, escrow, or any handling of money
- Deadlines and "only call pledges if the goal is reached"
- Mixed units or conversion between them
- Calling methods other than "author decides" (the setting exists from the start; the other methods come later)
- Reminders and notifications to pledgers
- Anonymous pledges

## Open questions

1. **Quiet campaigns.** A campaign where nobody reports anything just sits. Suggested: no mechanism, only show "last activity 40 days ago" so the staleness is visible.
2. **Closing a campaign.** Does the author need a way to stop new pledges, or does it stay open like any thread? The existing closed/resolved reply states might cover it.
3. **Whole numbers only?** Coffees suggest yes; dollars or hours suggest decimals. Suggested: allow decimals, display as written.
4. **Where configuration lives.** Calling method and wording: per instance, per campaign, or an instance default that a campaign can override?
5. **What the celebration looks like.** Decided that it is big; the actual design (banner, animation, colour) is still to be drawn. It has to work without JavaScript.

## Smallest build

1. Read `goal:` on a thread's first post and `pledge:` on replies; show the summary block and per-reply amounts.
2. Pledge box on the thread page.
3. Withdraw, via a `withdraw` reply line.
4. Fill and partial fill: pledger self-report, author confirm and dispute, direct fills by author and operators.
5. Board row total and `.txt` output.

6. Stages in the header, marker posts, and the optional `cushion:` line.

Steps 1 and 2 alone make a usable campaign; 3 and 4 make it honest; 6 makes it satisfying.
