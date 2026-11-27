> **Feature plan:** [Step 1](./sponsor_wall_step1_solution_assessment.md) · [Step 2](./sponsor_wall_step2_feature_description.md) · [Step 3](./sponsor_wall_step3_development_plan.md) · [Step 4](./sponsor_wall_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./sponsor_wall_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/sponsor_wall`; ledger `../effort_ledger/sponsor_wall.md` (written at closeout). Standing P2 enrollment.

Extended estimates: enabled

Status: in progress on `feat/sponsor-wall`. Planning commit `6bbd94c`.

**Cycle estimate: 0.60**

Step 4 entry: the Step 3 projection of 0.60 is reaffirmed, unchanged, before implementation. Claude, 2026-11-27 UTC.

# Step 4: Implementation Summary — Support wall

## Stage 1 - The page, the pitch and the totals

**Original estimate: 0.15**

**Retrospective: 0.12**

- Changes:
  - `SiteProfileRegistry`: a `supportThread` key on the zenmemes profile (null), and `supportThread()`.
  - New `SupportPageController::page()` loads the thread's posts through `fetchThreadPosts` (the thread page's loader, so it has the same approval columns) and runs `PledgeCampaign::fromPosts()`. The page is rendered live, as `/guestbook/` is.
  - `Application`: the `/support/` route sits beside `/guestbook/`. It returns not found with no `supportThread` or with `FORUM_PLEDGES_ENABLED` off. Added to the application route list.
  - New `templates/pages/support.php`: the title "Support <appName>", the opening post as the pitch with its goal line left out (`PledgeLines`, as on the thread), the existing totals partial, and "Open as a thread".
  - `TemplateRenderer`: stylesheets for `support.php` and a `siteSupportThreadId` page variable. On the support thread, `thread.php` shows "Open the wall".
  - Usage reports: the `support` path word and the "Support" name.
  - New `public/assets/support.css` (layout only so far).
- Verification:
  - New `SupportPageTest`: not found with no key and with the flag off; the pitch without the goal line; "5.5 of 50 squares pledged" on both the page and the thread; links both ways; an empty page for a named thread that doesn't exist yet. 3 of 3 pass.
  - `./v3 test --smoke SupportPageTest UsageNamesTest GuestbookPageTest`: 502 of 502 pass. The full suite was not run.
- Notes:
  - The title uses the profile's `appName` ("ZenMemes") when it has one, otherwise `displayName`.
- Estimate details: original scope as planned; uncertainty was low, and so it went. No revisions.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*

## Stage 2 - The wall

**Original estimate: 0.20**

**Retrospective: 0.20**

- Changes:
  - New `SupportWall::build($campaign, $posts, $perRow = 10)`: counted blocks in thread order, then pending ones; whole squares per pledge, delivered squares first; parts of a square named in `also`; the wall's size grows a row at a time past the goal. A withdrawn pledge keeps only what was delivered on it, because that's what the total keeps (`PledgeCampaign` counts it in "pledged"). This refines Step 1's "withdrawn: gone" so the wall and the total agree.
  - New `templates/partials/support_wall.php`: one `<li>` per square, each linking to its pledge in the thread and carrying the badge. The block's first square carries the name, plus the Pending chip (as a plain chip, since it sits inside a link). Empty squares fill out the size. The goal's last square gets a line on its outer edge.
  - `support.css`: 10 squares a row, 5 under 30rem (both divide 50). Open squares are outlined, delivered filled, pending dashed. A block starts with a heavier left edge. Names and badges take their color from the square (`--wall-ink`), not from the theme's link color, which gloss applies to every link.
  - Browser fixture and router (`tests/Support/support_wall_browser_fixture.php`, `support_wall_browser_router.php`): seed a goal-50 campaign with several people, approve some, and serve it as the support thread. Stage 3's browser check reuses them.
- Verification:
  - New `SupportWallTest` (3 tests): blocks, delivered squares, pending last, a withdrawn part-delivered pledge, "Also:" for 2.5 and 0.5, unnamed and unreadable left out, growth to 20 squares for 11 against a goal of 5, and the partial's markup (one name per block, every square linked, one goal mark, aria label).
  - `./v3 test --smoke SupportPageTest SupportWallTest PledgeCampaignTest`: 531 of 531 pass. The full suite was not run.
  - Screenshots at 375 and 1280 pixels in gloss, underground, midnight and dark, with pledges of 3 (delivered), 1, 2.5, 11 (4 delivered) and 1 pending. The first pass showed names in gloss's link purple on filled squares, fixed with `--wall-ink`. The second showed blocks running together, fixed with the heavier left edge. `git diff --check` is clean.
- Notes:
  - The fixture writes every pledge in the same second, so their order on the wall can change between runs. Real pledges are seconds apart.
  - Names are cut short with an ellipsis on a phone ("newco…"). The full name is in the square's title, and the thread has it.
- Estimate details: original scope plus the withdrawn refinement (no material change); uncertainty about wrapping went away once squares, not blocks, became the grid cells.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*

## Stage 3 - Pledge from the wall

**Original estimate: 0.15**

**Retrospective: 0.18**

- Changes:
  - `support.php` renders the existing pledge form (`pledge_totals.php`, form part) with `pledgeReturnTo` `/support/` and presets 1, 3, 5, 10. Both are new optional parameters on the partial. The thread page passes neither, so its form is unchanged.
  - `reply_form.php`: the amount field carries `data-pledge-presets` when presets are given.
  - `pledge_field.js`: draws a button per preset after the field (only with script). A press fills the field as typing would, so the `pledge: N` line follows, and `aria-pressed` marks the chosen one.
  - `resolveComposeReplyReturnTo` accepts `/support/` only for the support thread, as it does `/guestbook/`. Signed posts already honor `return_to` client-side (`canonicalReplyUrl`).
  - The page loads the guestbook's signing scripts plus `pledge_field.js`. A block's first square carries `id="post-<pledge>"`, so the `#post-` anchor after posting lands on the new block.
  - `support.css`: presets share one row (buttons are full width site-wide).
  - New browser check `tests/browser/support_wall.mjs`.
- Verification:
  - `SupportPageTest::testThePledgeFormOffersPresetsAndReturnsToTheWall`: the field's presets, unit, `return_to` and script on `/support/`; no presets on the thread page; a no-script post returns to `/support/?created_post_id=`; `return_to=/support/` on another thread returns to that thread.
  - `node tests/browser/support_wall.mjs` passes. At 375 by 667 with script: the Step 2 fixture shows 6.5 of 50, blocks of 3 (delivered), 1, 2 and a pending 1, "Also: sam", and no sideways scroll. Pressing "3" fills the field and the line. A post failed by the browser (a 500 from the reply API) stays on `/support/` with 3 kept, and pressing Post pledge again lands on `/support/?created_post_id=` with one pending 3-square block and the total unchanged. Once its key is approved, the total is 9.5 with two counted 3-square blocks. With no script at 1280: no presets, the field posts and returns to the wall, the unnamed pledge doesn't count, and the thread says why and links to the wall. Then 21 themes at 375 and 1280: no overflow and square squares.
  - `./v3 test --smoke SupportPageTest SupportWallTest PledgeCampaignTest GuestbookPageTest`: 537 of 537 pass. The full suite was not run. `git diff --check` is clean.
- Notes:
  - The first screenshots stacked the presets one per row, because site.css makes every button full width. Fixed in `support.css`.
  - A visitor without a name still sees "Posting as guest · Choose a name". The prompt that requires a name is the next cycle.
- Estimate details: original scope; slightly over for the failed-post replay, which needed a route intercept to make a real failure.

*— Claude · 2026-11-27 UTC · Model: Claude Opus 5.5*
