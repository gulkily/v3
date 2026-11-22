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

## Stage 3 - The featured meme: a stand-in, fetched first, never the original

**Original estimate: 0.15**

**Retrospective stage-work assessment: 0.15**

- Changes:
  - `ThumbnailStore::inline()`: a 16px-wide WebP of an image as a `data:` address, made once and kept beside the other copies under a name that is not a size, so it is never served by address and is removed with the image.
  - `BoardPageController::zenStrip()` asks for it for the featured meme only. `ListThumbnails` entries carry their `reference` for that.
  - `partials/zen_strip.php`: the featured image offers the tile and the display copies through `srcset`, is marked `fetchpriority="high"`, and carries the stand-in as its own background. The stored original is no longer named there.
  - `site.css`: the stand-in fills the image's box. It sits on the image element, so dark schemes dim it with the image.
- Verification:
  - `./v3 test BoardListTest ZenStripTest`: 20 run, 20 passed. `testTheFeaturedZenMemeShowsACompressedCopyOverAStandIn` (renamed) checks the markup, that the stand-in is a 16x11 WebP under 1 KB, and that the module never names the original. The full suite was not run.
  - Local copy with the six seed images restored from production: the home page's HTML grows by 166 bytes. Three home page loads took 0.11 s each once the stand-in existed.
  - Browser at 380x667, 2x, with image requests held back: the box shows the blurred stand-in in Gloss and Gloss Dark, then the image. The first image the browser asked for was the featured one. A 2x phone chose the `wide` copy (20 KB), where before it chose the 72 KB original.
- Notes:
  - **Changed during the stage:** the stand-in first sat on the link, where Gloss Dark showed it brighter than the image that replaced it. It moved onto the image element.
  - The thumbnail row has no stand-ins; its boxes are empty until their images arrive.
  - **Not checked separately:** the high-traffic copy of the home page. It is rendered from the same template.
- Estimate details (extended mode only): original scope as Step 3 Stage 3; uncertainty low-medium; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 4 - A failed image tries once more

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - New `public/assets/image_retry.js`: when an image's request fails, it waits two seconds and asks again, once per image. It also catches images that failed before the script ran. Images that load are never touched. It adds no text and no control.
  - `TemplateRenderer` includes it on every page when image attachments are on, in both places that already add the image scripts.
  - New `tests/browser/image_retry.mjs`, following the other files in `tests/browser/`.
- Verification:
  - The browser test passes in **Chrome and in WebKit**, the engine Safari uses: an image that fails once loads on the second try; one with `srcset` does too; one that always fails is asked for exactly twice and then left alone; one that loads is asked for once. It was run from a copy with the library's import path changed, because `playwright-core` is not installed in this checkout.
  - `./v3 test --smoke ImageServingTest BoardListTest MediaEmbedRendererTest TemplateRendererMediaEmbedsScriptTest`: 506 run, 506 passed. The full suite was not run.
  - The local home page includes the script.
- Notes:
  - **Not verified:** the maintainer's iPhone SE in the installed app. Desktop WebKit is the closest this workspace gets. The reason his original request failed is still unknown; this makes one failure recover, and does nothing for a server that fails twice.
  - Without JavaScript nothing changes.
  - The browser test is not wired into `./v3 test`; like its neighbours it is run by hand with `node tests/browser/image_retry.mjs`.
- Estimate details (extended mode only): original scope as Step 3 Stage 4; uncertainty low; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 5 - Direct serving behind a flag

**Original estimate: 0.30**

**Retrospective stage-work assessment: 0.30**

- Changes:
  - New flag `FORUM_IMAGE_DIRECT_SERVING_ENABLED` ("Direct image serving"), off by default, site-changeable, needs image attachments on.
  - New `Images/PublishedImages`: a folder `public/.published-images` (ignored by git). When the application serves an original or a copy and the site may publish, it also places that file there under the address it was served at. A site may publish only with the flag on, image attachments on, and members-only off.
  - `Application::handle()` starts by emptying and removing the folder when the site may no longer publish. With no folder this is one file-system check.
  - `ImageStore::remove()` also removes the image's published original and copies.
  - `public/.htaccess` carries the rules, shipped and inert until the folder exists: `/images/…` is answered from the folder when that exact file is there, for GET and HEAD without a query; everything else goes to the application as before. The folder's own address is refused. The folder gets its own small `.htaccess` with the same one-year header the application sends.
  - New deployment setting `FORUM_PUBLISHED_IMAGE_ROOT`, for a document root that is not the checkout's `public/` (and for tests).
- Verification:
  - `tests/ImageServingTest.php`, three new tests: a members-only board refuses the image and publishes nothing; copies left from when it was public are gone after its next request of any kind; the same when the flag or image attachments go off; a public board with the flag off publishes nothing. A public board with the flag on publishes exactly what it served, byte for byte, readable by the web server; a legacy thumbnail name and a copy answered with its original are not published under the wrong name; path tricks publish nothing. Removing the image removes its files and its address answers 404. The rule's pattern accepts every name the application serves and refuses seven others.
  - **A real Apache** (installed in the workspace for this): new `tests/image_direct_serving_apache_test.py` runs the shipped `public/.htaccess` in a private server. A published original and a published WebP copy are answered by Apache with the right type and the one-year header; an unpublished image, a query string, a POST and odd names all reach the application; the folder's address is refused in any letter case; after removal, and after emptying, the addresses reach the application again. It passes.
  - `./v3 test --smoke ImageServingTest ImageContentTest DeploymentSettingsTest FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest HighTrafficTest HighTrafficPublicationTest WebServerRoutingTest GalleryPageTest BoardListTest ImageZipBackupTest ImageZipRestoreTest InstanceImageImportTest StatusCommandTest PrivateConfigSchemaTest`: 537 run, 537 passed. The full suite was not run.
- Notes:
  - **Changed from the plan:** the rules ship in `public/.htaccess` and do nothing without the folder, as high-traffic mode's rules already do. Nothing writes to `.htaccess` when the flag is turned on, so there is no managed block and no shared block writer.
  - **Changed from the plan:** an image is published when it is first served, not when its post is written. What gets published is exactly what the application was already giving any visitor.
  - **Changed from the plan:** the code and the rules were written in one pass, and the members-only test was written straight after, not before.
  - **Found on the way:** the first version of the emptying check sat after some early returns, so `/api/version` never ran it. The test caught it; the check is now the first thing a request does.
  - **Found on the way:** the folder's refusal rule missed upper-case spellings. The Apache test caught it.
  - **Limits:** a page served by the static fast path doesn't run the application, so after the flag goes off on a public board the folder is emptied at the next request that does. On a members-only board every request does. A document root other than `public/` needs `FORUM_PUBLISHED_IMAGE_ROOT`.
  - **Still High risk and not verified:** whether DreamHost serves the folder. The local Apache is not their setup. `tests/image_content_apache_test.py` (the older test) was not run; it needs a fixture from another script.
  - `StatusCommandTest` fails while `public/.htaccess` has uncommitted changes, because the status command warns about local edits. It passes on a committed tree.
- Estimate details (extended mode only): original scope as Step 3 Stage 5; uncertainty stays high on the host; the simpler rule design offset the time spent installing Apache and writing a real server test; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
