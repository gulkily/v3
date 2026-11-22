> **Feature plan:** [Step 1](./image_loading_step1_solution_assessment.md) · [Step 2](./image_loading_step2_feature_description.md) · Step 3 · Step 4

Cycle: `v3/image_loading`. Follows [zen_hero](../zen_hero/zen_hero_step1_solution_assessment.md) (PR #350, merged), which put a large image at the top of the home page.

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_loading`; host ledger [image_loading.md](../effort_ledger/image_loading.md). Standing P2 enrollment. New cycle.

Extended estimates: enabled. Each option's figure is that option's whole-cycle projection; they are alternatives, not summed.

Cycle estimate: 0.90

## Original Query

this is great. i noticed that the images are kinda slow to load and a lot of the time it's just a placeholder for a while. on owa there was an incident where the big image didn't load at all. let's talk about it?

offline web app
it just stayed blank until reload. i think longer-term, we'll probably want to store images in localstorage too for owa? for now let's do all 3 in one cycle?

[Between the two messages the assistant reported what it measured and offered three fixes: 1. let browsers keep images; 2. serve image files without PHP; 3. small changes to the home page module (high priority for the big meme, a mid-size copy for phones, a small preview while it loads). "All 3" refers to these. "owa" is the offline web app.]

## Understood Intent

Images on ZenMemes, the home page's memes first, should appear quickly and not sit as an empty box. Do all three fixes in one cycle. Keeping images for the offline web app is wanted later, not now.

## Problem

Measured on zenmemes.com on 2026-11-22:

- Every image is sent with `Cache-Control: private, no-store`, set in `ImageController`. Apache adds `max-age` as well, but `no-store` wins, so a browser downloads every image again on every visit.
- Every image request runs the whole application: the access checks, then `ImageController`. For a full-size image it also recomputes the file's SHA-256 before sending it.
- The home page makes seven such requests. On a sharp phone screen the big meme is the 1080px original, because the only smaller copy is 480px.
- The files are small: thumbnails 300–1,300 bytes, the 480px copy 6 KB, the original 72 KB. The wait is request overhead, not size.
- From the assistant's workspace each image took 0.3–0.7 seconds, and a plain CSS file took about the same. So PHP time and network distance could not be told apart there.

The blank big image in the offline web app is **not explained yet**. Two facts bear on it. The service worker keeps pages and scripts in the browser's Cache Storage but keeps no images, so offline an image can only be blank. And online, a failed image request is never tried again, so one failure leaves the box empty until a reload.

Concepts touched: [Zen strip](../../concepts/zen_strip.md) (changed by the module tweaks). Image storage and serving have no concept entry yet; this cycle adds one. Feedback: [PF-20261111-F14](../../feedback/items/PF-20261111-F14.txt) "Better offline image presentation" is related and not addressed here. PF-20261111-B32 "Posting with an image is too slow" is about uploading, a different path.

## Constraints found in the code

- `ImageStore` refuses an image folder inside the repository or the web folder. Images sit outside on purpose, so nothing reaches them without passing the application's access checks (site lock, lobby, the image flag).
- File names are the content's hash, so a given address can never show different bytes.
- Deleting a post is expected to remove its images. A copy held in a browser stays until that browser lets it go.
- `PRINCIPLES.md`: a security trade-off gets a feature flag, so the operator chooses how locked down an instance is.

## Option A — All three, with Apache serving behind a flag

**Estimate: 0.90**
Main driver: a published-images folder that Apache can serve from, kept in step with posts and deletions, plus its rewrite rules and flag. Uncertainty: medium-high; it touches deployment, deletion and private instances.

1. **Caching:** images are sent with a one-year private lifetime in place of `no-store`. A deleted image's address stops working on the server at once, and pages stop naming it; a browser that already holds a copy may show it until it lets go. Best effort to catch up is defined in Step 2.
2. **Without PHP:** behind a new flag, off by default, the site keeps a folder of the images that public posts use, and Apache serves a file from it directly when it exists, the way high-traffic mode serves pages. Anything missing falls back to PHP as today. Deleting a post removes its files there.
3. **Pages:** the big meme is marked high priority, and the box shows a tiny blurred preview until the image arrives. Everywhere on the site, a page shows a compressed copy at a fitting size and never the stored original; the original opens only when asked for. An image that fails to load is tried once more on its own.

- Pro: everything asked for. First visits get fast and repeat visits are instant.
- Pro: images stop competing with pages for PHP.
- Con: with the flag on, an image address works for anyone who has it, without the access checks. That is fine for a public board and wrong for a locked one, which is why it is a flag.
- Con: the largest and riskiest part buys the least once caching is in place.

## Option B — Caching, a lighter PHP path, and the module tweaks

**Estimate: 0.45**
Main driver: cache headers with tests, skipping the per-request hash, the new image size and the preview. Uncertainty: low-medium.

Fixes 1 and 3 as in Option A. For fix 2, images still go through PHP but do less there: no re-hash of a file whose name already is its hash, and a reply of "not changed" when the browser already has the file.

- Pro: keeps every access check. No new folder, no deployment change.
- Pro: most of the benefit for about half the work.
- Con: a first visit still pays one application start per image.
- Con: not literally "without PHP", so it is less than what was asked.

## Option C — Option A plus images kept for the offline web app

**Estimate: 1.10**
Main driver: Option A plus the service worker keeping the home page's meme images, with rules for how much to keep and when to drop it. Uncertainty: high.

- Pro: the blank image offline goes away too.
- Con: two large pieces in one cycle. The maintainer said offline storage is for later.

## Recommendation

Option A, as asked and as revised on 2026-11-22, with the Apache part behind a flag that is off by default and turned on for zenmemes.com. Build it in the order 1, 3, 2, so the two safe fixes land first and can be deployed even if the Apache part needs more review.

One caution: Option B gets most of the speed without giving up the access checks. If the flag's trade-off looks wrong in Step 2, falling back to B is cheap.

On storing images in localStorage for the offline web app: the app already keeps its pages in the browser's Cache Storage, which holds files of any size as they are. localStorage holds about 5 MB of text per site, so images would have to be turned into text and would fill it fast. A later offline cycle should use Cache Storage; PF-20261111-F14 is the place to pick that up.

Vertical slice: on an iPhone SE, open zenmemes.com signed out. The big meme's box shows a blurred preview at once and the sharp image soon after. Reload: every image appears with no wait. With the Apache flag on, the server log shows no PHP request for those images. Delete a test post with an image: its address stops working.

## Maintainer's answers (2026-11-22)

"Yes, a browser may keep an image for a year. It's okay if a deleted image reappears until the client catches up, although we should make best effort to catch up. I don't know why no store was chosen. The phone was online. The phone was online. Yes, every image, perhaps we should compress them when we store them."

- **Lifetime:** one year. A deleted image may linger in a browser, with best effort to catch up.
- **`no-store`:** no known reason. Nothing in the repository records one either.
- **The blank image:** the phone was online, so a request failed and was never retried. One automatic retry joins the scope. The cause of the failed request itself is still unknown.
- **Scope:** every image on the site.
- **Compress when stored:** partly there already. The browser shrinks an image to at most 2048px and re-encodes it at 85% quality before upload (`image_prepare.js`). The server then keeps those exact bytes, because the signed post names their hash; re-compressing the stored file would break that reference. What can change is what pages show: compressed copies only, which is now part of fix 3. Uploads that skip the browser (agents posting through the API) arrive as sent.

## Open questions for the maintainer

- **What "best effort to catch up" should cover.** A server can't recall one file from a browser's own store; it can only ask the browser to drop everything it holds for the site. Default: the address fails at once, no page names it, and the offline web app drops its copy at its next update once it keeps images. Not by default: clearing the whole site's stored files on a deletion.
- **API uploads:** should the server refuse or shrink an oversized image that skipped the browser's preparation? Default: leave as is in this cycle.

## Answered questions (kept for the record)

- **How long may a browser keep an image?** Default: one year, private, since an address can never change its content. The cost: an image deleted from the site stays visible in browsers that already loaded it, until they clear it. Alternative: one day.
- **Was `no-store` chosen so deleted images vanish at once?** If so, the default above needs your explicit agreement.
- **The blank big image:** was the phone online when it happened? Offline it is expected today. Online it points to a failed request, and the cycle should then add one automatic retry.
- **Scope of the faster images:** the home page's memes only, or every image on the site (thread images, gallery, thumbnails in lists)? Default: every image, since they share one path.

## Enhancement requests

- **Request:** keep images for the offline web app. **Disposition:** later cycle, in Cache Storage rather than localStorage. **Approval status:** maintainer said "longer-term". **Follow-up:** PF-20261111-F14.

## Study snapshots and approval

- 2026-11-22 UTC, intake after measuring production and reading `ImageController.php`, `ImageStore.php`, `ThumbnailStore.php`, `service_worker.js`, `public/.htaccess` and `partials/zen_strip.php`: 0.80 projected total for the recommended scope. No pristine intake. Claude, single exposed rater; R1 hashes not re-verified this session; baseline `origin/main` `22fc9f1`.
- Step 1 handoff: 0.80 for Option A (alternatives B 0.45, C 1.10; not summed). No implementation.
- 2026-11-22 UTC revision after the maintainer's answers: one-year caching, a retry, and compressed copies on every page join Option A. Projected total **0.90** for Option A, up from 0.80, for the site-wide display copies and the retry. B and C unchanged for the record.
- 2026-11-22 UTC: **Approved Step 1** by the maintainer ("approved step 1"), Option A as revised.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

*— Revised by Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
