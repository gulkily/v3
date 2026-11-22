> **Feature plan:** [Step 1](./image_loading_step1_solution_assessment.md) · [Step 2](./image_loading_step2_feature_description.md) · [Step 3](./image_loading_step3_development_plan.md) · [Step 4](./image_loading_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_loading`; host ledger [image_loading.md](../effort_ledger/image_loading.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.90**

Branch `image-loading`, from `origin/main` `22fc9f1` with `a4a4aea` merged in. Planning commits: Steps 1–3 were committed as each was approved, ending with `ef0199e`; the ledger and debug log went into those commits, the same deviation from the planning-commit rule as zen_strip and zen_hero. Base: `./v3 test --smoke ImageContentTest MediaEmbedRendererTest GalleryPageTest BoardListTest ZenStripTest`, 514 run, 514 passed.

## Study snapshots

- 2026-11-22 UTC: **Approved Step 3** by the maintainer ("approved step 3").
- 2026-11-22 UTC, Step 4 entry: **0.90** projected total, reaffirmed from Step 3. Claude, single exposed rater. Scope v1. Stage originals copied from Step 3 (not new ratings): 0.10, 0.20, 0.15, 0.10, 0.30, 0.05.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 1 - Browsers keep served images for a year

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `ImageController`: a served original or copy is sent with `Cache-Control: private, max-age=31536000, immutable` (`ImageController::KEEP`). Every error answer keeps `private, no-store`.
  - New `tests/ImageServingTest.php`, registered in `tests/run.php`. It starts a local server and reads real response headers.
  - `tests/image_content_apache_test.py` expects the new header in place of `no-store`.
- Verification:
  - `./v3 test ImageServingTest`: 1 run, 1 passed. It covers an original, a `tile` copy, three missing addresses, and the image flag off. The full suite was not run.
  - **Not run:** `tests/image_content_apache_test.py`. Apache is not installed in this workspace.
  - **Pending:** the outside request to production after deploy, to see whether the host's own cache line weakens ours.
- Notes:
  - Nothing in the repository says why `no-store` was chosen, and the maintainer doesn't know either.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 2 - Compressed copies on pages; threads link the original

**Original estimate: 0.20**

**Retrospective stage-work assessment: 0.25**

- Changes:
  - `ThumbnailStore`: two display sizes, `wide` (fits 960px) and `large` (fits 1600px), written as WebP. With WebP available they are made even from an original that already fits. `displaySources()` and `displaySrcset()` list the tile and each display size wider than the one before, so an image never offers two copies of the same width.
  - Display sizes are written at WebP quality 90 (`DISPLAY_QUALITY`); thumbnails stay at 80.
  - `ImageController` accepts the new size names. A display copy that came out no smaller than its original is answered with the original.
  - `MediaEmbedRenderer`: a thread image is a compressed copy with `srcset`, wrapped in a link to the stored original (`post-image__original`).
  - `ListThumbnails` carries `display` and `display_srcset` for Stage 3.
- Verification:
  - `./v3 test --smoke ImageServingTest ImageContentTest MediaEmbedRendererTest GalleryPageTest BoardListTest ZenStripTest QuillClientTest`: 534 run, 534 passed. New in `ImageServingTest`: the source list for a 900x600, a 2000x1000 and a 40x40 image; the thread markup; no stored original inside an image tag on a thread, the board list, Recent and the gallery; the copy is WebP, 900x600 and under half the original's bytes; a flat PNG's copy is answered with the PNG. The full suite was not run.
  - Six originals downloaded from zenmemes.com, copies made in a scratch store:

    | Image | Original | Tile | Wide | Large |
    | --- | --- | --- | --- | --- |
    | Painting, 1672x941 PNG | 2,567 KB | 4 KB | 21 KB | 50 KB |
    | "Every commit is a stone", 1080x1080 PNG | 72 KB | 6 KB | 20 KB | 25 KB |
    | Screenshot with text, 732x655 PNG | 112 KB | 12 KB | 26 KB | same as wide |
    | Screenshot, 844x400 PNG | 82 KB | 13 KB | 41 KB | same as wide |
    | Screenshot, 809x316 PNG | 32 KB | 5 KB | 13 KB | same as wide |
    | Small banner, 775x165 PNG | 14 KB | 5 KB | 18 KB, so the original is sent | same |

  - Side by side, original against copy: the text screenshot is indistinguishable at quality 80 and 90. The painting at quality 80 lost its canvas grain and the coat's texture; at 90 they are kept.
- Notes:
  - **Changed from the plan:** quality 90 for display sizes, after the painting comparison. This is why the stage ran over.
  - **Changed from the plan:** a copy can come out larger than a small, flat original. Those requests are answered with the original's bytes.
  - **Narrower than Step 2's wording:** an original no larger than 480px (or 72px in list rows) is still shown as it is, as before this cycle. There is nothing smaller to make from it.
  - The home page still names the original until Stage 3.
  - The one stored photo-like image was a PNG; no camera JPEG was on the site to compare.
  - A tap on a thread image now opens the original in the same tab.
- Estimate details (extended mode only): original scope as Step 3 Stage 2; uncertainty was medium on quality, and that is where the extra work went; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
