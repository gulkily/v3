> **Feature plan:** [Step 1](./pledge_board_rows_step1_solution_assessment.md) · [Step 2](./pledge_board_rows_step2_feature_description.md) · [Step 3](./pledge_board_rows_step3_development_plan.md) · [Step 4](./pledge_board_rows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./pledge_board_rows_debug_log.md))

Effort study: `railspike-effort` / `v3/pledge_board_rows`; [ledger](../effort_ledger/pledge_board_rows.md). Standing P2 enrollment. Parent: `v3/pledge_cards`.

Extended estimates: enabled

Status: Step 3 approved by the maintainer 2026-11-17 ("approved step 3"). Building on `feat/pledge-board-rows`.

**Cycle estimate: 0.35**

# Step 4: Implementation Summary — Pledge board rows

- Branch: `feat/pledge-board-rows`, from `origin/main` at `1446b8a5`. PR #319 merged after Step 3 was written, so the base moved from the planned `d33114d2`. #319 touches the post card, the approval reload script and docs, none of which this cycle changes.
- Planning commit: `293bc17a` (Steps 1 to 3, the debug log, the plan index entry).
- Base check before any change: `./v3 test --smoke BoardListTest PledgeCampaignTest UserAccessTest` on `1446b8a5`: 506 run, 506 passed. No failure on the base to fix.

## Study entry

- Step 4 entry: **0.35** reaffirmed from Step 3. Stage originals 0.10, 0.12, 0.08, 0.06 copied from the plan; copying is not a new rating. Claude single exposed rater (claude-opus-5-5), 2026-11-17 UTC.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Stage 1 - Campaign rows carry their totals

**Original estimate: 0.10**

**Retrospective: 0.08**

- Changes:
  - `PledgeCampaign::forRows(rows, loadPosts, isOperator)`: for each row whose opening post has a `goal:` line, loads that thread's posts and adds the `fromPosts()` result as `pledge_campaign`. Other rows are not read.
  - `BoardPageController` takes the thread page's post loader (`Application::fetchThreadPosts()`) as an optional constructor argument. `boardList()` runs `forRows()` on the pinned and shown rows only, with the `pledges` flag on, using the thread page's operator check.
- Verification:
  - Passed: `PledgeCampaignTest::testBoardRowsGetTheThreadPagesTotalsAndOnlyCampaignsAreRead` (a campaign row gets exactly `fromPosts()`'s result, with a waiting and a withdrawn pledge; a plain row and a quoted goal line are not read; a board with no campaign reads nothing).
  - Passed: `./v3 test --smoke BoardListTest PledgeCampaignTest`, 507 run, 507 passed. The full suite was not run.
  - Timing: `fromPosts()` on 301 synthetic posts takes 0.58 ms. With one indexed read per campaign, no batched read is needed (Key Risk 2 closed).
- Notes:
  - The card-layout and QDB boards do not call `boardList()` and are unchanged, as scoped.
  - The row shows nothing yet; Stage 2 draws the line.
- Estimate details: original scope as planned; uncertainty low; no revisions. The retrospective is lower because the loader dropped in without changes.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
