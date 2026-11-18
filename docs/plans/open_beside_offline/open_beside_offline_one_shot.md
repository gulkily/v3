# Open beside offline — one-shot

Cycle: `v3/open_beside_offline`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](open_beside_offline_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/open_beside_offline`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/open_beside_offline.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.12**

Status: planned; next permitted action: implement and verify, then push and open a PR. Merge and deployment need the maintainer.

## Request and authorization

Original request, 2026-11-18 (maintainer, in the Claude session that built [open beside](../open_beside/open_beside_step4_implementation_summary.md)): "This feature is visible in offline mode but needs to be ported fully?" Asked what he saw when he tried it: "It went to a new page with the item."

The maintainer raised it and confirmed the symptom after being told a one-shot port was proposed; he did not say "go ahead" in those words. Treated as authorized through to a pushed branch and an open PR, which he reviews by merging or not.

Feedback item: none matched (searched `docs/feedback/items/` for "beside" and "offline"). Created [PF-20261118-F05](../../feedback/items/PF-20261118-F05.txt) in the completion commit.

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

Starting revision and branch: `df0ac8c5` on `main`; branch `feat/open_beside_offline`. PR #335 (the toggle's on state) is open and not in this base; the two touch different rules.

## Planning commit

Pending.

## Study history

- 2026-11-18T15:06Z intake: projection **0.12**, scope v1, R1, P2, single exposed rater Claude (claude-fable-5-2). Not pristine: given after reading the offline reader and worker and reproducing the behaviour.

## Execution history

- 2026-11-18T15:06Z: reproduced in Chrome with a scratch script; record written.
