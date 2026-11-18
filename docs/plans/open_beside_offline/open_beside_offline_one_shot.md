# Open beside offline — one-shot

Cycle: `v3/open_beside_offline`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](open_beside_offline_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/open_beside_offline`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/open_beside_offline.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.12** (retrospective 0.13)

Status: technically complete on `feat/open_beside_offline`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Original request, 2026-11-18 (maintainer, in the Claude session that built [open beside](../open_beside/open_beside_step4_implementation_summary.md)): "This feature is visible in offline mode but needs to be ported fully?" Asked what he saw when he tried it: "It went to a new page with the item."

The maintainer raised it and confirmed the symptom after being told a one-shot port was proposed; he did not say "go ahead" in those words. Treated as authorized through to a pushed branch and an open PR, which he reviews by merging or not.

Feedback item: none matched (searched `docs/feedback/items/` for "beside" and "offline"). Created [PF-20261118-F07](../../feedback/items/PF-20261118-F07.txt) in the completion commit.

## What happens today (inspected and reproduced in Chrome)

The offline reader is drawn inside the normal layout, so the ruler, the Beside button and `open_beside.js` are all present offline.

- A thread title on the offline board has its own click handler, which loads the thread as the whole page. The pane leaves alone any link another script has handled, so with Beside on the click still replaces the board. This is the reported symptom.
- Other links already open in the pane offline: the worker answers the pane's request with the offline reader, which draws the thread or tag there with its header hidden. Three things are wrong in that pane: the bar says "Offline Reader", because the reader sets the page's title after the pane has read it; the "OFFLINE MODE" bar is drawn a second time inside the pane, over the content; and a page with no offline copy (Recent, gallery, Prompts, users) shows the browser's own error page under an empty bar.

## Outcome

Application change. Offline, with Beside on and room:

1. A thread title clicked on the offline board, or in a saved tag's list, opens in the pane and the board stays.
2. The pane's bar shows the thread's or tag's title.
3. The offline mode bar is not repeated inside the pane.
4. A page with no offline copy shows a plain message in the pane, with Close still working.
5. A reply written in the pane while offline is kept in the Outbox as it is from a full page.

Online behaviour does not change. Excluded: giving more pages an offline copy; refreshing the main page after a post in the pane.

## Approach and risks

- `open_beside.js` exposes `forumOpenBeside.take(event)`: the same link rules as its own handler, for scripts that handle a link themselves. The offline reader calls it before its own handling of a thread title.
- The bar follows the pane's `<title>` as it changes, not only at load.
- `.offline-mode-bar` joins the elements a framed page hides.
- A pane whose page cannot be read after it loads shows the message. A same-site page can always be read, so only a failed load is unreadable.
- Risk: replying from the pane offline. The Outbox scripts may assume a top-level page. Checked first; if the fix is deep, stop and report with items 1 to 4 done.
- Risk: Firefox. Playwright's offline switch does not take the worker offline in Firefox here (the pages still came from the server), so offline evidence is Chrome only. Said plainly in the summary.

## Reuse

- `public/assets/open_beside.js`: extended (`take`, title following, the message).
- `public/assets/offline_reader.js`: `appendThreadCards`'s click handler extended by one call; nothing else.
- `templates/layout.php`: one selector added to the framed-page style.
- `public/assets/open-beside.css`: the message's style.
- Test: new `tests/browser/open_beside_offline.mjs` on the harness of `tests/browser/offline_compose.mjs`.

Concepts touched: [open beside](../../concepts/open_beside.md) (one paragraph on offline added).

## Completion and verification plan

- Normal entry: offline on a desktop with Beside on, open the board and click a thread title.
- `node tests/browser/open_beside_offline.mjs` (new), covering items 1 to 5, and with Beside off the click loads the thread as before.
- `node tests/browser/open_beside.mjs`, `offline_compose.mjs`, `offline_board_toolbar.cjs`: unchanged behaviour.
- Smoke set plus `OfflineNavigationWorkerTest`. Screenshot of the offline pane checked by eye.
- Recovery: none needed; Close and the Beside toggle work offline.

Starting revision and branch: `df0ac8c5` on `main`; branch `feat/open_beside_offline`. Correction: PR #335 (the toggle's on state) was already merged and is in this base.

## Planning commit

`e4fa005c` (confirmed with Git).

## Study history

- 2026-11-18T15:06Z intake: projection **0.12**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2). Not pristine: given after reading the offline reader and worker and reproducing the behaviour.
- 2026-11-18T15:16Z execution entry after the planning commit: 0.12 reaffirmed.
- 2026-11-18T15:16Z closeout: retrospective **0.13**. Scope v1 delivered; three stale worker tests fixed on the way. Maintainer fit and acceptance pending.

## Execution history

- 2026-11-18T15:06Z: reproduced in Chrome with a scratch script; record written.
- 2026-11-18T15:16Z: implemented. `forumOpenBeside.take(event)` in `open_beside.js`, called first by the offline reader's thread titles; the bar follows the pane's title; `.offline-mode-bar` hidden in a framed page; a failed load shows a message in the pane.
- 2026-11-18T15:16Z: the reply risk did not appear. A reply typed in the pane offline is kept as "not sent", listed above the pane's composer and queued in the Outbox, with no change to the Outbox scripts.
- 2026-11-18T15:16Z: found on the way and fixed: the offline reader's thread titles swallowed Ctrl-click and middle-click (they loaded the thread in the same tab). Those clicks are now left to the browser.
- 2026-11-18T15:16Z: fixed three tests failing on the base commit, confirmed in a clean worktree: `OfflineNavigationWorkerTest` built navigation events without `waitUntil`, which the worker has called since the fast-navigation work, and one test passed a request where the worker now takes the event. The tests' fake events were brought up to date; the worker is unchanged.

## Outcome

Delivered, offline with Beside on and room: a thread title on the offline board or a saved tag's list opens in the pane and the page stays; the bar shows the thread's title; the offline mode bar appears once, on the main page; a page with no offline copy shows "This page could not be loaded. If you are offline, it has no offline copy."; a reply written in the pane goes to the Outbox. With Beside off, a title loads the thread as before.

Limitations: the message appears for any pane page that fails to load, online too. Pages without an offline copy (Recent, gallery, Prompts, users) still have none.

Not done: `templates/pages/offline_reader.php` reads `$tagLinksEnabled`, which its controller does not pass; PHP logs a warning on each render. Seen in test output, not changed here.

## Verification

- `node tests/browser/open_beside_offline.mjs <dir>` (new, Chrome): pass. Beside off loads the thread as the page; Beside on opens it in the pane with the board still drawn; framed, no header, no second offline bar, bar title, no sideways scrolling; Ctrl-click left alone; the offline reply kept and queued; the no-copy message, announced, and cleared by the next page that loads; Close; a tagged thread from a saved tag page.
- `node tests/browser/open_beside.mjs` in Chrome and `BROWSER=firefox` (online behaviour): pass.
- `node tests/browser/offline_compose.mjs`, `offline_board_toolbar.cjs`: pass.
- `./v3 test --smoke OfflineNavigationWorkerTest PresentationProfileMatrixTest`: 497 run, 497 passed.
- Screenshot of the offline pane at 2048px checked by eye.
- Not verified: offline in Firefox. Playwright's offline switch did not take the worker offline in Firefox here, so there is no Firefox offline evidence. Also not verified: phones, production, and the full suite (the maintainer runs it).

## Final review

Diff limited to `open_beside.js`, one handler in `offline_reader.js`, one selector in `layout.php`, the message's style, the new browser test, the three worker tests, the concept paragraph and this cycle's documents.

*Claude (claude-fable-5-2), 2026-11-18*
