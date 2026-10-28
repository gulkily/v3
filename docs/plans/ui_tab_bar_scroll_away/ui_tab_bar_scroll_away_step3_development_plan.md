> **Feature plan:** [Step 1](./ui_tab_bar_scroll_away_step1_solution_assessment.md) · [Step 2](./ui_tab_bar_scroll_away_step2_feature_description.md) · [Step 3](./ui_tab_bar_scroll_away_step3_development_plan.md) · [Step 4](./ui_tab_bar_scroll_away_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tab_bar_scroll_away` ([ledger](../effort_estimation_trial.md#cycle-v3ui_tab_bar_scroll_away)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.45**

Step 2 approved 2026-10-28 ("Approved Step 2"), no scope added since. Branch `feat/ui-tab-bar-scroll-away` from `main` (`7ef735dd2`), independent of the unmerged `feat/ui-thread-reply-dock`.

## Completion Contract

- Normal entry: any ZenMemes page (`data-shell="app"`) at phone width (≤ 639px), guest or member.
- Outcome: a deliberate scroll down slides the tab bar off screen; scrolling up, reaching the top or end of the page, or focusing into the bar brings it back; it stays while the You menu is open.
- Recovery: without JavaScript, or if the script fails, the bar stays visible as today; every page load starts with it shown.
- Deployment: none beyond the normal deploy; the script loads only in the app shell.
- Release: focused tests and the navigation, reply and private message browser tests pass; 13 permitted themes plus Auto checked at 390px with the bar shown and hidden; desktop and other sites' HTML unchanged; one full-suite run with pre-existing failures recorded; iPhone feel left pending for the user.

## Key Risks

- **High risk:** flicker during iOS momentum, bounce and toolbar resizing. Impact: the bar jumps in and out. Validation: Stage 1 browser test that scrolls past both page ends and sends small back-and-forth deltas. Mitigation: clamp the scroll position to the page range, ignore changes below a threshold (about 8px), and show the bar within a small distance of either end.
- **Layout jump when the bar hides.** Impact: content shifts and Page Down lands wrongly. Mitigation: the bar moves with `transform` only; `.main` keeps its bottom padding so the last content is reachable when the bar returns at the page end.
- Dependents left above a gap. Validation: Stage 2 screenshots of the post-send reply box and private message composer with the bar hidden. Mitigation: one visible-offset value set together with the hidden state.
- Hidden bar keeps focusable links. Mitigation: `focusin` shows the bar before the browser scrolls to it; tested with Tab.

## Stage 1 - Bar hides on scroll down and returns

**Estimate: 0.22**

- Goal: on ZenMemes phone layouts the bar slides away after a deliberate scroll down and returns on scroll up, near either page end, on focus, and while the account menu is open.
- Dependencies: none.
- Expected changes: new `public/assets/tab_bar_scroll.js` (no third-party code) loaded with `defer` only when the navigation slot is `app`, through the existing asset path helper; it sets `data-tabbar="hidden"` on `.shell`, reads `matchMedia('(max-width: 639px)')`, and does nothing on wider screens. `site.css` gains the hidden state (`transform: translateY(100%)`, `transition: transform`, none under `prefers-reduced-motion`).
- Verification approach: new `tests/browser/tab_bar_scroll.mjs` at 390px with touch: hide after scroll down, show after scroll up, at top, at bottom, on Tab into the bar, while the account menu is open; overscroll and jitter do not toggle; JavaScript off keeps it visible; 1280px never hides. PHP test that the script tag appears only in the app shell. `AppNavigationPlacementTest`, `nav_active_theme_styles`.
- Estimate details (extended mode only): driver: thresholds and page-end handling; uncertainty: medium, emulation does not reproduce iOS bounce.
- Risks or open questions:
  - Impact: flicker (see Key Risks).
  - Early warning / validation: overscroll and jitter cases in the browser test.
  - Mitigation: threshold and clamping; tune values once the user tries it on the iPhone.
- Canonical components/API contracts touched: app-shell tab bar styles, `layout.php` script list, `account_menu.js` open state (read only).

## Stage 2 - Dependents follow the visible bar

**Estimate: 0.10**

- Goal: the post-send reply box and the private message composer sit at the screen bottom while the bar is hidden and move up with it when it returns.
- Dependencies: Stage 1.
- Expected changes: a `--app-tabbar-offset` value that equals `--app-tabbar-height` when shown and the safe-area inset when hidden; the reading reply box (`site.css`) and the private message composer (`private_message_conversation.css`) use it. `--app-tabbar-height` keeps sizing the bar and the `.main` padding. The account menu popovers are unchanged because the bar never hides while the menu is open.
- Verification approach: browser test extended to measure each dependent's bottom edge with the bar shown and hidden; `iphone_reply_submit`, `reply_viewport`, `private_message_availability`; screenshots at 390px.
- Estimate details (extended mode only): driver: two stylesheets, one shared value; uncertainty: low.
- Risks or open questions:
  - Impact: a dependent left mid-screen.
  - Early warning / validation: bottom-edge measurements.
  - Mitigation: dependents transition with the bar so they never overlap it mid-slide.
- Canonical components/API contracts touched: `--app-tabbar-height` consumers.

## Stage 3 - Themes, acceptance and closeout

**Estimate: 0.13**

- Goal: confirm all permitted themes and close the cycle.
- Dependencies: Stages 1–2.
- Expected changes: theme fixes only where screenshots show problems; `ui_copy_audit_checklist.md` entry (no copy change); no navigation destination changes, so `theme-nav-icons.css` is untouched (recorded); Step 4 closeout, ledger, `ui_redesign` checklist note.
- Verification approach: 13 themes plus Auto at 390px with the bar shown and hidden; desktop and Chouse/MIT Rap Club page HTML compared with `main`; one full suite with failures compared against the known pre-existing list. Real-iPhone check listed as pending for the user.
- Estimate details (extended mode only): driver: theme count and the suite run; uncertainty: low.
- Risks or open questions:
  - Impact: pre-existing failures obscure results.
  - Early warning / validation: compare with `main` results.
  - Mitigation: record, don't chase.
- Canonical components/API contracts touched: none.

## Execution and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 3 handoff | 2026-10-28 | 0.45 (reaffirmed) | v1 / R1 / P2, baseline `7ef735dd2` | Claude, single exposed rater; also inspected the tab bar media query, the two dependent stylesheets and the script loading in `layout.php` | Keeping `.main` padding fixed and the menu-open rule remove the popover work; the iOS uncertainty remains |

Stage estimates are separate annotations and are not summed. Earlier snapshots: [Step 2](./ui_tab_bar_scroll_away_step2_feature_description.md#approval-and-study-checkpoint).

*— Claude · 2026-10-28 UTC · Model: claude-opus-5-5*
