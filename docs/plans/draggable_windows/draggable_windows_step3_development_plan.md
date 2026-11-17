> **Feature plan:** [Step 1](./draggable_windows_step1_solution_assessment.md) · [Step 2](./draggable_windows_step2_feature_description.md) · [Step 3](./draggable_windows_step3_development_plan.md) · [Step 4](./draggable_windows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./draggable_windows_debug_log.md))

Effort study: `railspike-effort` / `v3/draggable_windows`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 3 approved by the maintainer 2026-11-17 UTC.

**Cycle estimate: 0.70**

# Step 3: Development Plan — Draggable windows

## Completion Contract

- Normal entry: with `FORUM_DRAGGABLE_WINDOWS_ENABLED` on, a reader picks Bubblegum from the theme menu on the board or a thread.
- End-to-end outcome: they collapse a post, reload and see it collapsed from first paint. On desktop they turn on Float mode, drag a window, press Reset, then pick Gloss and nothing from Bubblegum is left. In Preferences, "Window controls: Off" removes the buttons and ignores saved collapses.
- Recovery: an operator turns the flag off; a reader turns the row off, expands posts, or uses "Reset all preferences".
- Deployment: no schema change, no migration, no rebuild. A deploy ships CSS, JavaScript and templates only.
- Release condition: merged and deployed to zenmemes.com, then checked by the maintainer in Bubblegum on desktop and on his iPhone SE. Until then the cycle is technically complete but not accepted.

## Key Risks

- **High risk:** a remembered collapse flashes open on load. Impact: breaks the first-paint rule. Early validation: Stage 1's browser test, before any button exists. Mitigation: `layout.php` marks collapsed posts before paint; the script never decides that.
- **High risk:** dragging breaks reading. Impact: lost text selection, swallowed link clicks, stuck touch scrolling. Early validation: Stage 4's browser test with Float mode on. Mitigation: drag starts only on a title bar, only in Float mode, only with a fine pointer.
- Collapsing a composer hides a draft. Impact: a reader thinks the draft is gone. Early validation: Stage 2 collapses the reply composer with text in it. Mitigation: collapse only hides; nothing is removed or reset.
- Real buttons drift from the drawn title bar. Impact: misaligned pixel art. Early validation: Stage 2 screenshots at about 380px and 1280px. Mitigation: remove the drawn boxes and style the real buttons instead.

Concepts touched: [theme script](../../concepts/theme_script.md) used unchanged; [visitor intro](../../concepts/visitor_intro.md) and [post presentation](../../concepts/post_presentation.md) used unchanged; new `docs/concepts/window_controls.md`, written in Stage 6.

## Stage 1 — Flag, stored collapses and before-paint restore

**Estimate: 0.12**

- Goal: prove a collapsed post can be shown collapsed from first paint.
- Dependencies: none.
- Expected changes: add `FORUM_DRAGGABLE_WINDOWS_ENABLED` to `FeatureFlagRegistry.php` (off, with a one-line description). Add `windowControlsStorageKey` and `collapsedWindowsStorageKey` to `BrowserRuntimeProfile.php`. In `layout.php`, when the flag is on, Bubblegum is active and controls aren't off, mark saved post IDs as collapsed before paint. Add the collapsed look to `theme-bubblegum.css`.
- Verification approach: new browser test `tests/browser/draggable_windows.mjs`: a seeded collapse shows collapsed on first paint, and nothing changes with the flag off, another theme, or controls off. Smoke set plus `FeatureFlagsBehaviorTest`.
- Estimate details: driver: the before-paint check; uncertainty: static thread pages and the pre-paint theme choice.
- Risks or open questions: the first High risk above. A post ID saved on one site must not leak to another; keys use the runtime namespace.
- Canonical components/API contracts touched: `layout.php` pre-paint script (extended, as reading margins), `BrowserRuntimeProfile.php` (extended), `FeatureFlagRegistry.php` (extended).

## Stage 2 — Bubblegum theme script and working collapse

**Estimate: 0.18**

