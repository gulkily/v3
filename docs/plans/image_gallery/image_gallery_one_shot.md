# Image gallery page

Cycle: `v3/image_gallery`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_gallery`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.55**

Status: planning; next permitted action: planning commit, then implementation.

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

Planning commit: pending.

## Dated execution history

- 2026-11-05T23:25:00Z: record written. Earlier the same session, before this cycle, I read `ListThumbnails`, `ImageReference`, `ImageController` and `ImageStore` and posted an analysis reply in the thread (`reply-20261105232136-421dd665`) proposing previews and the images-only query.

## Study history

2026-11-05T23:25:00Z intake: **0.55 projected total**. Single exposed Claude rater; the image code listed above and the Recent controller were inspected before this projection, so it is not a pristine intake. R1 hashes not re-verified. Extended estimates enabled; no separate work units.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
