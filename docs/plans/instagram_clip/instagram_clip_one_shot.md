# Instagram clip bookmarklet

Cycle v3/instagram_clip · One-shot · [Plans](../README.md)

Effort study: `railspike-effort` / `v3/instagram_clip`; host ledger [instagram_clip.md](../effort_ledger/instagram_clip.md). Standing P2 enrollment. Extended estimates: disabled.

**Cycle estimate: 0.35**

Status: implemented, PR open; next permitted action: maintainer merge, then turn on **Instagram clip** in Tools → Feature flags on mitrapclub.com.

## Original request

"can you please add a bookmarklet for me to click once and then clip an instagram image to mitrapclub.com" (Ilya, 2026-11-25).

## Authorization

A bounded one-shot through an open PR: one bookmarklet that opens a new post with the Instagram post's link and its image attached, behind a site flag. No feedback item matched.

## Outcome

- Flag `FORUM_INSTAGRAM_CLIP_ENABLED` (Instagram clip; site-mutable, off by default, requires image content). Off hides the bookmarklet and the image fetch (404).
- Bookmarklets page: **Instagram** in both same-window and new-window sections.
- The bookmarklet (`instagramComposeUrl` in `public/assets/tools_bookmarklets.js`) finds the post on screen (its page, the open post dialog, or the feed post with the most visible image), normalizes the link to `https://www.instagram.com/p|reel/<code>/`, takes the largest `srcset` entry of the most visible Instagram-CDN image, and opens `/compose/thread` with the link as the body (selection first, if any) plus `instagram_post` / `instagram_image`.
- `GET /internal/instagram-clip/image` (`InstagramClipImageController`): the browser cannot read Instagram's image host, so the server fetches the image. The post must pass `MediaEmbedDetector` as Instagram; image addresses are fetched only from `*.cdninstagram.com` / `*.fbcdn.net` over HTTPS, no redirects, 15 MB cap, and only JPEG/PNG/WebP/GIF bytes are returned. Falls back to the post page's og:image via the existing `InstagramPagePreviewFetcher`.
- `public/assets/instagram_clip.js` (compose page, flag on): fetches that image, waits for the composer's image binding, and calls `ForumImageUpload.select()` (the same path as a picked, dropped or pasted file). Clears the clip parameters from the address so a reload does not attach again. On failure it shows a notice; the link stays, so the post still gets the Instagram preview card.

## Reuse decisions

- Bookmarklet list, generation and copy UI: existing `ToolsPageController::bookmarklets()` / `tools_bookmarklets.js`, same pattern as Tweet.
- Image attach: existing `ForumImageUpload.select()`; preparation, eligibility (approved key with image upload) and upload unchanged.
- og:image lookup: existing `InstagramPagePreviewFetcher`; post validation: existing `MediaEmbedDetector`.
- New: `RouteServices::sendBinary()` (no helper sent in-memory bytes with a type) and the controller (no existing endpoint fetches an image for the composer).
- No nav icon for the new bookmarklet kind, matching Port HN.

## Checks

- `./v3 test --smoke InstagramClipImageControllerTest FeatureFlagEvaluatorTest`: 496/496 passed. Full suite not run.
- Headless Chrome against a mock Instagram feed page: the bookmarklet picked the post from a username-prefixed link, the 1080w image, and skipped the avatar.
- Headless Chrome against a local server with the flag on (endpoint mocked): the composer attached `instagram.png`, kept the link in the body and cleared the clip parameters.

## Limitations

- Not verified against live Instagram (no access from the workspace): its markup may differ from the mock, and whether DreamHost can fetch Instagram's CDN is unverified (the preview card's og fetch already works there). A reel attaches its cover image.
- Attaching still needs an approved key with image upload; otherwise the post goes out with the link only.
