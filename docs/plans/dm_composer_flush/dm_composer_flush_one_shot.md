# DM composer flush on phones

Cycle: `v3/dm_composer_flush`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled (project marker `.railspike_debug_on`)

Effort study: `railspike-effort` / `v3/dm_composer_flush`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment; extended estimates disabled (one unit of work).

**Cycle estimate: 0.10**

Debug log: [dm_composer_flush_debug_log.md](./dm_composer_flush_debug_log.md)

Status: technically complete on `fix/dm_composer_flush`; PR open, awaiting maintainer merge, deploy and iPhone check. One messaging browser step (`checkUnavailable`) still fails intermittently; see Outcome.

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

Planning commit: `f1870922`.

## Dated execution history

- 2026-11-14: Measured on `b2c8bf8e` with a scratch probe: gap 0 while scrolling, then 15.2px at the end on phones (card padding 0.9rem plus 1px border) and 40.8px on desktop, the same in Light, Word 97 and Gloss.

- 2026-11-17: Planning commit `f1870922`.
- 2026-11-17: Added the card rule. Probe in all 20 permitted themes: phone gap 0 (≤0.2px rounding) at the end of scrolling everywhere except Word 97 and Midnight, which still rose 35px.
- 2026-11-17 (discovery, correction within scope): Word 97 and Midnight pad `body` by 2.2rem for their fixed status bar, but `site.css` hides that bar on phones in the app shell ("give way to the tab bar"), leaving dead space under every phone page in those themes. Added a rule beside it that drops the padding where the bar is hidden: `:root body:has(> [data-shell="app"] > .site-status-bar)`. This affects every phone app page in those two themes (removes the empty strip), not only conversations; the tab bar's clearance comes from `.main`'s padding and is unchanged. Probe afterwards: 0 in both.
- 2026-11-17: Browser check: `checkConversationTabBar` now asserts the composer sits at the bottom edge (±1px, was ≤32px) and stays flush at the end of a scrolled conversation; a temporary 1500px spacer makes the three-message fixture scroll.
- 2026-11-17 (scope addition, under the maintainer's standing rule that tests failing on the base commit are fixed as part of the work): the messaging browser suite (`private_message_list_browser.mjs`) failed before reaching `checkConversationTabBar`. Fixed the stale tests it hit, in order:
  - Users-link step: the Users page lists people as thread rows now; the selector `a.account-row[href="/user/bob"]` became `.thread-row__title a[href="/user/bob"]`.
  - History-sync `state()` assertion: earlier sends trip the server's intentional history rate limit (`admit()` → 429), and the client then defers its next run, so the forced `refresh()` made no request and the state stayed `waiting`. The test now reloads under its failing route before the check.
  - `checkChatRecovery` timeout and user-57 "Retry preview": the reader now requests `recipient_keys?…&purpose=verification`, so routes for the exact URL `…recipient_keys?username_token=X` never matched. Route globs in four test files now end in `&*`.
  - `checkUnreadIndicators` line 44: on phones Messages is a tab only once earned by use (`nav_slots.js`); the test saves that choice in `forum_nav_slots` before checking keyboard focus at phone width. Also made two `waitForFunction` predicates null-safe (`checkUnreadIndicators`, `checkSeenWindows`), which raced the navigation they waited for.
  - `FeatureFlagsBehaviorTest::testRenderedOrderIgnoresRegistryInputOrder` and `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` already pass on `6bdc52d7`; nothing to fix.
- 2026-11-17 (not fixed, needs the maintainer): `checkUnavailable` fails intermittently. Opening a conversation with 20 undecryptable messages sends one `history_sync/transfers` lookup per message; the server's history rate limit answers some with 429, and when a lookup's retry is also refused the message shows "History synchronization could not be loaded" (`load-failed`) instead of joining the "Couldn't decrypt 20 messages" group (one run: 19 grouped, message 11 split out). This is product behavior, not a stale test, so it is left for a decision (throttle client lookups, batch them, or change the limit) rather than masked in the test. Steps after it (`checkUnavailableRecovery` onward) were not reached.

## Study history

- Intake, 2026-11-17T05:54Z: 0.10. Recorded after the 2026-11-14 measurement and a reading of the CSS, so not before detailed inspection. Baseline `6bdc52d7`, scope v1. Single exposed rater Claude (claude-opus-5-5); R1 hashes not re-verified.
- Execution entry, 2026-11-17T05:54Z: 0.10 reaffirmed.
- Scope revision, 2026-11-17T06:11Z: 0.25. Reason: baseline browser-test fixes added under the standing rule (five stale steps, two races) and the Word 97/Midnight status-bar padding. Original scope and 0.10 retained.
- Closeout, 2026-11-17T06:11Z: retrospective scope assessment 0.25 for the delivered scope (CSS fix plus test repairs); `checkUnavailable` rate-limit issue reported, not delivered. Maintainer comfortable-fit judgment pending.

## Outcome

Delivered: on phones a conversation's composer stays flush with the bottom edge through the end of scrolling in every theme; Word 97 and Midnight lose the empty strip under phone pages; the messaging browser suite runs through `checkSeenRecovery`, where it previously stopped at the Users-link step. Desktop unchanged (still rides up about 41px as the window's bottom edge comes into view, as chosen).

Limitations and next actions: `checkUnavailable` is intermittent because of the history rate limit (above); later suite steps are unverified. iPhone Safari check pending the maintainer.

## Verification

- Scratch probe (outside the repo), 44-message conversation, gap between composer bottom and viewport bottom at five scroll positions: 390px, 0 at every position in all 20 permitted themes after the change (was 15.2px, 35.1px in Word 97/Midnight); 1280px unchanged (40–85px at the end).
- End-of-scroll screenshots at 390px reviewed in Word 97, Midnight, Light and Sticker: no card bottom edge, composer at the edge.
- `tests/browser/private_message_redesign.mjs all` (390 and 1280px, every theme): passed.
- Messaging browser suite: passes through `checkConversationTabBar` (with the new flush assertions), `checkChatRecovery`, history, unread and seen steps through `checkSeenRecovery`; `checkUnavailable` intermittent as described. Two further runs passed `checkUnreadIndicators` and the steps before it.
- `./v3 test --smoke PrivateMessagePageControllerTest FeatureFlagsBehaviorTest`: 482 run, 482 passed. Full suite not run, per `AGENTS.md`.
- `git diff --check`: clean.

## Final review

Diff: two CSS rules (one per file) reusing the existing phone no-tab-bar and status-bar blocks; no new variables or components. Test changes are selector, route-glob and timing repairs plus the two new flush assertions; none weaken an assertion except the deliberate tightening of the composer check. File scope: the CSS files, seven browser test files, this record, the plans index and the ledger.

## Commit/merge/publication status

Implementation commit: pending at time of writing; pushed with this record. Not merged or deployed.

*— Claude · 2026-11-17 UTC · Model: Claude Opus 5.5*
