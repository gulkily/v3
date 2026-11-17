> **Feature plan:** [Step 1](./draggable_windows_step1_solution_assessment.md) · [Step 2](./draggable_windows_step2_feature_description.md) · [Step 3](./draggable_windows_step3_development_plan.md) · [Step 4](./draggable_windows_step4_implementation_summary.md)

Debug mode: enabled ([debug log](./draggable_windows_debug_log.md))

Effort study: `railspike-effort` / `v3/draggable_windows`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

Status: Step 2 approved by the maintainer 2026-11-17 UTC, with one flag and a Preferences row (see Enhancement requests). Direction: Step 1 Option A, a Bubblegum theme script with Float mode.

**Cycle estimate: 0.70**

# Step 2: Feature Description — Draggable windows

## Problem

Bubblegum's windows are a still picture: the title bar ignores clicks, nothing collapses, and nothing moves. The maintainer wants a full window-manager feel, starting with Bubblegum.

## User stories

- As a reader in Bubblegum, I want to collapse a long post to its title bar so that I can skim a thread.
- As a reader, I want a post I collapsed to stay collapsed after a reload so that I don't redo it every visit.
- As a reader on desktop, I want to turn on Float mode and drag windows around so that the board feels like a little desktop.
- As a reader who dragged things into a mess, I want one Reset so that every window goes back in place.
- As the maintainer demoing on an iPhone SE, I want collapse to work by tap so that it makes a 5–15 second demo.
- As an operator, I want one feature flag so that I can turn all of this off if it breaks.
- As a reader who doesn't want window controls, I want to turn them off in Preferences so that Bubblegum stays a still picture for me.

## Core requirements

- Every window on a Bubblegum page gets a working collapse button in its title bar. Collapse hides everything below the title bar; a second press or a double-click on the title bar opens it again.
- A collapsed post stays collapsed after a reload, from first paint, with no jump. Other windows reset on reload.
- Clicking or tabbing into a window makes it active; the other title bars fade.
- Float mode is off by default and desktop-only. When it is on, a window can be dragged by its title bar with a mouse, and moved with the keyboard. Reset puts every window back. Positions last until the page reloads.
- Preferences shows a "Window controls" row, On by default, only when the flag is on. Off removes the buttons and ignores saved collapses in this browser. "Reset all preferences" also clears saved collapses.
- Picking another theme removes every button, listener and position without a reload. With JavaScript off, or `FORUM_DRAGGABLE_WINDOWS_ENABLED` off, pages look exactly as they do today.

Decisions on the Step 1 questions (proposed here; change any of them before approval):
- All windows get buttons, not only posts, because the request is a full window manager. Only posts remember their state, because only posts have a stable ID.
- Window positions are not remembered across reloads in this cycle.
- A window list or taskbar, Cascade and Arrange wait for a follow-up cycle.
- A collapsed post shows its author's name in its title bar, so a row of collapsed posts can still be told apart.
- Every drawn button must do something. The title bar draws one collapse button, plus the close box where a window can already close (the visitor intro). The other two drawn boxes go.

## Delivery scope

- Work type: application change.
- Completion boundary: a reader picks Bubblegum from the theme menu on the board or a thread. They collapse a post, reload and see it still collapsed. On desktop, they turn on Float mode, drag a window, press Reset, and switch to Gloss with no Bubblegum controls left behind. Release condition: merged and deployed to zenmemes.com, and checked by the maintainer in Bubblegum on desktop and on his iPhone SE.
- Recovery: a broken script is turned off with the flag; a reader's saved collapses are cleared by expanding the posts, or by clearing site storage.

## Risks

- **Collapse jumps after paint.** Theme scripts start after first paint, so a remembered collapse could flash open. Impact: the page breaks the first-paint rule. Earliest validation: a before-paint check in `layout.php` beside the reading margins one, tested in a browser test before any buttons are built. Mitigation: the check marks collapsed posts before paint; the script only adds buttons.
- **Dragging fights reading.** A drag could start a text selection, swallow a link click or stop touch scrolling. Impact: worse reading than today. Earliest validation: a browser test that selects text and follows a link with Float mode on. Mitigation: drag only from the title bar, only in Float mode, only on desktop.
- **The title bar is a picture.** `.card::before` ignores clicks and draws all three boxes as one image. Impact: real buttons must line up with the drawing at every width. Earliest validation: screenshots at about 380px and desktop width. Mitigation: draw the title bar's buttons as the real buttons, not over a picture.
- **Card variety.** `.card` appears in about 70 templates, including forms and composers. Impact: collapsing a composer could hide a draft. Earliest validation: a list of window kinds on the board, a thread, a profile and Tools. Mitigation: a collapsed window keeps its contents, only hidden.

