> **Feature plan:** [Step 1](./image_loading_step1_solution_assessment.md) · [Step 2](./image_loading_step2_feature_description.md) · Step 3 · Step 4

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_loading`; host ledger [image_loading.md](../effort_ledger/image_loading.md). Standing P2 enrollment.

Extended estimates: enabled.

**Cycle estimate: 0.90**

## Problem

Every image is downloaded again on every visit, every image request runs the whole application, and thread pages show the stored original at full size. On the home page the big meme often sits as an empty box, and once, online in the offline web app, it stayed blank until a reload.

## Approved direction

Step 1 was approved on 2026-11-22 ("approved step 1") after its revision. This follows Option A as revised: browser caching for a year, Apache serving behind a flag, compressed copies on every page, a preview and high priority for the big meme, and one automatic retry.

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed: preview, priority, a sharper mid-size copy). New concept entry: Image serving (how images are stored, copied, cached, served and deleted). Feedback: PF-20261111-F14 is related and stays open; no item matches this cycle.

## User stories

- As a visitor, I want images to appear at once on a page I've seen before, so that the site feels quick.
- As a first-time visitor on a phone, I want to see something in the big meme's place straight away, so that the page doesn't look broken while it loads.
- As a reader of a thread, I want its photos to load at a size that fits my screen, so that I don't wait for a file far bigger than I can see.
- As anyone online, I want an image that failed to load to try again by itself, so that I don't have to reload the page.
- As an operator of a public board, I want image files served without starting the application, so that images stay fast when the site is busy.
- As an operator of a locked board, I want that left off, so that images stay behind the site's access checks.
- As someone who deleted a post, I want its image to stop being served at once, so that it is gone for everyone who hasn't already loaded it.

## Core requirements

- **Kept by the browser.** A successfully served image may be kept by that browser for one year. Error answers are never kept. This applies to every image and every copy.
- **Compressed copies on pages.** Wherever a page shows an image (home page, lists, gallery, threads), it shows a compressed copy sized for the screen. The stored original is never shown inline and opens only when someone asks for it, as a link from the thread. The original's bytes are never changed, because the signed post names their hash.
- **Direct serving, by choice.** A new flag, off by default, lets Apache serve an image file directly when a published copy exists, with no application start. Only images named by posts the public can read are published. Anything not published is served by the application as today. With the flag off, nothing is published.
- **Deletion catches up as far as a server can.** When a post's image is removed, its address and all its copies stop being served at once, in both paths, and no page names it. A browser that already holds a copy may show it until it lets go.
- **The big meme.** Its box shows a small blurred preview from the first paint, the browser is told to fetch it first, and sharp phone screens get a mid-size copy, not the original.
- **Retry.** An image that fails to load is tried once more after a short wait. Without JavaScript there is no retry and nothing else changes.

## Delivery scope

- Work type: application change (image serving, display copies, the published folder and its Apache rules, a flag, thread and home page markup, a small retry script), tests, a concept entry, and deployment notes for turning the flag on.
- Out of scope: keeping images for the offline web app; shrinking or refusing oversized API uploads; clearing a browser's stored files when something is deleted; a CDN; changing how images are uploaded or stored; the gallery for All.

## Completion boundary

- Normal entry: any visitor opens the home page or a thread with an image. An operator turns the flag on in Tools.
- End-to-end outcome: on a second visit, images come from the browser with no request. On a first visit, the big meme's box shows a preview, then the image. A thread photo arrives as a compressed copy. With the flag on, image requests are answered by Apache.
- Recovery: the direct-serving flag off returns every image to the application path and empties the published folder. A host that can't serve the folder keeps working through the application. A failed image retries once; after that a reload is the recovery, as today.
- Deployment: the flag is off after deploy, so Deploy now is safe by itself. Turning it on for zenmemes.com is a separate operator step, described in the concept entry, and may need a one-time change to the server's `.htaccess`, which carries a local edit there.
- Release: merged, deployed, flag turned on for zenmemes.com, and checked by the maintainer on his phone.

## Known user or environment

The maintainer, on an iPhone SE (3rd gen), online, in the installed offline web app, saw the big meme stay blank until a reload. Ordinary entry: open the app to the home page. The failure to handle: an image request that fails once. Next action unaided: none needed if the retry works; otherwise reload. How he verifies: the box shows the preview, then the image; with a brief loss of signal the image still appears within a few seconds. Evidence: his own check on that phone after deploy. A test in desktop Chrome that blocks the first request shows the retry works but **does not satisfy this condition**; until he checks, it is reported unverified. The cause of the original failed request is unknown and may not be fixed by this cycle.

## Risks

- **Direct serving skips the access checks.** With the flag on, an image address works for anyone who has it, even if the board is later locked. Impact: a privacy mistake on a locked board. Earliest validation: a test that a locked or lobby-gated site publishes nothing, before any Apache rule is written. Mitigation: off by default; the flag's description says who it is for; publishing stops and the folder empties when the site is locked.
- **A deleted image survives in the published folder.** Impact: a deleted image keeps being served, the opposite of what was asked. Earliest validation: a deletion test against the published folder in the stage that creates it. Mitigation: removal goes through the one place that already removes an image and its copies (`ImageStore::remove`), and a rebuild drops anything no post names.
- **The host won't serve the folder.** DreamHost may refuse the link or the rule. Impact: the flag does nothing. Earliest validation: a manual check on production before the folder logic is finished, with one test file. Mitigation: the application path stays complete, so the worst case is today's behaviour plus caching.
- **Thread photos look worse.** A compressed copy could soften a detailed photo or a screenshot of text. Earliest validation: side-by-side screenshots of the seed memes and one photo thread. Mitigation: the original is one tap away, and the copy's size and quality are set in Step 3 against those screenshots.

## Shared component inventory

- `Http/ImageController.php`: the one place images are served and their headers set. Changed in place for caching; no second controller.
- `Images/ThumbnailStore.php`: makes and names the display copies (`sq`, `sq2x`, `tile`). Extended with the new sizes; no new store.
- `Images/ImageStore.php`: stores originals outside the web folder and removes an image with its copies. Unchanged in what it stores; removal is extended to the published folder.
- `Images/ListThumbnails.php`: gives lists and the home page their image addresses. Extended for the mid-size copy and the preview.
- `View/MediaEmbedRenderer.php`: the one place a thread shows an image, today the original. Changed to a compressed copy with a link to the original.
- `templates/partials/zen_strip.php`: changed in place.
- `Host/HighTrafficMode.php` and the `V3 HIGH TRAFFIC` block in `public/.htaccess`: the existing way Apache serves prepared files from a hidden folder in the web root. The published image folder follows that pattern; it is a new folder because its contents and lifetime differ.
- `FeatureFlagRegistry`: one new flag for direct serving, beside `FORUM_IMAGE_CONTENT_ENABLED`.
- `public/assets/`: no existing image retry exists; one small script is added.
- `public/service_worker.js`: read only. It does not handle images and is not changed.

## User flow

1. A visitor opens zenmemes.com on a phone. The big meme's box shows a blurred preview, then the sharp image.
2. They open a thread with a photo. It appears as a compressed copy; a tap opens the original.
3. They come back later. Every image they saw is there at once.
4. Their signal drops for a moment on a new page. The image that failed appears a few seconds later without a reload.
5. An operator deletes a post with an image. Its address answers "not found" from then on.

## Success criteria

- An image answer carries a one-year private lifetime and no `no-store`; an error answer carries `no-store`. Tests cover both.
- Reloading the home page in a browser makes no image requests.
- No page's HTML names a stored original inside an image tag. A test checks home, board list, gallery and a thread.
- A thread photo's shown copy is under half the original's bytes for the photo test image.
- With the flag on in a test instance, a published image is answered without the application; with the flag off or the site locked, the published folder is empty.
- After deleting a post, its image and every copy answer "not found" in both paths.
- The first request blocked once, the image appears without a reload.
- The maintainer's phone check passes, or is reported unverified.

## Enhancement requests

- **Request:** keep images for the offline web app. **Disposition:** later cycle, in Cache Storage. **Follow-up:** PF-20261111-F14.
- **Request:** compress images when stored (maintainer, 2026-11-22). **Disposition:** met by compressed display copies; the stored original is left alone because posts name its hash. Shrinking API uploads is not in this cycle.

## Study snapshots and approval

- 2026-11-22 UTC: **Approved Step 1** by the maintainer; Option A as revised.
- 2026-11-22 UTC, Step 2 handoff: **0.90** projected total, reaffirmed from the Step 1 revision. Claude, single exposed rater. Scope v1. Reference R1, hashes not re-verified this session. Baseline `origin/main` `a4a4aea` (merged into the branch). Awaiting `Approved Step 2`.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
