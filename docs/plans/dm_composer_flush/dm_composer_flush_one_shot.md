# DM composer flush on phones

Cycle: `v3/dm_composer_flush`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/dm_composer_flush`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates disabled (one unit of work).

**Cycle estimate: 0.10**

Status: planning committed; next permitted action: implement and verify.

## Original request

Maintainer, 2026-11-14, in the DM handoff's open items: "Since #232, in a long conversation the sticky composer may shift about 15px at the end of scroll (window padding). Pin it flush if wanted."

## Authorization

Maintainer, 2026-11-14, in session, after Claude measured the shift and offered three choices: chose "Pin flush on phone only". Claude had recommended that option: on phones the conversation card runs to the screen edge, so the composer never moves; desktop keeps the framed window with its visible bottom edge. The original handoff asked to follow `AGENTS.md`, which makes this a Railspike cycle, one-shot because the outcome is chosen and bounded. Authorizes this change, a branch push and a PR. Does not authorize merging, deploying or changing desktop layout.

## Outcome and work type

Application change (CSS). In a phone-width conversation (no tab bar, from #222), the sticky composer stays flush with the bottom of the screen (above the safe area) through the end of scrolling. The conversation card has no bottom padding, border or rounding there; the composer's own padding gives it room.

Excluded: desktop widths, where the composer rides up 41px at the end as the window's bottom edge and page margin come into view. That is the framed window from #232 working as intended. Also excluded: the person-page composer and the Messages list.

## Approach and risks

- One rule in the existing phone, no-tab-bar block of `private_message_conversation.css`, under `[data-shell="app"][data-tabbar-page="none"]`: remove the card's bottom padding, bottom border and bottom corner radius.
- Risk: a theme sets card padding, borders or radius with a more specific selector, or draws something at the card's bottom (Word 97 frames, Sticker shadows). Mitigation: measure the gap at the end of scroll and review end-of-scroll screenshots in every theme at 390px.
- Risk: a short conversation. #222 makes the card fill the screen; without bottom padding the composer should still sit at the bottom edge. Checked by the existing `checkConversationTabBar`.

## Reuse decisions

- Selector and media block: the existing phone `[data-tabbar-page="none"]` rules in `public/assets/private_message_conversation.css` (from #222). No new marker or variable.
- Bottom clearance: the existing `--app-tabbar-offset` (safe area on these pages) for the composer and `.main`'s `padding-bottom` from `site.css`; unchanged.
- Card: the `.card` from `site.css`, adjusted only on this page at phone width. No new component.

## Completion conditions

- Normal entry: on a phone, Messages → a conversation with enough messages to scroll.
- End-to-end: scrolling from top to bottom, the composer's bottom stays at the screen's bottom edge (gap 0 above the safe area) in every theme; a short conversation still puts the composer at the bottom edge; desktop is unchanged.
- Release: PR merged and deployed by the maintainer; iPhone Safari check by the maintainer.

## Concepts touched

None changed. [Navigation slots](../../concepts/navigation_slots.md) used unchanged (the no-tab-bar conversation page).

## Verification and recovery plan

- Probe (scratch, outside the repo): seed a 44-message conversation, measure the gap between the composer's bottom and the viewport bottom at several scroll positions, at 390px and 1280px, in every permitted theme; screenshot the end of scroll at 390px.
- `tests/browser/private_message_conversation_tabbar.mjs` assertion added: at the end of a long conversation the composer's bottom is at the screen's bottom edge. Run in the messaging browser suite.
- `tests/browser/private_message_redesign.mjs all` at 390 and 1280px.
- `./v3 test --smoke` plus `PrivateMessagePageControllerTest`. Full suite not run, per `AGENTS.md`.
- Baseline failures seen on clean `origin/main` are fixed as part of the work where they block these checks, per the maintainer's standing preference, or recorded with a reason if out of reach.

Starting revisions and branch: `fix/dm_composer_flush` from `origin/main` `6bdc52d7`.

Planning commit: pending.

## Dated execution history

- 2026-11-14: Measured on `b2c8bf8e` with a scratch probe: gap 0 while scrolling, then 15.2px at the end on phones (card padding 0.9rem plus 1px border) and 40.8px on desktop, the same in Light, Word 97 and Gloss.

## Study history

- Intake, 2026-11-17T05:54Z: 0.10. Recorded after the 2026-11-14 measurement and a reading of the CSS, so not before detailed inspection. Baseline `6bdc52d7`, scope v1. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified.
- Execution entry, 2026-11-17T05:54Z: 0.10 reaffirmed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