## Shared component inventory

- Theme script registry: `public/assets/theme_toggle.js` (`forumThemeScripts`) and `ThemeRegistry.php` (`'script' => true`). Reused unchanged; Bubblegum gets `public/assets/theme-bubblegum.js`, following `theme-engagify.js`'s start and stop pattern.
- Before-paint state: `templates/layout.php` and `BrowserRuntimeProfile.php` storage keys, as reading margins uses them. Extended with one key for collapsed posts.
- Feature flags: `FeatureFlagRegistry.php`, following `FORUM_READING_MARGINS_ENABLED`. One new flag, off by default.
- Preferences: `templates/pages/preferences.php`, `public/assets/preferences.js` and the `ToolsPageController.php` flag wiring, as reading margins and thread view use them. Extended with one row; `tests/browser/preferences.mjs` covers it.
- Window look: `public/assets/theme-bubblegum.css` (`.card`, `.card::before`, the visitor intro's close box). Extended, not copied.
- Window markup: `post_card.php`, `thread_root_card.php`, `quote_card.php`, `visitor_intro.php`. Used unchanged; the script finds windows by their existing classes and `data-post-id`.
- Same-author collapse (`FORUM_COLLAPSE_SAME_AUTHOR_POSTS_ENABLED`, `.continuation`): a different feature that folds runs of replies. Left unchanged; a collapsed continuation post must still look right.
- Tests: `tests/browser/theme_scripts.cjs` and `tests/browser/reading_margins.mjs` as patterns for a new browser test.

Concepts touched: [theme script](../../concepts/theme_script.md), used unchanged. [Visitor intro](../../concepts/visitor_intro.md) and [post presentation](../../concepts/post_presentation.md), used unchanged. New inventory entry: `docs/concepts/window_controls.md`, for collapse, active window and Float mode.

## User flow

1. A reader picks Bubblegum from the theme menu.
2. Each window's title bar shows a collapse button.
3. They press it on a long post. The post shrinks to its title bar with the author's name.
4. They reload. The post is still collapsed from the first frame.
5. On desktop, they turn on Float mode and drag a window by its title bar.
6. They press Reset, and every window returns to its place.
7. They pick Gloss. Every button and position is gone without a reload.

## Success criteria

- Collapse works by mouse, keyboard and tap on desktop and at about 380px. A screen reader announces it as expanded or collapsed.
- A remembered collapse shows on first paint, with no visible jump in a browser test.
- With Float mode on, text selection and links in a window still work in a browser test.
- Switching away from Bubblegum leaves no added elements or listeners, checked by `theme_scripts.cjs`.
- With JavaScript off or the flag off, Bubblegum pages match today's screenshots.

Enhancement requests:
- Request: one flag is enough. Disposition: confirms the existing requirement. Approval: approved with Step 2. Outcome: no change.
- Request: expose the feature in Preferences too. Disposition: in-scope refinement, added to Core requirements before approval, because it reuses the existing Preferences rows. Approval: approved with Step 2. Outcome: planned in Step 3.

## Study snapshots

- Step 2 handoff: 0.65 projected total, up from 0.60. Reason: new understanding of scope, not changed scope. Every window gets buttons, not only posts, and the title bar must be redrawn as real buttons. Scope v1, R1, P2; Claude single exposed rater, 2026-11-17 UTC. R1 hashes not re-verified in this session.
- Step 2 revision at approval: 0.70, up from 0.65. Reason: changed scope, the Preferences row the maintainer asked for. Scope v2 (v1 plus the Preferences row).
- Pending ledger update: unchanged from Step 1; it belongs in the planning commit after Step 3 approval.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
