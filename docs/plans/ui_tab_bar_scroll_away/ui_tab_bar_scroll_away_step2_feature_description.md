> **Feature plan:** [Step 1](./ui_tab_bar_scroll_away_step1_solution_assessment.md) · [Step 2](./ui_tab_bar_scroll_away_step2_feature_description.md) · [Step 3](./ui_tab_bar_scroll_away_step3_development_plan.md) · [Step 4](./ui_tab_bar_scroll_away_step4_implementation_summary.md)

Debug mode: enabled
Effort study: `railspike-effort` / `v3/ui_tab_bar_scroll_away` ([ledger](../effort_estimation_trial.md#cycle-v3ui_tab_bar_scroll_away)); standing enrollment P2
Extended estimates: enabled

**Cycle estimate: 0.45**

Step 1 approved 2026-10-27: Option A, hide while scrolling down and return on scroll up.

## Problem

On phones the ZenMemes tab bar is always on screen: it covers the bottom of the page, makes Page Down skip the covered part, and cannot be put away.

## User stories

- As a reader on a phone, I want the tab bar to get out of the way while I scroll down so that I can read the whole screen.
- As a reader, I want the bar back as soon as I scroll up, or when I reach the top or end of the page, so that navigation is never far.
- As a keyboard user, I want the bar to appear when I tab into it so that I never focus something I can't see.
- As a user who prefers reduced motion, I want the bar to hide and show without sliding.

## Core requirements

- On ZenMemes phone layouts the tab bar hides after a deliberate scroll down and reappears on scroll up, at the top or bottom of the page, and when focus enters it; small scroll jitter and iOS bounce at the page ends do not toggle it.
- It stays visible while its account menu (or the theme and palette popovers inside it) is open.
- Parts placed above the bar follow its visible height: the account menu popovers, the post-send reply box, and the private message composer. Nothing is left floating over a gap where the bar was.
- The slide animates only `transform` and is instant under `prefers-reduced-motion`; without JavaScript the bar behaves exactly as today.
- Desktop layouts and other sites are unchanged.

## Delivery scope

- Work type: application change.
- Includes the app-shell phone styles, a small script for the scroll behavior (no new third-party code), the styles of the three dependent elements, tests, the copy record (no copy change expected), and this cycle's plans and ledger bookkeeping.
- Excludes the reply bar removal (handled in `ui_thread_reply_dock`), making the private message composer non-sticky, and any desktop change.

## Completion boundary

- Normal entry: any ZenMemes page on a phone, as guest or member.
- Outcome: scrolling down hides the bar, scrolling up or reaching a page end brings it back, and every destination stays one flick away.
- Recovery: without JavaScript, or if the script fails, the bar stays visible as today; reloading always starts with it shown.
- Release: verified on phone emulation in all 13 permitted themes plus Auto; existing navigation, reply and private message browser tests pass; desktop and other sites unchanged.

## Known user or environment

The user reads on an iPhone in Safari. Ordinary entry: scrolling a thread or the board. Failure to handle: the bar flickering during momentum or bounce scrolling, or not returning. Next action unaided: scroll up slightly or to the top. Evidence: emulated touch scrolling in Chromium covers the logic only; the iPhone feel (bounce, Safari's toolbar resizing) needs the user's own check, recorded as pending until then.

## Risks

- **Flicker on iOS bounce and toolbar resizing.** Impact: the bar jumps in and out. Earliest validation: a threshold-based scroll test with simulated overscroll in Stage 1. Mitigation: ignore scroll past the page ends and small deltas.
- **Dependents left over a gap.** Impact: account menu or composers float mid-screen. Validation: screenshots with each dependent while the bar is hidden. Mitigation: one shared visible-height value that all three use.
- **Hidden bar traps focus.** Impact: keyboard focus on an invisible link. Mitigation: show on focus; test with Tab.

## Shared component inventory

- App-shell tab bar styles in `site.css` (`--app-tabbar-height`): **extend** with a hidden state; the height value becomes the bar's visible height.
- `account_menu.js`: **reuse**; its open state keeps the bar shown.
- Post-send reply box (`reply_viewport.js` reading state) and private message composer: **reuse**; they already read `--app-tabbar-height`.
- Scroll behavior script: **new**; no existing component watches scroll direction.

## User flow

1. A reader opens a thread on a phone; the bar is visible.
2. They scroll down; the bar slides away and the whole screen shows content.
3. They scroll up a little; the bar slides back.
4. They open You; the bar stays while the menu is open.

## Success criteria

- After a scroll down of more than a small threshold, the bar is off screen; after a scroll up of more than the threshold, at either page end, or on focus, it is on screen.
- No dependent element sits above an empty gap while the bar is hidden.
- Reduced motion: no slide; no JavaScript: always visible.
- Desktop and other sites' HTML and layout unchanged; existing navigation tests pass.

*— Claude · 2026-10-27 UTC · Model: claude-opus-5-5*

## Approval and study checkpoint

| Checkpoint | UTC | Score | Scope / reference / protocol | Rater and exposure | Reason |
| --- | --- | --- | --- | --- | --- |
| Step 2 handoff | 2026-10-27 | 0.45 (reaffirmed) | v1 / R1 / P2, baseline `7ef735dd2` | Claude, single exposed rater; additionally inspected the account menu popover offsets and the private message composer | Requirements match Option A; no scope change |
| Step 2 approved | 2026-10-28 | 0.45 | v1 / R1 / P2 | Same | "Approved Step 2"; no scope change |

Earlier snapshots: [Step 1](./ui_tab_bar_scroll_away_step1_solution_assessment.md#effort-study).
