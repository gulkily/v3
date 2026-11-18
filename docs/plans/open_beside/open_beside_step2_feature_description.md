> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md))

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved by the maintainer 2026-11-18 UTC. Direction: Step 1 Option A, one extra pane. One wording correction after approval, below, accepted with Step 3 approval.

**Cycle estimate: 0.55**

# Step 2: Feature Description — Open beside

## Problem

On a wide screen, most of the page beside the content column is empty, and reading a second page means leaving the first. The maintainer wants a mode in which a click opens the link in a pane beside the page he is on.

## User stories

- As a reader on a wide screen, I want to click a thread on the board and read it beside the board so that I keep my place in the list.
- As a reader, I want to reply in the pane while another thread stays open on the left so that I can refer to it as I write.
- As a reader, I want the pane to still be there after I move to another page so that I don't reopen it every time.
- As a reader, I want to make the pane the whole page, or close it, in one click so that I am never stuck with it.
- As a reader who doesn't want any of this, I want the mode off by default so that links work as they always have.
- As an operator, I want one feature flag so that I can turn it all off if it breaks.

## Core requirements

- The mode is off by default. A reader turns it on with a toggle on the reading-margins ruler or a row in Preferences; the choice is kept in that browser.
- With the mode on and room for a pane, a plain click on a link inside the page's content opens that page in one pane to the right of the content column. Links in the site header and the nav bar change the main page, as today.
- The pane shows the real page without the site header, ruler or status bar. Links, likes and reply forms inside it work, and its links load in the pane.
- The pane has a slim bar with the page's title, "Open alone" (the pane's page becomes the main page) and Close.
- The browser remembers the pane's page. After the main page changes or reloads, the pane is back, with no jump in the main column.
- Ctrl-click, middle-click and links that open a new tab behave as today. A link to another site clicked in the pane opens in the whole tab, not in the pane.
- With the mode off, the flag off, no room, a screen under 640px, or JavaScript off, every page looks and behaves as it does today.

Decisions on the Step 1 questions (proposed here; change any of them before approval):
- Width: the pane fills the space between the content column and the right edge of the ruler (the 95 stop), up to the site's normal page width. It appears only when that space is at least 20rem, the width every page is built to fit. With the screenshot's margins (5 and 41) at 2048px that is a full-width page beside the column.
- No room: the mode stays on, clicks navigate as today, and the toggle stays visible so the reader can make room with the ruler.
- The toggle shows whenever the ruler shows. Its label is "Beside" for now.
- Closing is the Close button only. Clicking the link already shown in the pane loads it again.
- Window controls work inside the pane as on any page. Float already hides itself under 640px of width, and inside a pane that is the pane's width, so no new rule is needed.
- The pane never opens a pane of its own.

Correction, 2026-11-18 UTC (after approval; wording only, no change in behaviour): the ruler was rebuilt on `main` in PR #263 while this step was in review. Its stops now count fifths of the normal column, from -8 to 13, and it has no 95 stop. Wherever this document says "the right edge of the ruler (the 95 stop)", read "the right edge of the window, less a small gutter". Wherever it says "margins 5 and 41", read "a column narrowed to the left third of the window, as in the thread's screenshot".

## Delivery scope

