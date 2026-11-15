Cycle: `v3/gallery_zoom_buttons_in_view`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); log: [gallery_zoom_buttons_in_view_debug_log.md](gallery_zoom_buttons_in_view_debug_log.md)
Effort study: `railspike-effort` / `v3/gallery_zoom_buttons_in_view` — [host ledger](../effort_estimation_trial.md#cycle-v3gallery_zoom_buttons_in_view); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.04**
Status: planned; next permitted action: implement

## Original request

2026-11-14, in chat: "Also have an issue when I zoom with the buttons at the top, the buttons scroll out of view. Can you please make a separate PR for this?"

## Authorization

Explicit request for a separate PR; read as the go-ahead for a bounded one-shot cycle with a PR. Merge and deploy not covered.

## Outcome and work type

Application bug fix, follow-up to `v3/gallery_zoom` (PF-20261114-F02). Normal entry: `/gallery/` Smaller / Larger buttons.

Cause: a button click zooms around the middle of the screen (`zoomAround(..., innerHeight / 2)`), so the page scrolls to keep the grid's middle in place and the buttons above the grid move off screen.

Outcome: a Smaller / Larger click keeps the clicked button where it is on screen, so repeated taps work without scrolling back. Pinch keeps zooming around the fingers (unchanged).

## Approach and risks

In the click handler, record the button's `getBoundingClientRect().top`, apply the zoom, then `scrollBy` the difference. Above the grid nothing moves, so this normally scrolls by 0. Risk: none beyond the single handler.

## Reuse decisions

- `public/assets/gallery_zoom.js` `apply()` / `save()`; pinch path untouched. `table_zoom.js` not changed (not reported; tables have their own layout).

## Concepts touched

None.

## Verification and recovery plan

- Browser harness at 390px: scroll so the buttons are mid-screen, tap Smaller and Larger several times; button top stays put.
- Smoke set plus `GalleryPageTest`; full suite not run.

## Starting revisions and branch

Host `main` at `0443804`; branch `fix/gallery_zoom_buttons_in_view`.

## Planning commit

Pending.

## Study history

- 2026-11-15T04:10Z intake: whole-cycle projection **0.04**, scope v1, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after working on `gallery_zoom.js`; no pristine intake.
- Execution entry: 0.04 reaffirmed.

## Dated execution history

_Claude (claude-opus-5-5), 2026-11-15_