- Goal: every Bubblegum window gets a working collapse button.
- Dependencies: Stage 1.
- Expected changes: set `'script' => true` on Bubblegum in `ThemeRegistry.php` and add `public/assets/theme-bubblegum.js` with `start` and `stop`, following `theme-engagify.js`. Start adds one collapse button to each `.card`'s title bar. Posts save their state by `data-post-id`; other windows don't. A collapsed post shows its author from `data-author`. Double-clicking a title bar toggles it. Replace the drawn three boxes in `.card::before` with the real button, and keep the visitor intro's close box.
- Verification approach: browser test for mouse, keyboard and tap; `aria-expanded` changes; reload keeps a post collapsed; a composer with text keeps its text. `theme_scripts.cjs` confirms stop removes everything. Screenshots at about 380px and 1280px.
- Estimate details: driver: about 70 kinds of `.card`; uncertainty: cards whose own layout fights a hidden body.
- Risks or open questions:
  - Impact: a page whose only window collapses looks empty. Early warning: Tools and profile screenshots. Mitigation: the title bar always stays visible.
  - Continuation posts from `FORUM_COLLAPSE_SAME_AUTHOR_POSTS_ENABLED` must still look right collapsed.
- Canonical components/API contracts touched: `forumThemeScripts` registry (reused), `ThemeRegistry.php` (one entry changed), `theme-bubblegum.css` (extended), card templates (read only).

## Stage 3 — Active window and pop

**Estimate: 0.07**

- Goal: windows feel alive without moving.
- Dependencies: Stage 2.
- Expected changes: clicking or focusing inside a window marks it active; other title bars fade. A short pop plays when a window opens from collapsed. `prefers-reduced-motion` turns the pop off.
- Verification approach: browser test that tabbing moves the active window; screenshot of active and inactive title bars; contrast check on the faded title bar.
- Estimate details: driver: focus events across nested controls; uncertainty: low.
- Risks or open questions: a faded title bar may fail contrast. Mitigation: fade the bar colour only, never the title text.
- Canonical components/API contracts touched: `theme-bubblegum.js`, `theme-bubblegum.css`.

## Stage 4 — Float mode, dragging and Reset

**Estimate: 0.20**

- Goal: on desktop, windows can be dragged and put back.
- Dependencies: Stage 3.
- Expected changes: a small Float toggle in the site header, shown only in Bubblegum with a fine pointer. In Float mode, pointer events on a title bar move its window, and arrow keys move a focused window. Reset returns every window and turns Float mode off. Positions live only in the page.
- Verification approach: browser test: drag moves a window; text selection and link clicks inside it still work; Reset restores layout; touch-only devices never see the toggle. Desktop screenshots in Float mode.
- Estimate details: driver: pointer capture and scroll interaction; uncertainty: windows inside scrolling containers.
- Risks or open questions: the second High risk above. Where the toggle sits in the header needs a screenshot review in Step 4.
- Canonical components/API contracts touched: `theme-bubblegum.js`, `theme-bubblegum.css`, site header markup (read only; the script adds the toggle).

## Stage 5 — Preferences row

**Estimate: 0.06**

- Goal: a reader can turn window controls off in this browser.
- Dependencies: Stage 2.
- Expected changes: pass the flag from `ToolsPageController.php`. Add a "Window controls" On/Off row to `preferences.php` beside Reading margins. `preferences.js` saves it, stops the theme script at once, and clears collapses on "Reset all preferences".
- Verification approach: extend `tests/browser/preferences.mjs`: Off removes buttons without a reload, survives reload, and Reset all clears collapses. Row hidden when the flag is off.
- Estimate details: driver: live stop from another page's script; uncertainty: low.
- Risks or open questions: none beyond Stage 2's.
- Canonical components/API contracts touched: `preferences.php`, `preferences.js`, `ToolsPageController.php` (all extended).

## Stage 6 — Docs and final review

**Estimate: 0.07**

- Goal: record the feature and check it across the site.
- Dependencies: Stages 1–5.
- Expected changes: write `docs/concepts/window_controls.md`. Note Bubblegum's script in `docs/runbooks/theme_development_guide.md`. Update the flag's description if wording changed.
- Verification approach: screenshots on the board, a thread, a profile, Tools and Preferences at both widths, with the flag on and off and JavaScript off. `git diff --check`. Review the diff for duplication and scope. Smoke set plus changed tests; the full suite is not run.
- Estimate details: driver: screenshot sweep; uncertainty: low.
- Risks or open questions: other themes must be unchanged. Mitigation: Gloss and Word97 screenshots in the sweep.
- Canonical components/API contracts touched: concept inventory, theme guide.

Enhancement requests: none since Step 2.

## Study snapshots

- Step 3 handoff: 0.70 reaffirmed; the plan adds verification but no scope. Stage estimates 0.12, 0.18, 0.07, 0.20, 0.06, 0.07 are not summed. Scope v2, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified in this session.
- Pending ledger update: this cycle's row goes into `effort_estimation_trial.md` in the planning commit.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
