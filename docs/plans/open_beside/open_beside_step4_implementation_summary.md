> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled, inherited from Step 3.

Status: Step 4 in progress on `feat/open_beside`, branched from `origin/main` at `55082575` plus the unpushed docs commit `b007b32f` (design notes and PF-20261117-F10). Planning commit `0dae1b9c`.

**Cycle estimate: 0.60**

# Step 4: Implementation Summary — Open beside

Step 4 entry projection: 0.60, Step 3 reaffirmed. Scope v1, R1, P2; Claude single exposed rater, 2026-11-18 UTC.

Stage estimates below are copied from Step 3 (originals rated 2026-11-18 by Claude); copying them is not a new rating.

Ledger note: Step 3 said this cycle's ledger row would go into the planning commit, but Step 4's gate keeps the ledger out of it. The row goes into the Stage 1 commit instead.

Link rules answered in session before approval (the maintainer asked which links are eligible, was given three proposals, and approved Step 3 without changing them): a link to a spot on the same page scrolls the main page; a link to a file that is not a page opens in the pane; "New post" and "Reply" links in the content open in the pane. Stage 3 builds these.

## Stage 1 - Flag and the framed page

**Original estimate: 0.08**
**Retrospective stage work: 0.08**

- Status: complete.
- Changes:
  - `FORUM_OPEN_BESIDE_ENABLED` in `FeatureFlagRegistry.php`: off by default, site-mutable, in the Experience group. `TemplateRenderer.php` passes `openBesideEnabled` to the layout.
  - `layout.php`'s head script: a page inside a same-site frame named `forum-open-beside` gets `data-open-beside-framed` and a style that hides the site header, the app shell's sky strip, the ruler, the status bar and the new-version banner. Both are set in the head, before the body is parsed.
  - `tests/FeatureFlagEvaluatorTest.php`: the new flag in both expected lists.
  - New `tests/browser/open_beside.mjs`. `BROWSER=firefox` runs it in Firefox.
  - Ledger: this cycle's row in `effort_estimation_trial.md`.
- Verification:
  - `node tests/browser/open_beside.mjs` in Chrome: passes. In the pane's frame the header, ruler and status bar are hidden the first time they can be seen and after load, and the content shows. A frame with another name keeps its header and ruler. A top-level window named like the pane is not marked. With the flag off the frame name means nothing.
  - `BROWSER=firefox node tests/browser/open_beside.mjs`: passes. Firefox 146 installed here with `npx playwright-core install firefox`, so the Step 2 Firefox evidence can be gathered in this workspace.
  - `./v3 test --smoke FeatureFlagsBehaviorTest FeatureFlagEvaluatorTest SiteProfileRegistryTest`: 492 run, 492 passed.
- Notes:
  - The hiding rules are written by the head script, not kept in `site.css`, because `site.css` loads without blocking paint and would let the header flash.
  - The status bar is hidden in every theme but Word97 anyway; the test asserts it only inside the pane.
  - The test watches the frames from the host page. A script injected into the frame does not run early enough in a frame that starts as a blank document.
- Estimate details: original scope as Step 3; uncertainty was whether Firefox could be installed here, and it could. No revisions.

## Stage 2 - The Beside toggle and the pane

**Original estimate: 0.14**
**Retrospective stage work: 0.13**

- Status: complete.
- Changes:
  - `openBesideStorageKey` and `openBesidePaneStorageKey` in `BrowserRuntimeProfile.php`. `layout.php` restores the mode before paint as `data-open-beside="on"`.
  - `reading_margins.php`: a Beside button at the window's right end of the ruler, only with the flag on; its inline script draws it pressed before paint.
  - New `public/assets/open_beside.js` and `open-beside.css`, loaded only with the flag on. The script catches plain left clicks on same-site page links inside `.main` and shows the page in a frame named `forum-open-beside`, fixed to the window between the column's right edge and the window's, no wider than the normal page, and only with at least 20rem of room. It starts below the ruler while the ruler is on screen. The pane follows window resizes, column changes and scrolling. A framed page never runs the mode.
  - Turning the mode off removes the pane.
