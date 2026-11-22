> **Feature plan:** [Step 1](./image_loading_step1_solution_assessment.md) · [Step 2](./image_loading_step2_feature_description.md) · [Step 3](./image_loading_step3_development_plan.md) · [Step 4](./image_loading_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_loading`; host ledger [image_loading.md](../effort_ledger/image_loading.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.90**

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (revised in Stage 3). New entry `docs/concepts/image_serving.md` (Stage 6). Feedback: none matched; PF-20261111-F14 stays open.

## Completion Contract

- Normal entry: a visitor opens the home page or a thread with an image; an operator turns on direct serving in Tools.
- End-to-end outcome: served images carry a one-year private lifetime; every page shows compressed copies and links the original from threads; the big meme has a preview and is fetched first; a failed image retries once; with the flag on, published images are answered by Apache.
- Recovery: the direct-serving flag off empties the published folder and returns all images to the application. If the server can't resize images, pages fall back to the original, as today. After one failed retry, a reload.
- Deployment: no schema change. Deploy now is enough for Stages 1–4. Stage 5 ships with its flag off; turning it on for zenmemes.com writes a managed block into `public/.htaccess`, which already carries a local edit there, so the usual stash, pull, pop applies.
- Release: merged, deployed, flag on for zenmemes.com, response headers checked from outside, and the maintainer's phone check done or reported unverified.

## Key Risks

- **High risk:** direct serving skips the access checks. Impact: an image stays reachable by its address on a board that was locked afterwards. Early validation: Stage 5 starts with tests that a locked or lobby-gated site publishes nothing and that locking empties the folder, before the Apache block is written. Mitigation: off by default; the folder is emptied when the flag goes off or the site is locked.
- **High risk:** DreamHost may not serve the folder, and nobody but the maintainer can try it. Impact: the flag has no effect on production. Early validation: none possible from here. Stage 5 is built to fall back cleanly, and after the flag is turned on the check is one outside request per image kind. Mitigation: Stages 1–4 deliver caching and smaller images regardless.
- A deleted image stays published. Impact: it keeps being served. Early validation: a deletion test in Stage 5 as soon as publishing exists. Mitigation: removal lives in `ImageStore::remove`, the one place that already deletes an image and its copies.
- Compressed copies soften photos or text. Early validation: Stage 2 compares the seed memes and one photo side by side before any page uses the copies. Mitigation: raise the quality or the size; the original stays one tap away.
- The host adds its own cache header. Production answers already carry a second `Cache-Control` line and `Vary: User-Agent` that the application doesn't send. Early validation: an outside request right after Stage 1 deploys. Mitigation: if the host's line weakens ours, set the header in the managed `.htaccess` block as well.

## Stage 1

**Estimate: 0.10**

- Goal: browsers keep served images for a year.
- Dependencies: none.
- Expected changes: in `ImageController`, a successful image answer sends `Cache-Control: private, max-age=31536000, immutable`; every error answer keeps `private, no-store`. No route or signature change.
- Verification approach: `./v3 test --smoke ImageContentTest`, with new cases for both headers on an original, a thumbnail, a missing image and the flag off. `tests/image_content_apache_test.py` if it runs here. A browser reload of a local page with images makes no image request.
- Estimate details (extended mode only): driver: one header and its tests; uncertainty: low.
- Risks or open questions:
  - Impact: the host's own cache header (above).
  - Early warning / validation: outside request after deploy.
  - Mitigation: as above.
- Canonical components/API contracts touched: `Http/ImageController.php` (`serve()`, `serveThumbnail()`).

## Stage 2

**Estimate: 0.20**

- Goal: every page shows compressed copies; threads link the original.
- Dependencies: Stage 1.
- Expected changes: `ThumbnailStore::SIZES` gains two fit-inside sizes, `wide` (960px) and `large` (1600px), written as WebP like the others. For these two, a copy is made even when the original is already small enough, because a re-encoded copy is still far smaller than a PNG original; the original is used only when the server can't write images. `ImageController` accepts the new names. `MediaEmbedRenderer` shows `tile`, `wide` and `large` through `srcset` and wraps the image in a link to the original. `ListThumbnails` offers `wide`. Gallery and list rows keep their current sizes.
- Verification approach: `./v3 test --smoke ImageContentTest MediaEmbedRendererTest GalleryPageTest BoardListTest`; a new test that home, board list, gallery and a thread name no stored original inside an image tag; byte sizes of the copies for a seed meme and a photo, with the photo's shown copy under half its original; side-by-side screenshots at 380px and 1280px.
- Estimate details (extended mode only): driver: two sizes, the thread markup and its tests; uncertainty: medium, on quality.
- Risks or open questions:
  - Impact: softened photos or text (above). Also: posts in the offline snapshot and exported pages name images too.
  - Early warning / validation: the screenshots; a search for other places that build an image address.
  - Mitigation: adjust size or quality; change other places only if they show images inline.
- Canonical components/API contracts touched: `Images/ThumbnailStore.php` (extended), `Http/ImageController.php` (name pattern), `View/MediaEmbedRenderer.php`, `Images/ListThumbnails.php`.

## Stage 3

**Estimate: 0.15**

- Goal: the big meme shows a preview at once and is fetched first.
- Dependencies: Stage 2.
- Expected changes: `ThumbnailStore` can produce a very small preview (about 16px wide) as a text value for the page; `ListThumbnails` carries it for the featured meme only. `partials/zen_strip.php` sets it as the box's background, marks the image high priority, and offers `tile`, `wide` and `large`, never the original. `site.css` scales the preview to the box. The preview is in the server's HTML, so the first paint is final apart from the image arriving.
- Verification approach: `./v3 test --smoke BoardListTest ZenStripTest`; the home page's HTML grows by under 1 KB; a screenshot taken with the image request held back shows the preview in Gloss and Gloss Dark at 380px; the high-traffic copy of the home page carries the same markup.
- Estimate details (extended mode only): driver: the preview and the markup; uncertainty: low-medium.
- Risks or open questions:
  - Impact: making the preview on every home page view would slow the page.
  - Early warning / validation: time the board render before and after.
  - Mitigation: the preview is made once and kept beside the other copies.
- Canonical components/API contracts touched: `Images/ThumbnailStore.php`, `Images/ListThumbnails.php`, `templates/partials/zen_strip.php`, `.zen-strip__*` in `site.css`.

## Stage 4

**Estimate: 0.10**

- Goal: an image that fails to load tries once more.
- Dependencies: none.
- Expected changes: a small script, `public/assets/image_retry.js`, included on every page through `TemplateRenderer`'s script list. It listens for image errors, waits about two seconds, and asks for the same address again, once per image. It does nothing for images that load. No visible text, no new control.
- Verification approach: a browser test on the local copy that fails the first request for the big meme and one thread image: both appear without a reload, and each address is requested exactly twice. A second test fails both attempts: no further requests. With JavaScript off, pages are unchanged. The script test conventions of `MediaEmbedInlinePlayerScriptTest` are reused if they fit.
- Estimate details (extended mode only): driver: the script and its browser test; uncertainty: low.
- Risks or open questions:
  - Impact: this proves the retry in desktop Chrome, not on the maintainer's phone, and does not explain the first failure.
  - Early warning / validation: his check after deploy.
  - Mitigation: reported unverified until then.
- Canonical components/API contracts touched: `View/TemplateRenderer.php` (script list), `public/assets/` (one new file).

## Stage 5

**Estimate: 0.30**

- Goal: behind a flag, Apache serves published images without the application.
- Dependencies: Stages 1 and 2.
- Expected changes:
  - `FeatureFlagRegistry`: `FORUM_IMAGE_DIRECT_SERVING_ENABLED`, off by default, with a description that says it is for public boards.
  - A new `Images/PublishedImages` class owns a hidden folder in the web root. When the application serves an image to a signed-out visitor on a site with no lock or lobby, and the flag is on, it also places that file in the folder. Nothing else writes there.
  - `ImageStore::remove()` also deletes the image and its copies from the folder. Turning the flag off, locking the site or turning image attachments off empties it.
  - A managed block in `public/.htaccess`, written and removed the way `HighTrafficMode` manages its own: serve `/images/…` from the folder when the file exists, with the Stage 1 header; otherwise fall through to the application.
  - This is a new component beside `HighTrafficMode` because that one publishes pages and is switched on only under load; the block-writing code is shared, not copied.
- Verification approach: tests first, in this order: a locked site and a lobby-gated site publish nothing; locking empties the folder; then serve-and-publish, deletion from the folder, and flag off. `./v3 test --smoke ImageContentTest HighTrafficTest` plus the new test file. `tests/image_content_apache_test.py` extended if Apache is available here; otherwise the rewrite rules are **not verified locally** and that is reported.
- Estimate details (extended mode only): driver: the folder's lifecycle and the Apache block; uncertainty: high, mostly the host.
- Risks or open questions:
  - Impact: both High risks above.
  - Early warning / validation: the locked-site tests; the outside request after the maintainer turns the flag on.
  - Mitigation: fall through to the application whenever a file is missing.
- Canonical components/API contracts touched: `Support/FeatureFlags/FeatureFlagRegistry.php`, `Images/ImageStore.php` (`remove()`), `Http/ImageController.php`, `Host/HighTrafficMode.php` (block writer shared), `public/.htaccess`.

## Stage 6

**Estimate: 0.05**

- Goal: docs, the production checks and the closeout.
- Dependencies: Stages 1–5.
- Expected changes: `docs/concepts/image_serving.md` (storage outside the web root, copies and sizes, the one-year lifetime, what deletion can and can't reach, the direct-serving flag and how to turn it on); `zen_strip.md` revised; the Step 4 summary; a short ZenMemes thread reporting the change.
- Verification approach: outside requests to zenmemes.com after deploy for headers, and again after the flag is on; the docs smoke test; a final diff review against Step 2. The full suite is not run.
- Estimate details (extended mode only): driver: writing and two rounds of outside checks; uncertainty: low.
- Risks or open questions: the production checks depend on the maintainer deploying and turning the flag on.
- Canonical components/API contracts touched: `docs/concepts/` (inventory).

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 2** by the maintainer ("approved step 2").
- 2026-11-22 UTC, Step 3 handoff: **0.90** projected total, reaffirmed. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `a4a4aea`.
- 2026-11-22 UTC: **Approved Step 3** by the maintainer.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
