# Image gallery page

Cycle: `v3/image_gallery`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_gallery`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55**

Status: PR #33 open and thread reply posted; awaiting maintainer review, merge and deploy.

## Original request

Thread: [Feature request: images-focused page with an image-board-style layout](https://zenmemes.com/threads/thread-20261105231111-4eb985c2), posted by chatgpt-agent for Ilya: a dedicated page where attached images are the main thing people browse, in a grid that works on phone and desktop; each image leads to its post and discussion; a short caption or title and the reply count are visible; image proportions stay recognizable and previews are served at a sensible size. Layout, ordering, filters and whether reply images are included are left as design decisions. Feedback item: PF-20261105-F01.

## Authorization

Maintainer, 2026-11-05, after pasting the thread: "let's make this feature". Under `docs/agents/thread_to_pr.md` this authorizes one-shot execution of the outcome below, pushing the branch, opening the PR and replying in the thread. It does not authorize merging, deploying or production access.

## Outcome and work type

Application change. A new Gallery page at `/gallery/` shows posts with an attached image as a grid of image tiles, newest first, roots and replies together. Each tile shows a resized preview at the image's own proportions, the thread title (or the reply's first line) and the thread's reply count, and opens the post in its thread. Gallery appears in the main navigation after Recent (and Calendar). It is behind a new site-mutable flag, Image gallery, default on, which requires image attachments.

Excluded (follow-ups): filters and other orderings; switching Board and Recent list thumbnails to the new previews; a read-model index of image posts.

## Approach and risks

- Page path: `/images/` already serves image files (`ImageController`), so the page lives at `/gallery/`.
- Finding image posts: the Recent query (`RecentPostRepository`) with the same eligibility rules plus `instr(body, 'Image-v1:') > 0`. No schema change. Risk: whole-table scan as the forum grows; acceptable at today's size, mitigated later by an index (follow-up).
- Previews: a new `/images/preview/<sha256>.<type>` route resizes with GD (already used for upload validation) to at most 480 px on the long side, caches the result in a sibling directory of the private image store (`<image root>-previews`), and serves the original when it is already small. The cache is derived from the content hash and can be deleted at any time. Risks: memory on large originals (bounded by the 2048 px upload limit); a concurrent first request (write to a temp file, then rename); preview cache inside the image store would break `inventory()` (kept outside it).
- Proportions: tiles reserve the image's aspect ratio from the reference's width and height, so the grid does not shift as images load; very tall or wide images are clamped and cropped with `object-fit: cover`.
- Retracted, hidden or missing images: excluded by the existing eligibility rules and `ImageStore::isPresent()`.
- Themes: 13 themes and phone width; icon themes (Word97, Chicago, Midnight) need a nav icon.

## Reuse decisions

- Listing: `RecentPostRepository` eligibility and cursor pagination, extended with an images-only option rather than a copied query; `PageNavigation` and `partials/page_navigation.php` for paging.
- Images: `ListThumbnails::forPosts()` (one query per page, presence check), `ImageReference`, `ImageStore`, `ImageController` (preview mode added beside the original route).
- Titles: `ThreadTitle::displayTitle` and `PostText::plainPreview`, as Recent uses them.
- Page shell: `RouteServices::renderPageTemplate`, the Recent page template structure and `board-empty` empty state.
- Flag: modeled on List thumbnails (`FeatureFlagRegistry`, `PrivateConfigSchema`, evaluator tests); nav entry modeled on Calendar's flag-gated insertion in `TemplateRenderer`.
- New: `templates/pages/gallery.php` and a `.gallery-grid` CSS block; the thread-row layout does not fit a tile grid.

## Completion conditions

- Normal entry: Gallery in the main navigation, or `/gallery/`.
- End-to-end: image posts (roots and replies) appear as tiles with previews; a tile opens the post in its thread; paging works.
- Recovery: a missing image file shows no tile; turning the flag (or image attachments) off hides the nav item and makes `/gallery/` a 404; a deleted preview cache is rebuilt on the next request.
- Release: PR open with checks recorded; deployment belongs to the maintainer.

## Concepts touched

[Post presentation](../../concepts/post_presentation.md): changed (adds the gallery and image previews). Proposed: none.

## Verification and recovery plan

Smoke set plus a new `GalleryPageTest` (tiles, replies, missing file, flag off, preview route resize and cache) and the affected `FeatureFlagEvaluatorTest`, `FeatureFlagsBehaviorTest`, `PrivateConfigCommandTest`, `CalendarPageTest` (nav). Render the page in a local server at phone and desktop widths across themes, including the three icon themes. Full suite not run, per `AGENTS.md`.

## Starting revisions and branch

Branch `feat/image_gallery` from `db178602eb142a4e172a2a20b8e9ea973a5aada1`.

Planning commit: `2784b051`, confirmed before implementation.

## Dated execution history

- 2026-11-05T23:25:00Z: record written. Earlier the same session, before this cycle, I read `ListThumbnails`, `ImageReference`, `ImageController` and `ImageStore` and posted an analysis reply in the thread (`reply-20261105232136-421dd665`) proposing previews and the images-only query.

- 2026-11-05T23:26:00Z: planning commit `2784b051` confirmed with Git before any implementation edit.
- Discovery: `/images/` already serves image files, so the page is `/gallery/` and previews use `/images/preview/`, which the file route's name pattern never matched before.
- Discovery: `ImageStore::inventory()` rejects unexpected files in the store, so previews live in a sibling `<image root>-previews` folder; a test checks the inventory still validates.
- Choice: reply counts come from a separate lookup on `threads` by root ID, leaving the Recent query's columns and joins as they were (no read-model schema change).
- Choice: tiles carry the thread title for both roots and replies; a reply tile adds "Reply" to the meta line and opens the reply's anchor, as Recent does.
- Choice: alt text is the image's own description when the author gave one; the caption is the link text otherwise.
- Correction during verification: on a 390 px phone, greedy wrapping left single tiles stretched to full width (a square rendered 358 px tall). Added a growth cap (1.6 times the row's natural width) and a 7rem phone row; rechecked all themes.
- Correction during verification: personal phone slots are listed per section in CSS, but the slot chooser offers any More destination, so pinning Gallery would have shown no tab. Added `gallery` to both slot lists and checked the pinned tab in light and Midnight.
- Reuse: page navigation, `board-empty`, `ListThumbnails::forPosts()` (now also returning the preview URL and description), Calendar's flag-gated nav insertion and `$barOrder` placement, List thumbnails' flag pattern including the evaluator environment-cleanup list.
- Follow-ups recorded below; no new requests arrived during the cycle.

## Study history

2026-11-05T23:25:00Z intake: **0.55 projected total**. Single exposed Claude rater; the image code listed above and the Recent controller were inspected before this projection, so it is not a pristine intake. R1 hashes not re-verified. Extended estimates enabled; no separate work units.

2026-11-05T23:35:00Z closeout: delivered scope v1 in full. Projection 0.55 preserved; **retrospective scope assessment 0.45**, single exposed Claude rater, not independent. Maintainer comfortable-fit and acceptance pending.

## Outcome

With image attachments on, Gallery appears in the main navigation after Recent (and Calendar) and in the phone More panel, where it can be pinned as a tab. `/gallery/` shows posts with a stored image as tiles, 48 per page, newest first, roots and replies together, each with a preview, the thread title and the thread's reply count. Turning off Image gallery or image attachments removes the nav item and makes the page a 404. Other pages are unchanged apart from `ListThumbnails` returning two extra keys.

Follow-ups: filters and other orderings (likes, a tag); using previews for Board and Recent row thumbnails, which still load originals; a read-model index of image posts if the `instr` scan becomes slow; static first-page artifacts if Gallery gets heavy traffic.

## Verification

- `./v3 test --smoke GalleryPageTest FeatureFlagEvaluatorTest FeatureFlagsBehaviorTest PrivateConfigCommandTest CalendarPageTest RecentPostTest ImageContentTest AppNavigationPlacementTest`: 463 run, 463 passed. New `GalleryPageTest` covers tiles for a thread and a reply, ordering, ratio clamping, captions and reply counts, missing files and text posts left out, the empty state, both flags off (404 and no nav item), and the preview route (480 px resize, cache file, small originals sent as is, cache rebuilt after deletion, 404 once the original is gone, bad names). Full suite not run, per `AGENTS.md`.
- Local server on a seeded repository (ten JPEG posts from 0.33 to 3.4 aspect, one image reply, three text replies), driven by headless Chrome through `playwright-core` 1.58.1 installed in a scratch folder outside the repository: all 13 themes at 390 and 1280 px. Every preview loaded, no page overflow, rendered image ratios matched the clamped values, no console errors, and the Gallery icon showed in Word97, Chicago and Midnight. Screenshots of light, dark, Word97 and Midnight reviewed by eye. Pinned phone tab checked in light and Midnight.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not verified: the live site, Apache, static artifact builds, very large real photos (GD memory is bounded by the 2048 px upload limit but not measured), old and text-only browsers (tiles fall back to stacked images without flexbox).

## Final review

Diff covers routing, controller, query option, preview class, image route, flag and config schema, nav, template, CSS, icon CSS, tests, test registry, concept, feedback item and index, plan record, index link and ledger. No schema change and no dependency change: nothing was added to `package.json` or committed from the scratch install. Preview cache and image store stay outside the repository and web roots.

Commit/merge/publication status: completion commit `92c2db70`; branch `feat/image_gallery` pushed; [PR #33](https://github.com/gulkily/v3/pull/33) open; [thread reply](https://zenmemes.com/threads/thread-20261105231111-4eb985c2) `reply-20261105233509-ca7426b9` posted as claude (fingerprint `3076f0f2…` awaiting approval). Not merged or deployed.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
