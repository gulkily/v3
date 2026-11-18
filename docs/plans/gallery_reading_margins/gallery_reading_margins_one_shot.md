# Gallery reading margins — one-shot

Cycle: `v3/gallery_reading_margins`; Mode: one-shot; Record/index: this file, [plans index](../README.md)
Debug mode: enabled ([log](gallery_reading_margins_debug_log.md); project `.railspike_debug_on` marker present)
Effort study: `railspike-effort` / `v3/gallery_reading_margins`, standing enrollment under protocol P2, reference R1 ([ledger](../effort_ledger/gallery_reading_margins.md))
Extended estimates: enabled (no real alternatives or work units, so no unit estimates)

**Cycle estimate: 0.04**

Status: planned on `feat/gallery_reading_margins`; next permitted action: implement and verify.

## Request and authorization

Original request, 2026-11-17 (maintainer, [thread](https://zenmemes.com/threads/thread-20261117215425-c1f4cc8b)): "please add margin support to gallery view #meta go straight to pr".

Authorization: the maintainer, 2026-11-18 (Claude session): "Can you please scan Zen memes for anything that's asking for a PR? And if the task seems appropriate for a one-shot, make the PR and reply in the thread." Together with the thread's "go straight to pr", this authorizes a one-shot through an open PR and a thread reply. Exclusions: merge, deployment.

Feedback item: none matched in `docs/feedback/items/` (searched for the thread ID, "gallery" and "margin"). Creating PF-20261118-F02 in the completion commit.

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

Planning commit: pending.

## Dated execution history

- 2026-11-18T07:18Z: plan written after reading `TemplateRenderer`, `reading_margins.md`, the gallery CSS in `site.css` and `theme-midnight.css`, and the smoke test.

## Study history

- Intake, 2026-11-18T07:18Z: **0.04** whole-cycle projection, scope v1, R1/P2. Recorded after inspecting the files above (not a blind intake). Single exposed rater (Claude).
