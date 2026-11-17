Cycle: `v3/gallery_zoom`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`)
Effort study: `railspike-effort` / `v3/gallery_zoom` — [host ledger](../effort_estimation_trial.md#cycle-v3gallery_zoom); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.12**
Status: implemented and verified locally; next permitted action: push and PR (merge and deploy need the maintainer)

## Original request

Thread https://zenmemes.com/threads/thread-20261115001728-044717db (feedback item PF-20261114-F02), posted by the maintainer: "Gallery needs zoom please".

## Authorization

In a Claude session on 2026-11-14 the maintainer pasted the thread link and wrote "Please pr Claude". Read under `docs/agents/thread_to_pr.md` as the go-ahead for a one-shot cycle on the bounded outcome below, pushing the branch, opening a PR and replying in the thread. Merging and deploying are not covered.

## Outcome and work type

Application change. Normal entry: `/gallery/`.

Outcome: the Gallery gets Smaller and Larger buttons above the tiles; on phones a two-finger pinch on the tiles does the same, live, without zooming the page. In the justified-rows themes the row height scales (0.5x to 2x); in Word97 and Midnight, whose gallery is a figure table, the column count changes instead (8 down to 2 on desktop, 4 down to 1 on phones). The size is saved in this browser, restored before first paint, and listed on Tools → Preferences as "Gallery image size" with a Reset (Reset all clears it too).

Excluded: a full-screen image viewer / lightbox (opening a single image larger), and bigger preview files for large zoom levels (tiles still use the 480px `tile` thumbnail, so the cap stays at 2x).

## Approach and risks

"Zoom" is read the way the site already uses it for tables (`ZoomTable`, `table_zoom.js`): Smaller / Larger buttons plus pinch, remembered per browser. The other reading, tap an image to see it full size, is a different feature; tiles already open their post, and it is listed as a follow-up.

Considered and set aside: a slider (no precedent on the site; the icon buttons are the shared control) and CSS `zoom`/transform on the grid (blurs and breaks the justified layout).

Risks: older browsers without CSS `round()` keep the themes' fixed 4/2 columns in Word97 and Midnight (fallback declaration first); the 480px tile previews soften slightly at 2x on large screens.

## Reuse decisions

- Buttons: `ZoomButtons::group` (same icons and `.zoom-buttons` styles as tables, Query viewer and Forte); added an optional group label so the gallery's reads "Image size" instead of "Text size".
- Behaviour: `public/assets/gallery_zoom.js` mirrors `table_zoom.js` (step 1.25 / 0.8, pinch via touch events with `touch-action: pan-x pan-y` on phones, iOS gesture events blocked only during a pinch, save on release).
- Before-paint restore: the existing localStorage block in `templates/layout.php`, next to the table zoom restore.
- Layout: the existing `--gallery-row` variable in `site.css`; Word97 and Midnight's figure tables get `--gallery-columns`.
- Preferences: the existing row/reset pattern in `templates/pages/preferences.php` and `public/assets/preferences.js`.

## Concepts touched

None.

## Verification and recovery plan

- `GalleryPageTest`: buttons and script present with tiles, absent with none; before-paint restore present.
- `ZoomButtonsTest`: group label.
- `PreferencesPageTest`, `tests/browser/preferences.mjs`: new row shows default/custom, resets, and Reset all clears it.
- Browser check on a local fixture with 12 images: Gloss, Light, Word97 and Midnight at 390px and 1200px; Larger twice, reload (size kept), Smaller to the minimum, pinch on phones (tiles grow, page scale stays 1, value saved).
- Smoke set plus changed tests only; full suite not run.

## Starting revisions and branch

Host `main` at `26dd2fbff07b1e99b30d2429cd9d322a02318683`; branch `feat/gallery_zoom`.

## Planning commit

None separate; one completion commit (pending reference).

## Study history

- 2026-11-15T00:25Z intake: whole-cycle projection **0.12**, scope v1 as above, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after reading `gallery.php`, `ZoomTable`, `ZoomButtons` and `table_zoom.js`; no pristine intake.
- Execution entry: 0.12 reaffirmed.

## Dated execution history

- 2026-11-14: no existing item for gallery zoom; created PF-20261114-F02. No open branch for it on origin.
- 2026-11-14: implemented as planned. Added the Preferences row after finding the Preferences page (PF-20261114-F01) lists every setting the browser keeps.
- 2026-11-14: verification: `./v3 test --smoke GalleryPageTest ZoomButtonsTest PreferencesPageTest PostMarkdownRendererTest` 474/475 (the one failure, `LocalAppSmokeTest::testThreadCardWrapsMediaEmbedPreviewInDiv`, also fails on `main`); `tests/browser/preferences.mjs` ok; the theme/width/pinch browser check above passed with no page errors.

## Study closeout

Intake 0.12; execution entry reaffirmed; **retrospective 0.12**. Single exposed rater Claude (claude-opus-5-5), R1/P2. Maintainer acceptance pending; no accepted-count increment.

## Outcome

Gallery zoom built and verified locally; awaiting review, merge and deployment. Follow-ups: tap-to-view-full-size image viewer; larger previews if 2x looks soft.