- Work type: application change.
- Completion boundary: a reader on a desktop turns on Beside on the ruler of the board, clicks a thread, and reads it beside the board. They reply in the pane, click Board in the nav bar and still see the pane, press Open alone, and later press Close. Release condition: merged and deployed to zenmemes.com with `FORUM_OPEN_BESIDE_ENABLED` on, and checked by the maintainer.
- Recovery: a reader closes the pane or turns the mode off; "Reset all preferences" clears both. A broken pane is turned off for everyone with the flag.
- Known user and environment: the maintainer, in Firefox on a 2048px-wide desktop, Gloss Dark, reading margins at 5 and 41 (the thread's screenshot). Ordinary entry: the Prompts page or the board with those margins. The condition to handle: the empty space to the right fills with the clicked page. He verifies by clicking a prompt's title and seeing its thread beside the list. Evidence: a browser test run in Firefox at that size, plus his own check after deploy. Chrome evidence alone does not count.

## Risks

- **A framed page shows its header for a moment.** Pages are static files, so the framed page must hide its own header. Impact: a flash on every pane load. Earliest validation: a browser test that loads a page in a frame and checks the header is hidden at first paint. Mitigation: mark the framed page before paint in `layout.php`, beside the reading margins block.
- **Catching clicks breaks links.** Impact: a link that should download, open a new tab or submit could be swallowed. Earliest validation: a browser test over Ctrl-click, middle-click, `target` links, external links and form buttons. Mitigation: catch only plain left clicks on same-site page links inside the content area.
- **The pane and the main page disagree.** Theme changes, sign-in and new posts in one do not show in the other until it reloads. Impact: a pane in the old theme, or a board missing the reply just posted. Earliest validation: change the theme with a pane open. Mitigation: the pane reloads when the theme or sign-in state changes; stale lists wait for a later cycle and are noted as a limitation.
- **Firefox differs.** The only browser used for tests so far is Chrome. Impact: the named environment goes unchecked. Earliest validation: try to run the browser test in Firefox at the start of Step 4. Mitigation: if Firefox cannot run here, say so in the summary and leave the release check to the maintainer.

## Shared component inventory

- Before-paint state: `templates/layout.php` head script and `BrowserRuntimeProfile.php` storage keys (`readingMarginsStorageKey`, `windowControlsStorageKey`). Extended with keys for the mode and the pane's page, and with a framed-page mark.
- Ruler: `templates/partials/reading_margins.php`, `public/assets/reading_margins.js`, `public/assets/reading-margins.css`. Extended with the toggle; the column's position (`--reading-left`, `--reading-right`) is reused to place the pane.
- Feature flags: `FeatureFlagRegistry.php`, following `FORUM_READING_MARGINS_ENABLED` and `FORUM_DRAGGABLE_WINDOWS_ENABLED`. One new flag, `FORUM_OPEN_BESIDE_ENABLED`, off by default. The ruler toggle needs the reading margins flag too; the Preferences row does not.
- Preferences: `templates/pages/preferences.php` and `public/assets/preferences.js`, as the Reading margins and Window controls rows use them. Extended with one row and the reset.
- Page shell: `.site-header`, `.reading-margins`, `.main`, `.site-status-bar` in `layout.php`. Used unchanged; the framed mark hides the first, second and last.
- Link handling: `public/assets/nav_cached_copy.js` and `auth_navigation.js` already listen for link clicks in the header. Left unchanged; the new handler covers only links inside `.main`.
- Frames: `templates/tour/page.php` frames site pages for the tour. Not reused (it scales fixed-size snapshots), but it shows same-site framing works.
- Window controls: `public/assets/theme-bubblegum.js`. Used unchanged.
- Tests: `tests/browser/reading_margins.mjs`, `preferences.mjs` and `draggable_windows.mjs` as patterns for a new browser test.

Concepts touched: new inventory entry `docs/concepts/open_beside.md`. [Window controls](../../concepts/window_controls.md), [no-script presentation](../../concepts/no_script_presentation.md), [static publication](../../concepts/static_publication.md) and [theme script](../../concepts/theme_script.md) are used unchanged.

## User flow

1. On a desktop, a reader narrows the column with the ruler and presses Beside at its end.
2. They click a thread on the board. It opens in a pane to the right; the board stays where it was.
3. They click a link inside the pane. It loads in the pane.
4. They write a reply in the pane and post it.
5. They click Recent in the nav bar. The main page changes; the pane is still there.
6. They press Open alone. The pane's page becomes the main page and the pane closes.

## Success criteria

- At 2048px with margins 5 and 41, a click on a board thread shows it in the pane, and the main page's address and scroll position do not change, in Chrome and in Firefox.
- A framed page shows no header, ruler or status bar at first paint in a browser test.
- After the main page changes, the pane shows the same page again and the main column does not move once painted.
- Ctrl-click, middle-click, `target` links, external links and form buttons behave as before in a browser test.
- With the flag off, the mode off, a 390px screen, or JavaScript off, pages match today's behaviour in the existing browser tests.

Enhancement requests:
- Prompt cards in columns on a wide Prompts page: parked, not part of this cycle (unchanged from Step 1).
- No-JavaScript support: skipped for now at the maintainer's direction (unchanged from Step 1).
- A strip of panes, pin, a pane list, "Updated, reload" hints and spines: later cycles, recorded in the [open beside design notes](../open_beside_design_notes.md).

## Study snapshots

- Step 2 handoff: 0.55 projected total, up from 0.50. Reason: new understanding of scope, not changed scope. External links in the pane, keeping the pane from opening its own pane, and reloading the pane on a theme or sign-in change were not counted at Step 1. Scope v1, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified in this session.
- Pending ledger update: unchanged from Step 1; it belongs in the planning commit after Step 3 approval.

*— Claude · 2026-11-17 UTC · Model: claude-fable-5-2*
