> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md)), inherited from Step 3.

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_ledger/open_beside.md). Standing P2 enrollment.

Extended estimates: enabled, inherited from Step 3.

Status: Step 4 technically complete on `feat/open_beside`, rebased onto `origin/main` at `31e16b06` after Stage 6 (commit names below are the rebased ones), on top of the unpushed docs commit `dbc105ef` (design notes and PF-20261117-F10). Planning commit `d3d46872`.

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

## Stage 4 - The pane stays

**Original estimate: 0.12**
**Retrospective stage work: 0.12**

- Status: complete.
- Changes:
  - `open_beside.js`: on every page with the mode on, the saved page comes back in the pane as soon as there is room. A saved value is used only if it is a path on this site. The pane's scroll position is saved as it scrolls and restored when the same page comes back. A restored pane is not announced.
  - With no room the pane hides without unloading; a page that loads with no room builds its pane once there is room.
  - The pane reloads when the main page's theme really changes (the theme script also re-sets the same theme, which is ignored).
  - Open alone now also stops a pane load that is still on its way from saving the pane again.
- Verification:
  - `node tests/browser/open_beside.mjs` in Chrome and `BROWSER=firefox`: both pass. After a header link and after a reload the pane shows the same page at the same scroll position, and the main column sits where it was first parsed; hiding at 900px keeps the pane's page loaded; a page loaded at 900px gets its pane at 2048px; a theme change reaches the pane and keeps its page; a saved page that does not exist shows the framed not-found page and Close forgets it; `https://…`, `//…` and `javascript:` values are not opened.
  - Screenshot in Word97 at 2048px checked by eye.
- Notes:
  - The pane is created when the page's deferred scripts run, not from the head script as Step 3 planned. The pane is fixed to the window and never moves the main column, so the "no jump" criterion holds either way, and the test checks it. The head script could not create it in any case: `site.css` loads without blocking, so the column's width is not known that early.
  - `theme_toggle.js` is unchanged. The pane watches `data-theme` on the page instead of needing a new signal.
  - Two bugs found by the test and fixed in this stage: the theme watcher reloaded a pane that had not left its blank page yet, cancelling its first load; and a pane load finishing after Open alone saved the pane again.
  - Scroll restore is exact in the test. On a page whose height changes after load it may land short.
- Estimate details: original scope as Step 3; uncertainty was scroll restore, which held. No revisions.

## Stage 5 - Preferences row and reset

**Original estimate: 0.06**
**Retrospective stage work: 0.07**

- Status: complete.
- Changes:
  - `preferences.php`: a row "Open links beside", Off or On, only with the flag on. `ToolsPageController.php` passes the flag. The page's inline script shows the saved choice before paint.
  - `preferences.js`: the row switches the mode through `window.forumOpenBeside.set`, which `open_beside.js` now exposes, so the row and the ruler's toggle share one path. "Reset all preferences" removes the mode, the pane's page and its scroll position.
  - `open_beside.js`: switching the mode re-renders the Preferences rows when that page is open.
  - Fixed on the way, a test failing on the base commit: `tests/browser/preferences.mjs` seeded reading margins as `23 77`, the ruler's old numbering, which the rebuilt ruler (PR #263) ignores, so its "Custom" check failed. Confirmed failing on the base commit in a clean worktree; the seed is now `1 4`.
- Verification:
  - `node tests/browser/preferences.mjs`: passes, with the new row (off on a fresh browser, on from a saved choice, the row and the ruler toggle following each other, On surviving a reload, Reset all).
  - `node tests/browser/open_beside.mjs` in Chrome (twice) and `BROWSER=firefox`: pass, with a new part: with the reading margins flag off there is no toggle, the Preferences row alone switches the mode, a pane opens beside the normal column at 2048px, and Reset all leaves nothing of the mode in storage.
  - `./v3 test --smoke PreferencesPageTest FeatureFlagEvaluatorTest`: 480 run, 480 passed.
- Notes:
  - A race in the test itself (changing the saved page by hand while the pane was still reloading for a theme change) was removed by waiting for that reload.
- Estimate details: original scope as Step 3, plus the pre-existing test fix. No revisions.

## Stage 6 - The reported case, documentation and closeout

**Original estimate: 0.10**
**Retrospective stage work: 0.10**

- Status: complete.
- Changes:
  - `tests/browser/open_beside.mjs`: the reported case. At 2048px in Gloss Dark, with the column narrowed to the left on the Prompts page (more than 1000px empty beside it), a click on a prompt's title opens its thread in the pane, in the same theme, and the Prompts page stays. With a screenshot directory, every theme is shot with a pane at 2048px and 1280px and without one at 390px.
  - `open-beside.css`: the pressed Beside toggle no longer swaps colours. In Arena and Sticker the ruler's colour is also the text colour, so the pressed toggle was a blank white pill. It now keeps the page's background and ink and shows a heavy ring and a bold label.
  - New `docs/concepts/open_beside.md`. The design notes, the plans index and PF-20261117-F10 point at the built cycle. Ledger closeout.
- Verification:
  - `node tests/browser/open_beside.mjs <dir>` in Chrome and `BROWSER=firefox … <dir>` in Firefox 146: both pass, including the reported case and all 18 themes at three widths with no sideways scrolling.
  - Screenshots checked by eye: the reported case in Chrome and in Firefox; contact sheets of all 18 themes at 1280px; the toggle cropped in eight themes after the fix.
  - `node tests/browser/reading_margins.mjs`, `draggable_windows.mjs` and `preferences.mjs`: pass.
  - `./v3 test --smoke PresentationProfileMatrixTest PreferencesPageTest FeatureFlagEvaluatorTest BrowserRuntimeProfileTest`: 488 run, 488 passed. The full suite was not run; the maintainer runs it.
  - `./v3 feedback check`: no problems.
  - Final read of the whole diff against the base.
- Notes:
  - Not verified: real Firefox outside automation (see the Stage 3 focus note), physical phones, production. Not pushed: the session has no push access to the repository.
  - In Word97 the pane's lower edge covers the right end of the theme's status bar. Left as it is.
  - Engagify shows its parody popups inside the pane too, as on any page.
  - Concepts checked: window controls, no-script presentation, static publication and theme script still read true; none changed.
- Estimate details: original scope as Step 3. No revisions.

## Step 4 closeout

- Completion Contract: met locally for entry, outcome and recovery. External verification in Firefox is done under automation. Release (merge, deploy with the flag on, the maintainer's check) is pending and needs the maintainer.
- Study: entry projection 0.60; **retrospective scope assessment 0.65**. Stage retrospectives 0.08, 0.13, 0.16, 0.12, 0.07, 0.10, not summed. The difference is Stage 3's Firefox focus finding and second fixture. Maintainer comfortable-fit judgment pending. Same R1/P2, single exposed rater.
- Follow-ups, not started: refresh the main page after a post in the pane; a strip of panes, pin, a pane list and spines (design notes); the pane's lower edge over Word97's status bar.

*— Claude · 2026-11-18 UTC · Model: claude-fable-5-2*
