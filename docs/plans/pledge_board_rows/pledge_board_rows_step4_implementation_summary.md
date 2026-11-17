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

## Stage 2 - The line on the row

**Original estimate: 0.12**

**Retrospective: 0.14**

- Changes:
  - `templates/partials/thread_row.php`: a campaign row gets one line under its title: the pledged sum in bold, " / ", the goal and unit, then "Goal reached" or "Complete" with the thread page's `.pledge-status` look and labels.
  - The row's excerpt leaves out the goal line (`PledgeLines::hide()`), as the thread page does, so a campaign whose first line is its goal does not repeat it. In-scope refinement, recorded below.
  - `public/assets/site.css`: `.thread-row__pledge` in the first column (second beside a thumbnail), 0.85rem, wrapping; a smaller status word; "Complete" uses the same colour rule as the totals block.
- Verification:
  - Passed: `PledgeCampaignTest::testBoardRowShowsTheThreadPagesProgressOnlyWithTheFlagOn` (flag off draws nothing; a 12-coffee pledge plus a waiting 3 reads "12 / 10 coffees · Goal reached", as the thread page's 12; a plain thread has no line; the line follows the title; the excerpt keeps the pitch and drops the goal line; after approval the board reads 15).
  - Passed: `tests/browser/pledge_campaign.mjs` in headless Chrome, 17 themes at 375 and 1280 px. On the board, the main campaign's line matches its thread page's sum and status word, and a second campaign with a 59-character unit wraps. In both, the line sits under the title, the title's height is the same with and without it, the line stays clear of the reply count, nothing overflows, and the excerpt has no goal line. Also checked once without JavaScript at 375 px.
  - Screenshots checked by eye at 375 px in Gloss, Word97, Bubblegum, Light and Console, and at 1280 px in Gloss.
  - Passed: `./v3 test --smoke BoardListTest PledgeCampaignTest BoardSearchTest`, 508 run, 508 passed. The full suite was not run.
- Notes:
  - Not exercised: a campaign row with a thumbnail. The CSS gives the line the same column as the excerpt beside a thumbnail, but no fixture attached an image. Pending; low risk, since the line follows the excerpt's rules there.
  - `thread_row.php` also draws profile and search rows. Those rows carry no `pledge_campaign`, so they are unchanged (`BoardSearchTest` passes).
  - The browser check needed the project's declared `playwright-core`, installed locally with `npm install --no-save`; nothing was added to the repository.
- Estimate details: original scope as planned plus the excerpt refinement; uncertainty about Word97 and Bubblegum did not materialise. Retrospective slightly above the original for the refinement and the geometry checks.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*

## Enhancement requests

- **Request:** (found during Stage 2, not from the maintainer) a campaign row's excerpt repeated the raw `goal:` line under the new line. **Disposition:** in-scope refinement of Stage 2's approved row, following the thread page's existing rule; implemented. **Approval:** within approved Step 3 scope. **Outcome:** Stage 2 commit; checked by `PledgeCampaignTest` and the browser check.

## Stage 3 - The board stays current for visitors with no cookies

**Original estimate: 0.08**

**Retrospective: 0.06**

- Changes:
  - `StaticArtifactInvalidator::invalidateBoardPages()` clears `/index.html`, `/threads.html` and `/threads/index.html`.
  - `invalidateReply()` and `invalidateBoardThread()` now also clear `/threads.html` and `/threads/index.html`. They already cleared `/index.html`. Every pledge, fill, withdrawal and review is a reply.
  - `LocalWriteService::invalidateThreadsAuthoredBy()`, the approval path from #317, also calls `invalidateBoardPages()`. It runs on all four approval paths.
- Verification:
  - Passed: `PledgeCampaignTest::testAPledgeReplyClearsTheBakedBoardPages` (three baked board files are gone after a pledge reply).
  - Passed: `UserAccessTest`, with #317's case extended to the three board files after an approval.
  - Both new assertions fail with the source change stashed and pass with it.
  - Passed: `./v3 test --smoke BoardListTest PledgeCampaignTest UserAccessTest HighTrafficTest`, 517 run, 517 passed. The full suite was not run.
- Notes:
  - Already covered, checked by reading: a retraction runs `invalidateRemovedPost()`, which clears every top-level `*.html` and `*/index.html`. An operator grant and a key retirement, the only way an operator stops being one, run `invalidateApprovalSeed()`, which clears every generated page.
  - High-traffic mode keeps its own cached copies of `/` and `/threads/`, which may lag, as the maintainer accepted for that mode.
- Estimate details: original scope as planned; the operator paths needed no change. Retrospective lower for that reason.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
