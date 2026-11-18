# Gallery reading margins — one-shot

Cycle: `v3/gallery_reading_margins`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](gallery_reading_margins_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/gallery_reading_margins`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/gallery_reading_margins.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.04**

Status: technically complete on `feat/gallery_reading_margins`; pushed for review, not merged or deployed. Next action: maintainer review, merge and deployment.

## Request and authorization

Original request, 2026-11-17 (maintainer, [thread](https://zenmemes.com/threads/thread-20261117215425-c1f4cc8b)): "please add margin support to gallery view #meta go straight to pr".

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." Together with the thread's "go straight to pr", this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment.

Feedback item: none matched in `docs/feedback/items/` (searched for the thread ID, "gallery" and "margin"). Created [PF-20261118-F02](../../feedback/items/PF-20261118-F02.txt) in the completion commit.

## Outcome and work type

Application change. With reading margins on (`FORUM_READING_MARGINS_ENABLED`), `/gallery/` shows the ruler like every other layout page, and a chosen column narrows the gallery; the tiles refill the column.

## Approach and risks

`TemplateRenderer::READING_MARGINS_EXCLUDED_PAGE_TEMPLATES` drops `gallery.php`; `redirect.php` stays excluded. The exclusion's reason ("justified rows are laid out for the normal width") no longer holds: `.gallery-grid` is a wrapping flex row whose tiles take a basis of ratio times row height with `min-width: 0`, so rows refill any width. Midnight and Word97 lay the gallery out as a column grid of `minmax(0, 1fr)` cells, which also follow the container.

Risk: in the narrowest column (20rem) a wide tile's basis (a 2.2 ratio is 26.4rem) is larger than the row. A lone flex item shrinks to the line (`flex-shrink: 1`, `min-width: 0`), so it should fit; checked in the browser before calling this done. Midnight/Word97 keep 4 columns at narrow columns on a wide window (their 2-column rule is a viewport media query); accepted if the cells stay legible, noted otherwise.

## Reuse decisions

Reuses the existing reading-margins ruler, stylesheet and saved choice (`reading_margins.php`, `reading-margins.css`, `reading_margins.js`); no new component. Gallery CSS unchanged unless the browser check shows overflow.

## Concepts touched

None in the concept inventory.

## Completion conditions and verification plan

- Normal entry: with the flag on, open `/gallery/` on a desktop window; the ruler shows; choosing stops narrows the column and the tiles reflow without sideways scroll.
- `LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled` updated to expect the ruler on `/gallery/`.
- `./v3 test --smoke LocalAppSmokeTest GalleryPageTest`. Full suite not run (maintainer runs it).
- Scratch browser check of a gallery with wide and tall images at the narrowest column in the default theme, Midnight and Word97: no horizontal overflow.
- Docs: `docs/plans/reading_margins.md` gets a dated follow-up.

Starting revision and branch: `31e16b066774dbdf8f4d3709ae4ad021bfff9516` on `main`; branch `feat/gallery_reading_margins`.

Planning commit: `2231b4fc` (2026-11-18T07:19Z), containing only this record and its index link.

## Dated execution history

- 2026-11-18T07:18Z: plan written after reading `TemplateRenderer`, `reading_margins.md`, the gallery CSS in `site.css` and `theme-midnight.css`, and the smoke test.
- 2026-11-18T07:20Z: planning commit `2231b4fc` confirmed. Dropped `gallery.php` from the exclusion; smoke test now expects the ruler on `/gallery/`.
- 2026-11-18T07:25Z: discovery from the browser check. Flex themes never overflowed, but the narrowest column (320px) left one tile per row at the desktop 12rem row height, and Midnight/Word97 kept four 66px cells with captions cut to "Figure 1: A...". Addition within scope: `gallery.php`'s section gets `gallery-page` (a size container), and below 36rem the gallery takes its existing phone layout through `@container` copies of the phone rules (`site.css` row height, caption size and gap; Midnight/Word97 two columns). The default column (712px/812px) is unchanged; 36rem sits below it.

## Study history

- Intake, 2026-11-18T07:18Z: **0.04** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
- Execution entry, 2026-11-18T07:20Z: 0.04 reaffirmed.
- Closeout, 2026-11-18T07:35Z: delivered scope v1 plus the narrow-column layout found in verification. Retrospective **0.05**. Maintainer fit and acceptance pending.

## Outcome

Delivered: the reading-margins ruler on `/gallery/`; a chosen column narrows the gallery, and below 36rem it uses the phone layout (smaller rows; two columns in Midnight and Word97). Not delivered: nothing in scope left.

## Verification

- `./v3 test --smoke GalleryPageTest LocalAppSmokeTest::testReadingMarginsAreHiddenByDefaultAndShownOnLayoutPagesWhenFlagEnabled`: 481 of 481.
- Scratch browser check (headless Chrome, 1280px; tiles of ratios 0.6 to 2.2 injected into `/gallery/` in gallery.php's markup because the fixture has no images) in Gloss, Light, Midnight, Word97 and Bubblegum, at the normal column and the narrowest choice: ruler present, no sideways scroll, every tile inside the column, normal-column tile widths unchanged by the container. Screenshots reviewed.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not run: the full suite (maintainer runs it); `tests/browser/reading_margins.mjs` (it has no gallery images either; not extended); the other 12 themes; Safari and real devices; a gallery with real uploaded images.

## Final review

Diff limited to `TemplateRenderer.php` (one list entry), `gallery.php` (one class), `site.css`, `theme-midnight.css` and `theme-word97.css` (`@container` copies of existing phone rules), `LocalAppSmokeTest.php`, `reading_margins.md`, this record and its index line, the feedback item and index, the ledger and debug log. No new component, copy, route, schema or dependency.
