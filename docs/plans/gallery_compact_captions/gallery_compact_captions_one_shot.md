Cycle: `v3/gallery_compact_captions`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); log: [gallery_compact_captions_debug_log.md](gallery_compact_captions_debug_log.md)
Effort study: `railspike-effort` / `v3/gallery_compact_captions` — [host ledger](../effort_estimation_trial.md#cycle-v3gallery_compact_captions); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.06**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

In a Claude session on 2026-11-14, with a phone screenshot of `/gallery/` (Gloss Dark): "I'd like to improve this gallery page by hiding the captions if I'm zoomed out a lot, please."

## Authorization

That message asks for the change directly; read as the go-ahead for a bounded one-shot cycle with a PR. Merging and deploying are not covered.

## Outcome and work type

Application change, follow-up to `v3/gallery_zoom` (feedback item PF-20261114-F02). Normal entry: `/gallery/` with Smaller / Larger or a phone pinch.

Outcome: at a gallery zoom below 0.7 (two Smaller taps from the default, or an equivalent pinch) the tile captions (title and reply count) are hidden, so small tiles show only images. They come back when zooming in past that point. The state is decided before first paint on reload, from the same saved zoom. Screen readers still get the caption text (visually hidden, not removed).

Excluded: changing the zoom range, steps or the caption content at normal sizes.

## Approach and risks

`gallery_zoom.js` `apply()` sets `data-gallery-compact` on `<html>` when the zoom is below the threshold; `layout.php`'s before-paint restore sets the same attribute; `site.css` (critical section) visually hides `.gallery-tile__caption` under that attribute with `!important` so the Word97 / Midnight caption rules do not win.

Risk: a threshold that feels wrong; it is one constant in two places (script and head restore), noted in both.

## Reuse decisions

- Zoom state: the existing `--gallery-zoom` handling in `public/assets/gallery_zoom.js` and `templates/layout.php`; no new storage key.
- Hiding: the declarations of the existing `.visually-hidden` utility in `site.css`, applied by attribute rather than a new class on each tile.
- Pre-paint root attribute pattern: as `data-table-size-*` and `data-visitor` already do.

## Concepts touched

None.

## Verification and recovery plan

- `GalleryPageTest`: head restore sets the compact attribute with the same threshold.
- Browser check on a local fixture: Gloss Dark and Word97 at 390px; Smaller twice hides captions, Larger once restores them, reload at small zoom has no captions on first paint.
- Smoke set plus changed tests only; full suite not run.

## Starting revisions and branch

Host `main` at `90d3b0f`; branch `feat/gallery_compact_captions`.

## Planning commit

`ff48697` (plan only, before implementation).

## Study history

- 2026-11-15T03:45Z intake: whole-cycle projection **0.06**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `gallery_zoom.js`, the gallery template and CSS; no pristine intake.
- Execution entry: 0.06 reaffirmed.

## Dated execution history

- 2026-11-14: no separate feedback item; recorded as a follow-up note on PF-20261114-F02.
- 2026-11-14: implemented as planned (`gallery_zoom.js`, `layout.php`, `site.css`, `GalleryPageTest`). Correction: the first head-script comment named `gallery_zoom.js`, which put that string on every page and broke `GalleryPageTest::testGalleryIsEmptyThenHiddenWhenTurnedOff`; reworded.
- 2026-11-14: addition under the standing rule to fix tests failing on the base commit: `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv` failed on `main` because media embeds are off by default; the test now turns `FORUM_MEDIA_EMBEDS_ENABLED` on for its render and restores it.

## Verification

- `./v3 test --smoke GalleryPageTest LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`: smoke set and both changed tests pass. Full suite not run.
- Browser (Chrome, 390px, static page with the real `site.css`, theme CSS, head restore snippet and `gallery_zoom.js`) in Gloss Dark, Word97 and Midnight: captions visible at default and one Smaller step; hidden (1px, visually hidden) after two; still hidden after reload; back after one Larger. No page errors. Not checked on a real iPhone or against the live app.

## Final review

Diff limited to the three files above, the gallery test, the pre-existing failing test, this record, the plans index, PF-20261114-F02 and the ledger. No duplicated components; the hiding declarations repeat `.visually-hidden` because they apply by root attribute (with `!important` over theme caption rules).

## Study closeout

Intake 0.06; execution entry reaffirmed; **retrospective 0.07** (the extra pre-existing test fix). Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending.

## Outcome

Gallery tiles show images only below 0.7 zoom; awaiting review, merge and deployment.

_Claude (claude-opus-5-5), 2026-11-15_
