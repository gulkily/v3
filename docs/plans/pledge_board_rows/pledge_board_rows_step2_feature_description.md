> **Feature plan:** [Step 1](./pledge_board_rows_step1_solution_assessment.md) · [Step 2](./pledge_board_rows_step2_feature_description.md) · [Step 3](./pledge_board_rows_step3_development_plan.md) · [Step 4](./pledge_board_rows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_board_rows_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_board_rows`; [ledger](../effort_ledger/pledge_board_rows.md). Standing P2 enrollment. Parent: `v3/pledge_cards`.

Extended estimates: enabled

Status: Step 1 approved by the maintainer 2026-11-17 (Option A). Step 2 approved by the maintainer 2026-11-17 ("approved step 2"). The Liked-view question was not answered, so the suggested answer stands.

**Cycle estimate: 0.35**

# Step 2: Feature Description — Pledge board rows

Cycle 5 in [pledges_checklist.md](../pledges_checklist.md). Base: `origin/main` at `d33114d2`; PR #319 still open and not included.

## Problem

A campaign can only be reached through its link, because its board row looks like any other thread's. A reader browsing the board cannot tell that a campaign exists or how far along it is.

Approach (Step 1): Option A. The board works out the totals for the campaign rows on the page it shows, with the same function as the thread page. Approval and replies also clear the board's baked copies.

## User stories

- As a visitor browsing the board, I want to see that a thread is a campaign and how far along it is, so that I can find one to follow.
- As a campaign author, I want my campaign's progress on the board, so that people notice it without my sharing the link.
- As a pledger, I want the board to show the same number as the thread page, so that I trust both.

## Core requirements

- With the `pledges` flag on, a campaign's row on the board shows one line under the title: "620 / 1000 coffees", then "· Goal reached" or "· Complete" when the campaign has that status.
- The numbers are the thread page's numbers: only approved members' pledges count, and approval is read when the board is shown.
- Rows that are not campaigns, and every row with the flag off, are unchanged.
- After a pledge, fill, withdrawal, review, retraction or approval, the next board view shows the new numbers. This includes a visitor with no cookies, who is served a baked copy.
- The board stays as fast as it is for pages with no campaign.

## Decisions on the Step 1 questions

The suggested answers are used, open to overrule.

1. **"620 / 1000 coffees"**, the spec's slash, to keep the line short. The thread page keeps "620 of 1000 coffees pledged".
2. **No delivered figure on the row.** "Complete" already says the goal was delivered.

## Question for the maintainer

The board's default view is Liked. A campaign appears there only once someone likes it, and under All always. Suggested: leave the rule as it is; this cycle makes campaigns recognisable where they are listed. Showing campaigns in Liked without a like would be its own small change.

## Delivery scope

Work type: application change.

Lists: the board only, `/` and `/threads/`, on phone and desktop. Not in this cycle: Recent, tag pages, profile, search and card-layout boards. Profile and search share the row partial and could show the line later.

## Completion boundary

- Entry: the board at `/` or `/threads/`, signed in or not, at 375 px and on desktop, with and without JavaScript.
- End-to-end outcome: a campaign with 620 of 1000 coffees pledged by approved members, and 12 more from a member not yet approved, reads "620 / 1000 coffees" on the board. The thread page says 620 too. Once that member is approved, the next board view reads "632 / 1000 coffees", for a visitor with no cookies as well.
- Recovery: nothing on the row can be pressed except the title link, so there is no failed action to retry. A stale number is the failure to guard against; the next view after any change must be current.
- Release: Ilya opens zenmemes.com on his iPhone SE and sees the demo campaign's line on the board, matching its thread page.

## Known user and environment

- The maintainer on an iPhone SE, on zenmemes.com. His ordinary entry is the board. The mismatch today is that a campaign has no sign on its row. He verifies by finding the demo thread's row and opening it to compare numbers. A headless pass at 375 px does not satisfy this; it stays pending until he reports.
- The demo thread is in the Liked view only if it has a like; otherwise he finds it under All. See the question above.

## Risks

- **Baked board copies stay stale.** Impact: a visitor with no cookies sees old numbers after an approval, or after a reply on `/threads/`. Validate early: write a pledge and an approval in a test and check that `/index.html`, `/threads.html` and `/threads/index.html` are gone afterwards. Mitigation: add those paths to what approval and a reply clear.
- **A big campaign slows the board.** Impact: each board view reads every reply of each campaign on the page. Validate early: time a board page with one campaign of a few hundred replies. Mitigation: one batched read for the page, filtered to lines with a colon, as the thread page does.
- **The line crowds a narrow row.** Impact: with a thumbnail, a reply count and a long unit, the title could lose width. Validate early: render a row with all three at 375 px in all 17 themes. Mitigation: the line sits under the title in the first column and wraps; the title's line is not shared.
- **Operator changes.** Impact: fills by an operator count only while the key is an operator, so a grant or removal can change a total. Mitigation: the thread page has the same dependency today; Step 3 checks that a grant clears the board as it clears thread pages, and otherwise records it as known.

## Shared component inventory

- Totals reader (`src/ForumRewrite/View/PledgeCampaign.php`, `fromPosts()`): reused as is for each campaign row. No second counting rule.
- Goal line (`src/ForumRewrite/Support/GoalDirective.php`): finds campaign rows from the opening post's text, which each board row already carries.
- Per-row data on the visible page (`src/ForumRewrite/Images/ListThumbnails.php`, `attach()`): the pattern for adding data to the 50 shown rows only. `EventStatus::forThreads()` is the pattern for one query over many threads.
- Thread posts (the loader `ThreadAndPostPageController` uses): the same post shape, so approval and attribution fields match.
- Board list (`BoardPageController::boardList()`, `templates/pages/board_list.php`, `templates/partials/thread_row.php`): one line added to the row, in the event line's slot.
- Status word (`templates/partials/pledge_totals.php`, `.pledge-status`): its labels and look reused.
- Baked copies (`src/ForumRewrite/Write/StaticArtifactInvalidator.php`, `invalidateReply()`, the approval path from #317 in `LocalWriteService`): board paths added.
- Checks: `PledgeCampaignTest` and `tests/browser/pledge_campaign.mjs`, extended to the board.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), changed (the board row). No matching item in `docs/feedback/items/`.

## Simple user flow

1. A visitor opens the board on a phone.
2. Among the threads, one row reads "Pledge campaigns demo: 50 coffees", with "60 / 50 coffees · Goal reached" under it.
3. They open it, and the thread page shows the same 60 of 50.
4. A waiting member's key is approved. The next board view shows the new total.

## Success criteria

- A campaign row shows its line at 375 px and 1280 px in all 17 themes, and the title keeps its full line.
- The row and the thread page show the same pledged sum and status in every fixture, including waiting and withdrawn pledges.
- After a pledge, a fill, a withdrawal, a review, a retraction and an approval, a visitor with no cookies sees the new numbers on the next view of `/` and `/threads/`.
- A board page with no campaign runs no extra query.
- With the flag off, the board is unchanged.

## Enhancement requests

None.

## Study snapshots

- Step 2 handoff: **0.35** reaffirmed, scope v1. The Liked-view question adds nothing unless chosen; the freshness tests carry most of the work. Baseline `d33114d2`, R1, P2. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
