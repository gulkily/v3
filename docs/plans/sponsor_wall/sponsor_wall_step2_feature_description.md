> **Feature plan:** [Step 1](./sponsor_wall_step1_solution_assessment.md) · [Step 2](./sponsor_wall_step2_feature_description.md) · [Step 3](./sponsor_wall_step3_development_plan.md) · Step 4

Debug mode: enabled ([debug log](./sponsor_wall_debug_log.md))

Effort study: `railspike-effort` / `v3/sponsor_wall`; ledger `../effort_ledger/sponsor_wall.md` (pending, written with the planning commit). Standing P2 enrollment. Parent: the pledge epic, [pledges_checklist.md](../pledges_checklist.md).

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-27 (Option A). Step 2 approved by the maintainer 2026-11-27 ("approved step 2").

**Cycle estimate: 0.65**

# Step 2: Feature Description — Support wall

Base: `origin/main` at `a542d1e`.

## Problem

There is no page where a visitor can see the $5,000 support goal as a wall of squares and cover several squares at once. Pledges already take any amount, but nothing draws them as squares.

Approach (Step 1): Option A. A `/support/` page draws the wall for one campaign thread named by the site profile, and it follows the Step 1 wall rules.

## User stories

- As a visitor, I want to see how much is covered and by whom, so I can tell the project is real and supported.
- As a supporter, I want to cover several squares in one pledge and see them under my name, so a bigger gift shows as one gift.
- As a new supporter, I want to see my pledge on the wall right away, marked Pending, so I know it arrived.
- As the maintainer, I want to change the pitch by editing a post, so copy changes don't need a deploy.

## Core requirements

- `/support/` exists while `FORUM_PLEDGES_ENABLED` is on and the site profile names a `supportThread`. Otherwise it is "not found", like the guestbook.
- The page shows the thread's opening post as the pitch, the totals, the wall and a pledge form. All numbers come from `PledgeCampaign::fromPosts()`, so the page and the thread always agree.
- The wall follows the Step 1 rules: one block per pledge, N squares wide, with badge, name and a link to the pledge; outlines for open, filled for delivered; pending after counted and dashed; withdrawn left out; rows added past the goal, with a line at the goal.
- The form is the existing pledge composer, with presets (1, 3, 5, 10 squares) that fill its amount field. Posting returns to `/support/`.
- The thread page links to `/support/`, and the page links back to the thread.

## Decisions on the Step 1 questions

Used as suggested, open to overrule:

1. **Pending pledges show**, dashed and after the counted squares, out of the total.
2. **Parts of a square** are listed under the wall ("Also: mara, rin"), not drawn.
3. **Presets:** 1, 3, 5, 10, plus the field's own number.
4. **Name:** "Support ZenMemes" at `/support/`; pledgers are "supporters", because "sponsor" already means vouching for a new member's key.
5. **Pitch** comes from the thread's opening post.

Two changes from Step 1:

- **The form lives on the page**, not on the thread. The guestbook already does this (`resolveComposeReplyReturnTo` returns to `/guestbook/`), and it saves a trip to the thread.
- **The guest username prompt is not in this cycle.** Pledges from a post with no name already never count. Making the form ask a guest for a username (the 2026-11-24 rule) changes every pledge form, so it gets its own cycle next in the epic. Until then, a guest who pledges here sees "Not counted: no name", the same as on the thread.

## Delivery scope

Work type: application change.

Not in this cycle: the guest username prompt; more than one support page; a wall on the campaign thread; payment links.

## Completion boundary

- Entry: `/support/` on a phone or desktop, signed in or not, with and without JavaScript.
- End-to-end outcome: with pledges of 3, 1 and 2.5 squares from approved members, one delivered, and 1 square from a key that isn't approved, a visitor at 375 pixels sees blocks of 3, 1 and 2 squares (the 3-square block filled in), a dashed Pending square after them, "Also: <name>" for the half square, and "6.5 of 50". A signed-in member presses "3", posts, and lands back on `/support/` with a new three-square block.
- Recovery: a failed post shows the composer's usual error on the page with the amount kept, and can be sent again.
- Release: Ilya opens `/support/` on zenmemes.com on his iPhone SE, pledges 3 squares, and sees them on the wall.

## Known user and environment

- The maintainer on an iPhone SE, on zenmemes.com. He verifies by pledging from `/support/` and seeing his block. A headless pass at 375 pixels doesn't count. This stays pending until he reports.
- zenmemes.com needs a `supportThread` in its profile and a thread with `goal: 50`. If the profile names a thread that doesn't exist yet, the page shows an empty wall, as the guestbook does.

## Risks

- **Stale saved pages.** Impact: pages are baked to static files, and the approval reload clears thread and board pages but not new ones. A pledge or an approval could leave `/support/` showing old numbers. Validate early: post a pledge and approve its key, then load `/support/` with no cookies. Mitigation: clear `/support/` wherever the support thread's own page is cleared.
- **Wide blocks on a phone.** Impact: a 10-square pledge spans a whole row at 10 squares per row, and an 11-square pledge breaks across rows. Validate early: mock a wall with pledges of 11 and 0.5. Mitigation: a block may wrap, and its name sits on the first square.
- **Themes.** Impact: the wall must read in every theme, including dark ones. Validate early: render it in the default theme, Underground and one dark theme. Mitigation: draw it from the theme's existing colors only, unlike the guestbook's fixed paper look.

## Shared component inventory

- **Page for one named thread:** `GuestbookPageController`, `SiteProfileRegistry::guestbookThread()` and the guestbook route in `Application.php`. Reuse the same shape: a `supportThread` profile key and a controller.
- **Campaign numbers:** `PledgeCampaign::fromPosts()`. Reuse as is; nothing is stored.
- **Pledge form:** `templates/partials/reply_form.php` amount field, `public/assets/pledge_field.js`. Reuse, adding presets that only fill the field.
- **Return after posting:** `resolveComposeReplyReturnTo`. Extend it with a `/support/` case like `/guestbook/`.
- **Totals and bar:** `templates/partials/pledge_totals.php`. Reuse for the line above the wall.
- **Pending chip and badge:** the existing Pending chip from campaign rows; reuse it. The badge is the one new part, a small mark drawn from theme colors.
- **Cache clearing:** the approval reload and reply clearing from `pledge_approval_reload`. Extend to `/support/`.

## Simple user flow

1. A visitor opens `/support/` and reads the pitch, "6.5 of 50" and the wall.
2. They press a name on a block and land on that pledge in the thread.
3. A signed-in member presses "3", adds a note and posts.
4. They land back on `/support/` and see their block, dashed and Pending if their key isn't approved yet.
5. Once they're approved, the block moves into place and counts.

## Success criteria

- The page and the thread show the same total for every fixture: counted, pending, withdrawn, part-filled and over the goal.
- A pledge of N whole squares draws one block N squares wide. What's left of a square appears only in "Also:".
- Posting from `/support/` returns there, with and without JavaScript.
- With no `supportThread`, or with the flag off, `/support/` is not found and every other page is unchanged.

## Study snapshots

- Step 2 handoff: **0.65** (Step 1: 0.60). Changed scope: the form moves onto the page (+0.05). The guest prompt moving out holds the total down. R1, P2; Claude single exposed rater (claude-opus-5-5), 2026-11-27 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
