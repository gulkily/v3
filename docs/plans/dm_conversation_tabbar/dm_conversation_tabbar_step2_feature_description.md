# DM conversation tab bar Step 2 feature description

> **Feature plan:** [Step 1](./dm_conversation_tabbar_step1_solution_assessment.md) · [Step 2](./dm_conversation_tabbar_step2_feature_description.md) · [Step 3](./dm_conversation_tabbar_step3_development_plan.md) · [Step 4](./dm_conversation_tabbar_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/dm_conversation_tabbar`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates enabled.

**Cycle estimate: 0.30**

Approved direction: Step 1 Option A, no tab bar inside a conversation (approved 2026-11-14).

## Problem

On a phone, a conversation always shows the bottom tab bar, because conversations sit at the page bottom where the bar never hides. Together with the composer and Safari's bars it leaves a thin strip for messages.

## User stories

- As a member reading a conversation on my phone, I want the messages to have most of the screen so that I can follow the conversation without constant scrolling.
- As a member in a conversation, I want an obvious way back to my messages so that losing the tab bar never leaves me stuck.
- As a member on a desktop or a wide screen, I want nothing to change so that the layout I know stays the same.

## Core requirements

- On phone widths, a single conversation page shows no tab bar; the composer sits at the bottom edge, clear of the iPhone safe area.
- The header's back arrow is the way out and stays visible; nothing else in the conversation needs the bar.
- Every other page, including the Messages list, keeps the tab bar exactly as today; desktop and wide layouts are unchanged.
- The page looks final on first paint: the bar is absent from the start, never shown then removed.
- Anything else pinned to the bottom on a conversation page (popovers, toasts, the composer) uses the bar-free offset, in every theme.

## Delivery scope

Work type: application change (layout markup or attribute for conversation pages, phone CSS, tests).

## Completion boundary

- Normal entry: Messages → a conversation, on a phone.
- End-to-end: the conversation fills the space above the composer; back arrow returns to Messages, where the bar is back.
- Recovery: no new failure modes; if a theme mis-positions something, it falls back to today's bar-free offset rules already used when the bar is scrolled away.
- Release: ships on merge (no flag), since it only removes chrome on one page type and is easy to revert.

## Known user or environment

The maintainer on iPhone Safari (screenshot from 2026-11-10). Entry: zenmemes.com → Messages → a conversation. Verification: open a conversation and confirm no tab bar, composer at the bottom, back arrow works. Desktop or emulated-phone evidence alone does not satisfy this.

## Risks

- **Hidden dependencies on the bar**: some page parts may assume the bar exists (the account menu lives in it on phones). Validate by listing what the bar holds and confirming none is needed inside a conversation.
- **Theme offsets**: Gloss, Word97, Chicago, Midnight and others position bottom elements with `--app-tabbar-offset`. Validate on each theme with a 375px screenshot.
- **Scroll script conflict**: `tab_bar_scroll.js` toggles `data-tabbar`; it must not bring the bar back on conversation pages.

## Shared component inventory

- `templates/layout.php` shell (`data-shell="app"`) and `TemplateRenderer` `bodyClass`/page data: extend with one page-level marker for conversation pages rather than a second layout.
- `public/assets/site.css` phone tab bar rules and `--app-tabbar-offset`: reuse the existing hidden-bar offset (`[data-tabbar="hidden"]`) instead of new offset variables.
- `public/assets/tab_bar_scroll.js`: leave its behavior intact on other pages; skip it or have it respect the marker on conversation pages.
- `public/assets/private_message_conversation.css`: composer `bottom` already follows `--app-tabbar-offset`; no change expected.
- Theme files using `--app-tabbar-offset` (Gloss and others): verified, changed only if a theme breaks.

## User flow

1. Member taps a conversation in Messages.
2. The conversation opens with no tab bar; messages fill the screen above the composer.
3. Member taps the back arrow and returns to Messages, where the tab bar shows again.

## Success criteria

- At 375×667, a conversation page has no visible tab bar and the composer's bottom edge sits at the bottom of the screen (plus safe area).
- The space for messages grows by the bar's height (about 56px) compared with today.
- The Messages list, board and other pages show the tab bar as before, at phone width.
- Each theme renders the conversation without overlapping or floating bottom elements.

Concepts touched: [Navigation slots](../../concepts/navigation_slots.md) (unchanged; note that conversation pages show no bar).

Enhancement requests: none.

## Study snapshots

- Step 2 handoff, 2026-11-14 UTC: 0.30 projected total reaffirmed; scope v1 unchanged. Claude single exposed rater; R1 hashes not re-verified this session.

*— Claude · 2026-11-14 UTC · Model: Claude Opus 5.5*
