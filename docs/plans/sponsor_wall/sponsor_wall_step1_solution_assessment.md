> **Feature plan:** [Step 1](./sponsor_wall_step1_solution_assessment.md) · [Step 2](./sponsor_wall_step2_feature_description.md) · [Step 3](./sponsor_wall_step3_development_plan.md) · [Step 4](./sponsor_wall_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./sponsor_wall_debug_log.md))

Effort study: `railspike-effort` / `v3/sponsor_wall`; ledger `../effort_ledger/sponsor_wall.md` (pending, not yet written). Standing P2 enrollment. Parent: the pledge epic, [pledges_checklist.md](../pledges_checklist.md).

Extended estimates: enabled

Status: Step 1 written 2026-11-27. Approved by the maintainer 2026-11-27 ("approved step 1"), Option A. The maintainer approves every step in this cycle.

**Cycle estimate: 0.60**

# Step 1: Solution Assessment — Sponsor wall

Base: `origin/main` at `a542d1e`.

## Original Query

The maintainer, across two chats (2026-11-24 to 27):

1. A sponsors page modeled on pequalnp.com/sponsors, as a standing "Support ZenMemes" campaign. One unit is $100, drawn as a 50-square wall, for a $5,000 goal. Open pledges are shown too.
2. "a guest username is not ok for pledging"; an unapproved username shows "Pending".
3. "sticking with $100 per square for now"; a claimed square shows a small badge, the sponsor's name and a link to their pledge.
4. "how do we enable people to fund more than one square at a time? it feels like right now we're leaving money on the table"
5. "please go ahead" (on sketching the wall's rules).

## Understood Intent

One page where a visitor sees how much of the $5,000 is covered and by whom, and can cover more than one square at once. The wall is a picture of the campaign the site already counts. It adds no new way to pledge.

## Problem

Pledges already take any amount: `pledge: 3` is three units, `pledge: 0.5` is half of one (`PledgeAmount`). What is missing is the wall, which has no rule for a pledge bigger or smaller than one square, for open, delivered, pending or withdrawn pledges, or for going past 50.

One naming clash: "sponsor" already means vouching for a new member's key in account approval (`FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL`, its runbook and tests). Calling pledgers "sponsors" would give the word two meanings.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md) (a new view of it).

No matching item in `docs/feedback/items/`.

## Wall rules (shared by every option)

- **Source.** The wall reads `PledgeCampaign::fromPosts()` for one campaign thread. Nothing is stored, so the wall and the thread always agree.
- **One block per pledge.** A pledge of N claims N squares in a row, in thread order. The block shows the badge, the name and the link once, across its squares. A $300 pledge is one block three squares wide, not three repeated names.
- **Parts of a square.** Whole squares are drawn for the whole part of a pledge. What is left (0.5 of a $250 pledge, or a $40 pledge) is not drawn as a square. It counts in the total, and the pledger is listed under the wall: "Also: mara, rin".
- **Open and delivered.** An open square is drawn as an outline. A delivered square is filled in. A part-filled pledge fills whole squares from the left.
- **Pending.** A pledge from a key that isn't approved yet comes after the counted squares, dashed and marked "Pending". It stays out of the total, as on the thread. Once the key is approved, the pledge moves into place.
- **Withdrawn.** Gone from the wall. Its squares go to the next pledges. The thread still shows it.
- **Past the goal.** The wall grows a row at a time. A line shows where square 50 ends.
- **Pledging from the wall.** "Cover squares" opens the thread's existing pledge form, with presets (1, 3, 5, 10) that fill its amount field. A guest is asked for a username first (rule 2).

## Option A — A `/support/` page that names one thread (recommended)

**Estimate: 0.60**

A new page, `/support/`, draws the wall for one thread chosen in config. The campaign thread keeps its bar and rows, and links to the page.

- Gives pequalnp.com/sponsors a home of its own, with a short link to share.
- One thread at a time is all we need. A second campaign can come later.
- Avoids the "sponsor" clash: the page is "Support ZenMemes", and pledgers are "supporters".
- Estimate details: driver: new route and template, wall layout across themes, and the presets; uncertainty: page caching when a pledge's approval changes (the approval reload cleared thread pages and board pages, not new ones).

## Option B — The wall on the campaign thread itself

**Estimate: 0.45**

A `wall: 100 dollars` line in the opening post draws the wall in place of the bar.

- No new page and no config. Any campaign can have a wall.
- No short link, and the wall competes with the pitch and rows on a phone. Adds a third line format to the campaign.
- Estimate details: driver: the new line and a second totals layout; uncertainty: low.

## Option C — A page drawn by hand

**Estimate: 0.15**

A static page where squares are edited by hand from the thread.

- Fast, but out of date the moment someone pledges, and it goes against "derived, not stored".

## Recommendation

Option A, with the shared rules. Three things answer "money left on the table": multi-square blocks, presets on the form, and a wall that grows past 50. Smaller amounts still count, without making the wall messy.

Questions for Step 2:
- Do pending pledges show on the wall at all? Suggested: yes, dashed and after the counted squares, so a new supporter sees their pledge arrive.
- Is the remainder list ("Also: …") right, or should parts of a square be drawn as part squares? Suggested: the list; part squares are hard to read at this size.
- Presets: 1, 3, 5, 10 squares? Suggested: yes, plus the field's own number.
- Page name and path: "Support ZenMemes" at `/support/`, and "supporters" rather than "sponsors"? Suggested: yes, because of the clash above.
- The pitch text on the page is written by the maintainer in the thread's opening post, and the page shows it. Suggested: yes, so changing the copy is a post, not a deploy.

Enhancement requests: none beyond the original query. Approval: approved 2026-11-27.

## Study snapshots

- Intake (after reading `PledgeAmount`, `PledgeCampaign` and the pledge campaign concept; no pristine intake): 0.60 for Option A. Base `origin/main` at `a542d1e`; R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-27 UTC. Option estimates: A 0.60, B 0.45, C 0.15, not summed. R1 hashes not re-verified in this session. The ledger entry is pending.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
