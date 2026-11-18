> **Feature plan:** [Step 1](./open_beside_step1_solution_assessment.md) · [Step 2](./open_beside_step2_feature_description.md) · [Step 3](./open_beside_step3_development_plan.md) · [Step 4](./open_beside_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./open_beside_debug_log.md))

Effort study: `railspike-effort` / `v3/open_beside`; [host ledger](../effort_ledger/open_beside.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved by the maintainer 2026-11-18 UTC, including the Step 2 wording correction.

**Cycle estimate: 0.60**

# Step 3: Development Plan — Open beside

## Completion Contract

- Normal entry: a reader on a desktop opens the board, narrows the column with the ruler and presses Beside at the ruler's end. Preferences has the same switch.
- End-to-end outcome: a click on a thread shows it in a pane beside the board. Links, likes and the reply form work in the pane. The pane survives a change of the main page. Open alone makes the pane's page the main page; Close removes it.
- Recovery: Close, the Beside toggle, the Preferences row and "Reset all preferences" for a reader; `FORUM_OPEN_BESIDE_ENABLED` for the operator.
- External verification: the reported condition is replayed in Firefox at 2048px with the column narrowed to the left (Stage 6). After deploy, the maintainer checks it on zenmemes.com.
- Release condition: merged, deployed with the flag on, and checked by the maintainer. Merge and deploy need the maintainer.

## Key Risks

- **A framed page flashes its header.** Impact: a flicker on every pane load. Early validation: Stage 1 builds only the framed-page mark and tests first paint, before any pane exists. Mitigation: the mark is set in `layout.php`'s head script.
- **High risk: caught clicks break links.** Impact: Ctrl-click, new-tab links, downloads or form buttons stop working for readers with the mode on. Early validation: Stage 3's test covers each kind before the pane is remembered across pages. Mitigation: only plain left clicks on same-site page links inside `.main` are caught; everything else is left alone.
- **Firefox cannot run here.** Impact: the named environment is unchecked. Early validation: Stage 1 tries to run its test in Firefox. Mitigation: if it cannot, Stage 6 records that plainly, and the release check falls to the maintainer.
- **The ruler changed under the plan.** PR #263 rebuilt it (stops in fifths of the normal column, the header moves with the column) after Step 2 was written. Impact: the pane's position rules must use the new variables. Early validation: Stage 2 places the pane against `--reading-offset` and `--reading-width` and checks it at three widths. Mitigation: Step 2 carries a wording correction; no behaviour changed.

## Stage 1

**Estimate: 0.08**

- Goal: a page loaded in the pane's frame shows no site header, ruler or status bar from first paint. Nothing is visible to readers yet.
- Dependencies: none.
- Expected changes: a new flag `FORUM_OPEN_BESIDE_ENABLED`, off by default, passed to the layout. With it on, the head script marks the page as framed when it is inside a same-site frame with the pane's name. A new stylesheet hides the header, ruler and status bar on a framed page.
- Verification approach: a new browser test, `tests/browser/open_beside.mjs`, loads a thread in a frame with the pane's name and checks the three elements are hidden at first paint, and shown in a frame with another name and at top level. `FeatureFlagEvaluatorTest`. One attempt to run the test in Firefox.
- Estimate details: driver: wiring a flag through the registry, renderer and layout; uncertainty: whether Firefox can be installed here.
- Risks or open questions:
  - Impact: the tour page frames site pages too and must not lose their headers.
  - Early warning / validation: the test's "another name" case.
  - Mitigation: only the pane's frame name sets the mark.
- Canonical components/API contracts touched: `FeatureFlagRegistry.php` (new flag, following `DRAGGABLE_WINDOWS_ENABLED`); `TemplateRenderer.php` and `templates/layout.php` head script (extended); `.site-header`, `.reading-margins`, `.site-status-bar` (used unchanged).

## Stage 2

**Estimate: 0.14**

- Goal: with the mode on and room beside the column, a click on a link in the page's content opens that page in a pane.
- Dependencies: Stage 1.
- Expected changes: a Beside toggle at the end of the ruler, shown whenever the ruler shows. Two storage keys, for the mode and for the pane's page. The mode is restored before paint. A new script catches plain left clicks on same-site page links inside `.main` and shows the page in a frame fixed to the window, full height, between the column's right edge and the window's right edge, up to the normal page width. The pane appears only with at least 20rem of room; without it, clicks navigate as today.
- Verification approach: the browser test turns the mode on at 2048px with a narrowed column, clicks a board thread, and checks the pane's page, that the main address and scroll did not change, no sideways scrolling, and that at 1280px with the normal column a click navigates. Screenshots at 1280px and 2048px.
- Estimate details: driver: placing the pane from the ruler's variables and recomputing on resize; uncertainty: the header now moves with the column, which may leave or take room at the top.
- Risks or open questions:
  - Impact: the toggle crowds the ruler's last stops.
  - Early warning / validation: screenshots at 640px and 1280px.
  - Mitigation: the toggle sits at the window's right edge, outside the stops.
- Canonical components/API contracts touched: `templates/partials/reading_margins.php`, `public/assets/reading-margins.css` (extended with the toggle; `--reading-offset` and `--reading-width` reused); `BrowserRuntimeProfile.php` (two keys added); new `public/assets/open_beside.js` and `open-beside.css` (no existing equivalent: `nav_cached_copy.js` and `auth_navigation.js` handle header links only and stay unchanged).

## Stage 3

**Estimate: 0.12**

- Goal: the pane has its bar, and every kind of link behaves as Step 2 says.
- Dependencies: Stage 2.
- Expected changes: a slim bar over the pane with the page's title, Open alone and Close. The title follows the pane as its page changes. Open alone loads the pane's page as the main page and forgets the pane. Close removes and forgets it. In a framed page, a link to another site opens in the whole tab, and the mode never opens a pane inside the pane. Opening a pane is announced to screen readers; focus stays on the link.
- Verification approach: the browser test covers Ctrl-click, middle-click, a `target="_blank"` link, an external link in the main page and in the pane, a link clicked inside the pane, a like and a reply posted in the pane, Open alone, Close, and keyboard use of the bar.
- Estimate details: driver: the list of link kinds; uncertainty: reply posting inside a frame, which signs in the browser.
- Risks or open questions:
  - Impact: signing or posting scripts assume they run at top level.
  - Early warning / validation: the reply case in the test, run first.
  - Mitigation: fix the assumption where it is found; if it is deep, stop and report before going on.
- Canonical components/API contracts touched: `public/assets/open_beside.js`, `open-beside.css` (extended); `templates/layout.php` framed-page block (extended); `browser_signing.js` and the reply form (used unchanged unless the risk above appears).

## Stage 4

**Estimate: 0.12**

- Goal: the pane is still there after the main page changes or reloads, and follows a theme change.
- Dependencies: Stage 3.
- Expected changes: the pane's page is saved whenever it changes. On every page with the mode on, room and a saved page, the pane is created before the page's content paints, so the main column does not move. The pane's scroll position is saved and restored as well as the browser allows. When the theme changes in the main page, the pane reloads. When room disappears on resize the pane hides, and returns when room does.
- Verification approach: the browser test opens a pane, clicks Recent in the nav bar, reloads, resizes below and above the room limit, and switches theme; each time it checks the pane's page and that the main column's position is unchanged after first paint.
- Estimate details: driver: creating the frame early enough; uncertainty: scroll restore, which may only be approximate.
- Risks or open questions:
  - Impact: a saved page that no longer exists shows an error page in the pane on every visit.
  - Early warning / validation: a test with a saved address that returns "not found".
  - Mitigation: the pane shows the site's own not-found page, and Close forgets it.
- Canonical components/API contracts touched: `templates/layout.php` head script (extended, beside reading margins and window controls); `public/assets/open_beside.js` (extended); `public/assets/theme_toggle.js` (used unchanged if it already signals a theme change, otherwise extended with one signal).

## Stage 5

**Estimate: 0.06**

- Goal: the mode can be switched in Preferences and cleared with everything else.
- Dependencies: Stage 2.
- Expected changes: a row "Open links beside", On or Off, shown only when the flag is on. It works with the reading margins flag off, when the ruler toggle is absent. "Reset all preferences" turns the mode off and forgets the pane.
- Verification approach: `tests/browser/preferences.mjs` and `PreferencesPageTest` extended for the row, the flag-off case and the reset.
- Estimate details: driver: following the Window controls row; uncertainty: low.
- Risks or open questions:
  - Impact: the row and the ruler toggle disagree after one is changed.
  - Early warning / validation: the test changes one and reads the other.
  - Mitigation: both read the same storage key.
- Canonical components/API contracts touched: `templates/pages/preferences.php`, `public/assets/preferences.js`, `ToolsPageController.php` (each extended by one row, as Window controls did).

## Stage 6

**Estimate: 0.10**

- Goal: the reported condition is replayed, the feature is documented, and the cycle is closed.
- Dependencies: Stages 1 to 5.
- Expected changes: the browser test gains the reported case: 2048px wide, Gloss Dark, the column narrowed to the left, a prompt's title clicked on the Prompts page, its thread shown beside the list. It runs in Firefox if Stage 1 found Firefox can run here; if not, the summary says so. A concept entry, `docs/concepts/open_beside.md`. The feature flag reference. The design notes and the plans index are updated. The feedback item gets its evidence and workflow state.
- Verification approach: the reported case in Chrome and, if possible, Firefox. Screenshots at 390px, 1280px and 2048px in every theme, checked by eye. With the flag off and with the mode off, the existing `reading_margins.mjs`, `preferences.mjs` and `draggable_windows.mjs` pass unchanged. The smoke set and `PresentationProfileMatrixTest`. A final read of the whole diff. The full suite is left to the maintainer.
- Estimate details: driver: screenshots across themes; uncertainty: Firefox.
- Risks or open questions:
  - Impact: a theme draws the pane's bar unreadably.
  - Early warning / validation: the screenshots.
  - Mitigation: the bar uses the themes' existing panel and line colours.
- Canonical components/API contracts touched: `docs/concepts/` inventory (one entry added); `docs/feedback/items/PF-20261117-F10.txt`; `docs/plans/open_beside_design_notes.md`; `docs/plans/README.md`; `docs/plans/effort_estimation_trial.md`.

Concepts touched: new `open_beside`. [Window controls](../../concepts/window_controls.md), [no-script presentation](../../concepts/no_script_presentation.md), [static publication](../../concepts/static_publication.md) and [theme script](../../concepts/theme_script.md) used unchanged; Stage 6 checks each still reads true.

Branch: `feat/open_beside`, started from the unpushed docs commit that adds the design notes and the feedback item, so one PR carries both.

## Study snapshots

- Step 3 handoff: 0.60 projected total, up from 0.55. Reason: new understanding, not changed scope. The ruler was rebuilt on `main` (PR #263), so the pane's placement is planned against variables not yet worked with, and the Firefox replay is its own piece of work. Stage work 0.08, 0.14, 0.12, 0.12, 0.06, 0.10, not summed. Scope v1, R1, P2; Claude single exposed rater, 2026-11-18 UTC. R1 hashes not re-verified in this session.
- Ledger: this cycle's row in `effort_estimation_trial.md` is written in the planning commit after Step 3 approval.

*— Claude · 2026-11-18 UTC · Model: claude-fable-5-2*
