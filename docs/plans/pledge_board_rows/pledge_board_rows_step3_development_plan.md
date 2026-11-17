> **Feature plan:** [Step 1](./pledge_board_rows_step1_solution_assessment.md) · [Step 2](./pledge_board_rows_step2_feature_description.md) · [Step 3](./pledge_board_rows_step3_development_plan.md) · [Step 4](./pledge_board_rows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_board_rows_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_board_rows`; [ledger](../effort_ledger/pledge_board_rows.md). Standing P2 enrollment. Parent: `v3/pledge_cards`.

Extended estimates: enabled

Status: Steps 1 (Option A) and 2 approved by the maintainer 2026-11-17. Step 2's Liked-view question went unanswered, so the suggested answer stands: the Liked rule is unchanged. Step 3 written 2026-11-17; awaiting `Approved Step 3`.

**Cycle estimate: 0.35**

# Step 3: Development Plan — Pledge board rows

Base: `origin/main` at `d33114d2`; PR #319 open and not included. Branch for Step 4: `feat/pledge-board-rows`.

## Completion Contract

- Normal entry: the board at `/` or `/threads/`, signed in or not, at 375 px and 1280 px, with and without JavaScript.
- End-to-end outcome: a campaign row reads "620 / 1000 coffees", plus "· Goal reached" or "· Complete" when that applies, under its title. The numbers match the thread page, counting approved members only.
- Recovery: no action on the row can fail. The guarded failure is a stale number: after a pledge, fill, withdrawal, review, retraction or approval, the next view is current, including the baked copy for a visitor with no cookies.
- Deployment: no schema change, no rebuild and no new flag; the line appears only with `pledges` on. High-traffic mode may still lag the board, as the maintainer accepted for that mode.
- Release: Ilya sees the demo campaign's line on his iPhone SE and it matches the thread page. Pending until he reports.

## Key Risks

- **High risk: stale board for visitors with no cookies.** Impact: they see old totals after an approval, or `/threads/` after a reply. Early validation: Stage 3's tests bake the board pages first, then check they are gone. Mitigation: approval and replies clear all three board paths. Operator grants already clear every page.
- **A large campaign slows the board.** Impact: each view reads every reply of each campaign on the page. Early validation: Stage 1 times a board page with one campaign of 300 replies against one without. Mitigation: only campaign rows on the shown page are read, with the thread page's own loader. A batched read is added only if the timing shows a need.
- **The line crowds the row.** Impact: a thumbnail, a reply count and a long unit could squeeze the title. Early validation: Stage 2's browser check at 375 px in 17 themes, with all three. Mitigation: the line sits in the first column under the title and wraps.

## Stage 1 — Campaign rows carry their totals

**Estimate: 0.10**

- Goal: each campaign row on the shown board page carries the same totals as its thread page.
- Dependencies: none. Before any change, run the smoke set plus `BoardListTest`, `PledgeCampaignTest` and `UserAccessTest` on the base, and fix any failure found there.
- Expected changes: `BoardPageController::boardList()` passes its pinned and shown rows to one new helper, `PledgeCampaign::forRows(rows, loader, isOperator): rows`. The helper finds campaign rows by the opening post's `goal:` line and loads only those threads' posts. It adds the `fromPosts()` result to each one. The controller receives the thread page's post loader and operator check from `Application`, as the thread controller does. The flag off means nothing is loaded.
- Verification approach: new `BoardListTest` cases. A campaign row's totals equal the thread page's, with one waiting and one withdrawn pledge. A board with no campaign calls the loader zero times. The flag off adds nothing. Time the 300-reply case.
- Estimate details: driver: passing the loader into the board controller; uncertainty: low.
- Risks or open questions:
  - Impact: the board's card layout and QDB boards do not use `boardList()`. They stay unchanged, as Step 2 scoped.
  - Early warning / validation: `BoardListTest`'s card-layout test still passes.
  - Mitigation: none needed.
- Canonical components/API contracts touched: `PledgeCampaign::fromPosts()` (reused, unchanged); `GoalDirective`; `Application::fetchThreadPosts()` (reused as the loader); `ApprovalPolicy::isActiveOperator()`; `ListThumbnails::attach()` as the pattern.

## Stage 2 — The line on the row

**Estimate: 0.12**

- Goal: a visitor sees the line under a campaign's title, in every theme.
- Dependencies: Stage 1.
- Expected changes: `thread_row.php` adds one line in the event line's slot: pledged, " / ", goal and unit, then the status word. It reuses `pledge_totals.php`'s labels and the `.pledge-status` look. `site.css` places the line in the first column, in the meta size, wrapping. No theme file changes unless a theme check fails.
- Verification approach: a `BoardListTest` case for the text, with and without the status word. `tests/browser/pledge_campaign.mjs` opens the board at 375 and 1280 px in 17 themes. It checks the line's text, that the title's box is unchanged by the line, and that nothing overflows the row. One fixture has a thumbnail and a long unit.
- Estimate details: driver: 17 themes, two of which draw rows as windows; uncertainty: Word97 and Bubblegum row styles.
- Risks or open questions:
  - Impact: the line could read as part of the excerpt.
  - Early warning / validation: screenshots at 375 px in Gloss, Word97 and Bubblegum.
  - Mitigation: the excerpt keeps its own line, and the status word's look marks the campaign line.
- Canonical components/API contracts touched: `templates/partials/thread_row.php`, `templates/partials/pledge_totals.php` (labels), `public/assets/site.css` (`.thread-row`, `.pledge-status`).

## Stage 3 — The board stays current for visitors with no cookies

**Estimate: 0.08**

- Goal: the next board view after any change shows the new numbers, baked copy included.
- Dependencies: Stage 1.
- Expected changes: `StaticArtifactInvalidator` gains `invalidateBoardPages()`, clearing `/index.html`, `/threads.html` and `/threads/index.html`. `invalidateReply()` and `invalidateBoardThread()` add the two `/threads/` paths. The approval path from #317 (`invalidateThreadsAuthoredBy()` in `LocalWriteService`) also calls `invalidateBoardPages()`. Retraction and operator grants already clear every top-level page. Stage 3 confirms that operator removal does too, or calls `invalidateBoardPages()` there.
- Verification approach: extend #317's case in `UserAccessTest` to bake the three board files and assert approval removes them. Add one case in which a pledge reply removes them. Rerun Stage 1's tests.
- Estimate details: driver: finding every approval and operator path; uncertainty: low, since #317 already found the approval ones.
- Risks or open questions:
  - Impact: clearing the board on every reply makes the next visitor with no cookies get a PHP render.
  - Early warning / validation: `/index.html` is already cleared on every reply today.
  - Mitigation: accepted; it is the same cost that `/` already has.
- Canonical components/API contracts touched: `src/ForumRewrite/Write/StaticArtifactInvalidator.php`, `src/ForumRewrite/Write/LocalWriteService.php`, `tests/UserAccessTest.php`.

## Stage 4 — Replay and records

**Estimate: 0.06**

- Goal: the maintainer's ordinary check can be repeated locally, and the docs say what was built.
- Dependencies: Stages 1 to 3.
- Expected changes: a local replay of a campaign like the demo thread, as a visitor with no cookies, at 375 by 667 px, with and without JavaScript. The replay bakes the board, approves a waiting pledger, reloads and sees the new number. Update `docs/concepts/pledge_campaign.md` (the board row), the spec's "Board" bullet, the checklist and the ledger. Then open the PR, with a checkout snippet.
- Verification approach: the replay's screenshots; `git diff --check`; a final read of the diff for duplication and scope.
- Estimate details: driver: the replay; uncertainty: low.
- Risks or open questions:
  - Impact: the demo thread on zenmemes.com may have no like and no approved pledges.
  - Early warning / validation: the PR names both checks for the maintainer.
  - Mitigation: the release check stays pending until he reports.
- Canonical components/API contracts touched: `docs/concepts/pledge_campaign.md`, `docs/plans/pledges_spec.md`, `docs/plans/pledges_checklist.md`.

Concepts touched: [pledge campaign](../../concepts/pledge_campaign.md), updated in Stage 4.

Checks: the smoke set plus `BoardListTest`, `PledgeCampaignTest` and `UserAccessTest`, and the browser check. The full suite is not run.

## Study checkpoint

- Step 3 handoff: **0.35** reaffirmed, scope v1. Stage work 0.10, 0.12, 0.08, 0.06, not summed. Reusing the thread page's loader removes a second query shape, and the third `/threads/` path adds a little. Baseline `d33114d2`, R1, P2. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC. R1 hashes not re-verified.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