- Verification:
  - `node tests/browser/open_beside.mjs` in Chrome and `BROWSER=firefox`: both pass. Mode off navigates; mode on at 2048px with the column narrowed to the left opens the thread in the pane with the main address and scroll unchanged; the pane sits right of the column, inside the window, nearly window-tall and below the ruler; no sideways scrolling; no pane inside the pane; the mode survives a reload; at 1280px with the normal column the mode stays on and the click navigates; a phone shows no toggle and no pane.
  - `node tests/browser/reading_margins.mjs`: passes unchanged (18 themes).
  - `./v3 test --smoke FeatureFlagEvaluatorTest BrowserRuntimeProfileTest PresentationProfileMatrixTest`: 486 run, 486 passed.
  - Screenshots at 2048px (pane open) and 1280px (no room) checked by eye.
- Notes:
  - The pane's position is set by the script from the column's measured edge, not from the ruler's CSS variables as Step 3 expected. The measured edge is right in every theme and for the normal column too, and one rule serves both.
  - The pane's address is already saved on open; restoring it is Stage 4.
  - The fixture's `/` answers 503 in the test server, so the test uses `/threads/` as the board.
- Estimate details: original scope as Step 3; the header moving with the column caused no trouble. No revisions.

## Stage 3 - The pane's bar and the kinds of link

**Original estimate: 0.12**
**Retrospective stage work: 0.16**

- Status: complete.
- Changes:
  - `open_beside.js`: the pane gets a bar with the page's title, Open alone (a real link to the pane's page, which also forgets the pane) and Close. The bar, the saved page and Open alone follow the pane whenever its page changes, including links followed and forms posted inside it. A page with no title shows its address. Opening is announced through a `role="status"` line; focus stays on the clicked link.
  - The click handler moved from the document to the window, so a link another page script already handles (the inline reply link) is left alone.
  - In a framed page: a link to another site takes the whole tab; a clicked field takes focus (see Notes).
  - `open-beside.css`: the bar and its two actions, styled the same in every theme.
- Verification:
  - `node tests/browser/open_beside.mjs` in Chrome and `BROWSER=firefox`: both pass. Bar title, focus on the link, the announcement; a link followed in the pane; Ctrl-click and middle-click; `target="_blank"`, download and same-page links; a text file and a compose page in the pane; a header link changes the main page; a link to another site from the page and from the pane; Open alone; Close by keyboard.
  - Posting, on the signing fixture (`signed_reaction_browser_router.php`, the one `first_post_identity.mjs` uses): a reply typed and posted in the pane is accepted, shows in the pane, the pane's page is still framed, and the main page does not move. A like in the pane is applied there.
  - Screenshot of the bar at 2048px checked by eye.
- Notes:
  - The Step 3 risk (signing scripts assuming top level) did not appear: posting and liking work in the frame unchanged.
  - Found in Firefox under Playwright: when something in the main page has focus, the first click on a field inside any frame focuses the frame but not the field. Reproduced without this feature's code, with a plain frame and a focused link. It is probably an automation artifact, not real Firefox, but that could not be checked here. Because the main page's clicked link always keeps focus, the framed page now focuses a clicked field itself; that is harmless where the browser already did. The maintainer's Firefox check should include one click into the pane's reply box.
  - The parity fixture's server cannot accept posts (`create_identity` answers 400 at top level too), which is why posting uses the signing fixture.
  - The main page is not refreshed after a post in the pane. Known limitation, as Step 2 says; the test asserts it so a later cycle changes it on purpose.
- Estimate details: original scope as Step 3; the retrospective is higher because of the Firefox focus finding and the second fixture. No scope change.

*— Claude · 2026-11-18 UTC · Model: claude-fable-5-2*
