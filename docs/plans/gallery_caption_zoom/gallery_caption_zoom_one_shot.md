Cycle: `v3/gallery_caption_zoom`; Mode: one-shot; Record: this file; Index: [plans index](../README.md)
Debug mode: enabled (project marker `.railspike_debug_on`); log: [gallery_caption_zoom_debug_log.md](gallery_caption_zoom_debug_log.md)
Effort study: `railspike-effort` / `v3/gallery_caption_zoom` — [host ledger](../effort_estimation_trial.md#cycle-v3gallery_caption_zoom); standing enrollment under protocol P2
Extended estimates: enabled (no real alternatives or work units; no unit estimates)
**Cycle estimate: 0.05**
Status: planned; next permitted action: implement

## Original request

2026-11-14, after PR #249 (gallery_compact_captions) was merged, with a phone screenshot of `/gallery/` where caption text is large next to small tiles: "can you please add another PR that makes the font zoom at the same time".

## Authorization

That message asks for a separate PR; read as the go-ahead for a bounded one-shot cycle with a PR. Merge and deploy not covered.

## Outcome and work type

Application change, follow-up to `v3/gallery_zoom` and `v3/gallery_compact_captions` (PF-20261114-F02). Normal entry: `/gallery/` with Smaller / Larger or a phone pinch.

Outcome: gallery caption text scales with `--gallery-zoom`, clamped to 0.75x–1.25x (below 0.7 captions are already hidden). Phones get a smaller base caption size (0.8rem) to match their smaller rows. Word97 and Midnight caption/meta sizes scale the same way.

Excluded: zoom range, steps, caption content.

## Approach and risks

One custom property, `--gallery-text-scale: clamp(0.75, var(--gallery-zoom, 1), 1.25)`, on `.gallery-grid`; caption `font-size` multiplies by it, caption padding moves to `em` so it follows. Theme caption rules multiply their existing rem sizes by it. Pure CSS; no script or before-paint change (the zoom variable is already restored before paint).

Risk: text too small on phones at 0.8x zoom (about 10px); acceptable for a dense image view and reversible by one constant.

## Reuse decisions

- The existing `--gallery-zoom` variable and `.gallery-grid` / `.gallery-tile__caption` rules in `public/assets/site.css`, and the theme caption rules in `theme-word97.css` / `theme-midnight.css`. No new component.

## Concepts touched

None.

## Verification and recovery plan

- Browser check (static harness with the real CSS and script) at 390px in Gloss Dark, Word97 and Midnight: caption font size at zoom 0.8, 1, 1.25, 2.
- Smoke set plus `GalleryPageTest`; full suite not run.

## Starting revisions and branch

Host `main` at `0443804`; branch `feat/gallery_caption_zoom`.

## Planning commit

Pending.

## Study history

- 2026-11-15T04:00Z intake: whole-cycle projection **0.05**, scope v1, R1, P2, single exposed rater Claude (claude-opus-5-5). Exposure: rated after working on the gallery zoom code; no pristine intake.
- Execution entry: 0.05 reaffirmed.

## Dated execution history

- 2026-11-14: chronology disclosure: the CSS edits were first written before this plan was committed; they were stashed, this plan committed, then restored. No implementation was committed before the plan.

_Claude (claude-opus-5-5), 2026-11-15_
