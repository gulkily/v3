> **Feature plan:** [Step 1](./nav_tree_step1_solution_assessment.md) · [Step 2](./nav_tree_step2_feature_description.md) · [Step 3](./nav_tree_step3_development_plan.md) · [Step 4](./nav_tree_step4_implementation_summary.md)

Debug mode: enabled ([log](./nav_tree_debug_log.md))

Effort study: `railspike-effort` / `v3/nav_tree`; [host ledger](../effort_ledger/nav_tree.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.70**

**Retrospective: 0.72**

Branch: `feat/nav-tree` from `origin/main` `2d7805c`. Planning commit: `f585d60`.

## Stage 1 - Flag and risk probes

**Original estimate: 0.05**

**Retrospective: 0.05**

- Changes:
  - `FORUM_VISIT_HISTORY_ENABLED` in `FeatureFlagRegistry.php`: Experience group, off by default, changeable by the site, beside Usage reports.
  - `FeatureFlagEvaluatorTest` lists it in both registry lists.
  - Ledger file `effort_ledger/nav_tree.md` brought over from the planning branch, with the Step 4 entry line. Checklist notes brought over and updated.
- Verification:
  - `./v3 test --smoke FeatureFlagEvaluatorTest`: passed, 487 run, 0 failed (24 flag tests). Full suite not run.
  - Probe, outside the repository, with `playwright-core` 1.58.1 in Chromium 1243, Firefox 1509 and WebKit 2248:
    - A link with `target="_blank"`, with or without `rel="noopener"`: the new tab starts with empty `sessionStorage` in all three.
    - `window.open` without `noopener`: the new tab gets a copy of the opener's `sessionStorage` in all three. The copied cursor is the opener's current visit, which is the right parent, so no detection is needed. The copy is not shared afterwards.
    - 2,000 visits with 80-character titles and thread addresses: about 320 KB, stored without error in all three.
- Notes:
  - Neither probe changes a Step 2 decision. The cap stays at 2,000; titles are cut at 120 characters to bound the size.
  - Step 3's mitigation "a copied cursor is detected" is dropped as unneeded; recorded here as new understanding, not changed scope.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 2 - Record page loads

**Original estimate: 0.15**

**Retrospective: 0.15**

- Changes:
  - New `public/assets/visit_history.js`: one local-storage key `forum_visit_history` (internal state, JSON, like `forum_nav_slots`) and one `sessionStorage` key `forum_visit_cursor`. Records each top-level page load; back and forward, page-cache restores and same-address loads add nothing; the cap keeps 2,000 visits; titles are cut at 120 characters and follow a page that renames itself. Page API `window.forumVisitHistory`: `visits()`, `current()`, `clear()`.
  - `TemplateRenderer.php` passes `visitHistoryScriptPath` only with the flag on; `layout.php` loads it right after `open_beside.js`, before page scripts.
  - New browser test `tests/browser/visit_history.mjs`.
- Verification:
  - `node tests/browser/visit_history.mjs` with `BROWSER` unset, `firefox` and `webkit`: passed in all three. Covers the reported flow, back and forward, reload, a new tab from a link (referrer parent), a fresh tab (new top), separate cursors per tab, the cap, a damaged key, storage that throws, and flag off.
  - `./v3 test --smoke FeatureFlagEvaluatorTest`: passed, 487 run. Full suite not run.
- Notes:
  - Firefox reported a reload after a page-cache restore as `navigate`. The rule became "a load at the current visit's address adds nothing", which also covers a link to the same page.
  - Key names follow `nav_slots.js` and `usage_log.js` (fixed `forum_` names), not `BrowserRuntimeProfile.php` as Step 3 said. Those two are the closer precedent for per-browser reader state.
  - The address is the path and query; `#` spots count as the same page.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 3 - Record address changes

**Original estimate: 0.08**

**Retrospective: 0.10**

- Changes:
  - `visit_history.js` wraps `pushState` and `replaceState` and listens for `popstate`. A push to a new address adds a visit under the current one; a replace moves the current visit to the new address; popstate moves the cursor. Same-address and `#spot` changes add nothing. The page scripts' own calls are unchanged.
  - The one-time hints `__v` and `created_post_id`, which `url_hints.js` drops, are never stored in an address.
  - `TemplateRenderer::renderStandalonePage` loads `visit_history.js` first on Forte pages (`/forte`, `/forte/activity`, `/forte/users`), which use `standalone_layout.php`, not `layout.php`.
  - `visit_history.mjs` gains a pushState, popstate and replaceState section and a `/forte` row click.
- Verification:
  - `node tests/browser/visit_history.mjs` in Chromium, Firefox and WebKit: passed in all three, including the `/forte` row check.
  - `./v3 test --smoke ForteBoardReaderTest FeatureFlagEvaluatorTest`: passed, 492 run. Full suite not run.
- Notes:
  - Forte pages were not in the Step 3 plan, which assumed every reader uses `layout.php`. Loading the recorder there is an in-scope refinement: Step 3 named the paned readers as captured. Recorded as new understanding.
  - A `replaceState` made while the browser is on an older entry of the same page can leave that entry with no visit; going back to it then adds one. Seen only in an artificial test sequence; left as is.
- Estimate details: original scope as planned; the Forte layout added a little. Retrospective 0.10.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 4 - Beside pane and other frames

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - `visit_history.js` runs in two lanes: the main page with `forum_visit_cursor`, and the Beside pane (a frame named `forum-open-beside` marked `data-open-beside-framed`) with `forum_visit_pane_cursor`. Pane visits carry `beside: true`. Any other frame, such as the tour's, records nothing.
  - A pane page opened from the main page hangs under the main page's visit; a link followed in the pane hangs under the pane's last visit. A restored pane and a theme reload add nothing. Open alone moves the tab's cursor to the pane's visit and adds nothing.
  - `open_beside.js` tells the recorder what each pane load is, through `window.forumVisitHistory.paneLoading("open" | "restore")` and `paneAlone()`. It does nothing when the history flag is off. `layout.php` now loads `visit_history.js` just before `open_beside.js`, because `open_beside.js` restores the pane as soon as it runs.
  - `visit_history.mjs` gains a Beside section.
- Verification:
  - `node tests/browser/visit_history.mjs` in Chromium, Firefox and WebKit: passed in all three. Covers opening beside, a link in the pane, a main-page change with the pane restored, a theme change, Open alone and another frame.
  - `node tests/browser/open_beside.mjs` (Chromium), with the history flag off and on: passed. `node tests/browser/open_beside_offline.mjs`: passed.
- Notes:
  - The Beside checks live in `visit_history.mjs`, not `open_beside.mjs` as Step 3 said, so all history behaviour is tested in one place; `open_beside.mjs` is run unchanged as a regression check.
  - WebKit once reported a cancelled `/api/forte_thread_detail` request from the Forte reader as a page error when the test left `/forte` early. The test now waits for the network to settle there. This is a test timing fix, not a product change.
  - Signals in `sessionStorage` are read once. A pane load that fails after the signal is set can leave it for the next pane load; the next open overwrites it.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 5 - Tools → History page

**Original estimate: 0.12**

**Retrospective: 0.12**

- Changes:
  - Route `/tools/history/` (and without the slash) behind the flag in `Application.php`, listed among the application routes; `ToolsPageController::history()`; `templates/pages/history.php` with `tools.css`.
  - The tree is drawn by a small script inside the page, right after its list, from `forum_visit_history`, so it is final before the rest of the page is read. A path reads down one list; where a page led to several, each branch is indented under it, newest first. Each visit shows its title (or address), its time and a "beside" mark when shown beside. Clear empties the tree; the empty note and Clear are hidden in the server HTML and set by the script. Without JavaScript one line says history is kept by JavaScript.
  - `tools.css` gains the `.visit-tree` styles, following the Usage log list.
  - `UsagePageKind::WORDS` gains `history`, so the usage log names the page `/tools/history`.
  - New PHP test `tests/VisitHistoryPageTest.php`; `visit_history.mjs` gains a History section.
- Verification:
  - `./v3 test --smoke VisitHistoryPageTest UsagePageKindTest UsageNamesTest UsageReportsPageTest`: passed, 495 run. Full suite not run.
  - `node tests/browser/visit_history.mjs` in Chromium, Firefox and WebKit: passed. Covers the drawn tree before later content, the page not being recorded, branch order, a click on a visit, Clear and a fresh tree afterwards.
- Notes:
  - Branch order uses each visit's place in storage, not its time in seconds: two visits in the same second tied in Chromium and Firefox.
  - The tree script is inline in the template rather than in a page script, because a deferred page script runs after the first paint is allowed. This follows the head scripts in `layout.php`.
  - A damaged store with a parent loop shows no row for the loop instead of hanging.
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 6 - Tools listing and icons

**Original estimate: 0.05**

**Retrospective: 0.06**

- Changes:
  - `ToolsPageSupport::registry()`: History in the "device" group after Usage Log (key and tone `history`, `/tools/history/`). `TemplateRenderer` hides it while the flag is off, as it does the usage tools.
  - `theme-nav-icons.css`: a History glyph (a node with two branches) in every set, for the launcher button and the Tools bar: pixel art (Word97, Chicago, Midnight), smooth lines (Gloss, Gloss Dark, Engagify, Spearmint) and cute pixel art (Bubblegum); plus the shared sizing rule.
  - `LocalAppSmokeTest` lists History among the tools that are off by default; `VisitHistoryPageTest` checks the launcher and Tools bar entries with the flag on and off.
  - From the visual check: on the History page each branch is now its own bar, and Clear is sized to its label.
- Verification:
  - `./v3 test --smoke VisitHistoryPageTest NavIconSetsTest UsageNamesTest LocalAppSmokeTest`: passed, 490 run. Full suite not run.
  - Icon and page checked by eye in screenshots (Chromium, outside the repository): Tools launcher in Word97, Chicago, Midnight, Gloss, Gloss Dark, Engagify, Spearmint and Bubblegum (and Light, which has no icons); History page at 1100px and 390px in the same themes. The glyph shows in each set; the tree reads as a Board with two branches.
  - `node tests/browser/visit_history.mjs` in Chromium, Firefox and WebKit after the layout change: passed.
- Notes:
  - A pushed address with the same title as its page (a `?view=` change) shows the same title twice in the tree. Left as is; page scripts that change what is shown usually change the title too.
- Estimate details: original scope as planned; the branch bars added a little. Retrospective 0.06.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Stage 7 - Acceptance and documentation

**Original estimate: 0.10**

**Retrospective: 0.10**

- Changes:
  - New concept entry `docs/concepts/visit_tree.md`; `open_beside.md` and `tools_launcher.md` updated.
  - New browser test `tests/browser/visit_history_offline.mjs`: offline, the board and a thread opened from it are recorded as a branch, and the visit takes the title the offline reader gives the page.
  - `ToolsRegistryTest` expects 24 tools; `tools_launcher.mjs` turns the History flag on so every registry tool is listed.
  - Browsing tree checklist, plans index and effort ledger updated.
- Verification (Completion Contract):
  - Vertical slice, three engines: `node tests/browser/visit_history.mjs` with Chromium, Firefox and WebKit: passed. Covers the Step 2 user flow, Beside on a 2048px window, a second tab, Clear and flag off.
  - Offline: `node tests/browser/visit_history_offline.mjs` (Chromium): passed. Firefox and WebKit offline: not run; the offline tests in this repository run in Chromium only.
  - Recovery: full storage that throws, a damaged key and flag off are covered in `visit_history.mjs`: passed.
  - `node tests/browser/tools_launcher.mjs` (Chromium, all themes, History on): passed, 46 theme and width cases.
  - `node tests/browser/open_beside.mjs` (flag off and on) and `open_beside_offline.mjs`: passed in Stage 4; `open_beside.js` is unchanged since.
  - `./v3 test --smoke VisitHistoryPageTest ToolsRegistryTest NavIconSetsTest UsageNamesTest UsagePageKindTest UsageReportsPageTest FeatureFlagEvaluatorTest ForteBoardReaderTest PreferencesPageTest`: passed, 506 run. Full suite not run.
  - One earlier smoke run had `DevServerStartTest::testFallbackServesRequestsRegistersActualPortAndStopsWithoutPcntl` fail once; it passed alone and in every later smoke run. It looks like a port-timing flake under load, not caused by this cycle; recorded here, not fixed.
  - Deployment: no schema change, no server state and no new dependency; the flag is off by default, so a deploy changes nothing until it is turned on. Production check pending deploy.
  - Final diff review: 18 source and test files changed against `2d7805c`; no duplicated components (History follows the Usage log page and styles; storage follows `nav_slots.js`). `git diff --check`: passed. Concept and plan links resolve.
- Notes:
  - Not verified: iPhone Safari on a real device, and Safari's seven-day storage wipe (accepted in Step 2).
- Estimate details: original scope as planned; no revision.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## After Step 4 - Merge with main for the PR

- Changes: merged `origin/main` (`797971a`) into `feat/nav-tree`. Conflicts in `FeatureFlagRegistry.php` (main added `FORUM_PUSH_ENABLED`; both flags kept), `docs/concepts/tools_launcher.md` (main added the Developer group; History added to its text) and `browsing_tree_checklist.md` (main's links kept; nav_tree progress kept; main's open question on Beside pane visits marked answered).
- Verification after the merge: `./v3 test --smoke VisitHistoryPageTest ToolsRegistryTest NavIconSetsTest UsageNamesTest UsagePageKindTest UsageReportsPageTest FeatureFlagEvaluatorTest ForteBoardReaderTest PreferencesPageTest`: passed, 507 run. `visit_history.mjs` (Chromium) and `visit_history_offline.mjs`: passed. Full suite not run.
- **Failed:** `node tests/browser/tools_launcher.mjs`, the 1280×720 check. With every flagged tool on (24 now), the last launcher button ends at 765 px in 11 zenmemes themes and at 772 to 774 px in Sticker and Underground Dark. Main's Developer group used up the room the test had; History adds one row to "Your device". The other launcher checks (order, hooks, icons, no overflow) pass. Not changed here: regrouping the launcher or relaxing the check is a layout decision for the maintainer. With the History flag off (the default) the launcher is as on main.

*— Claude · 2026-11-25 UTC · Model: claude-opus-5-5*

## Enhancement Requests

- Sync, in-page changes as visits, `tree_history` and `filmstrip`: unchanged from Step 2, all deferred or separate.

## Study Snapshots

2026-11-25T07:45Z; scope v2; starting revision `2d7805c8`; planning commit `f585d60`; reference R1, hashes not re-verified. Claude, single exposed rater.

- Step 4 entry: **0.70 projected total**, reaffirmed from Step 3. Stage estimates copied from Step 3, not re-rated.

2026-11-25T09:30Z; Step 4 closeout. Delivered scope v2 as planned, plus two in-scope refinements: Forte pages load the recorder, and the History page's branch bars. Starting revision `2d7805c8`; final stage commit on `feat/nav-tree`. Seven stages, all committed; no stage split or skipped.

- **Retrospective cycle assessment: 0.72**, against the 0.70 projection. Drivers: the Forte layout, Firefox's reload quirk and the visual pass on the History page. Not measured labor.
- Maintainer comfortable-fit judgment: pending.
