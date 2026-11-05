# Board and Recent image thumbnails

Cycle: `v3/board_thumbnails`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/board_thumbnails`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.50**

Status: PR #28 open and thread reply posted; awaiting maintainer review, merge and deploy.

## Original request

Maintainer, 2026-11-05: "let's please do the board thumbnails, and note that they've also been requested for the recent page"

Threads: [board thumbnails](https://zenmemes.com/threads/thread-20261103065646-b1b83702) ("this page (for now liked only) should have a small thumbnail that spans the entire height of an item that has an image attached"); [Recent requests](https://zenmemes.com/threads/thread-20261103185300-870542f7) ("thumbnails for image posts"). Feedback item: PF-20261104-F60.

## Authorization

The maintainer's message above (2026-11-05), following the thread-to-PR procedure in `AGENTS.md`. It authorizes one-shot execution of the outcome below, pushing the branch, opening the PR and replying in the board thread. It does not authorize merging or deploying. The maintainer also wants the result to serve as the About page's build example in `v3/site_purpose`; swapping it in is separate work after merge.

## Outcome and work type

Application change. On the board and on Recent, every item whose post has an attached image shows a small thumbnail of that image, as tall as the item. Items without an image look as they do now. Clicking the thumbnail opens the thread, like the rest of the item.

Excluded: the other Recent requests in the same thread (longer summaries, a promote button); they stay follow-ups.

## Approach and risks

To settle on inspection, before dependent work:

- Which image an item shows: the thread root's first attached image is the likely reading. Record the choice.
- Where the image reference comes from for a list: the listing query or read model may not carry attachments today. Prefer an existing field or join over a schema change.
- **High risk:** list performance. Impact: slower board for everyone. Early validation: query timing on the local fixture. Mitigation: one indexed lookup per page, never per item, and lazy-loaded images.
- Layout across 13 themes and phone width. Impact: crowded or broken cards. Early validation: phone screenshots in light, Word 97 and Chicago before finishing CSS. Mitigation: fixed small width, cropped with `object-fit`, text keeps its place.
- Retracted or missing images. Impact: broken image icons. Mitigation: render only when the attachment record resolves.

## Reuse decisions

- Image data: `ImageReference::fromBody()` and `filename()`, the same parser post pages use; images are served by the existing `/images/<sha256>.<type>` route (`ImageController`), which still verifies the checksum.
- Presence: new `ImageStore::isPresent()` checks the file exists at the referenced size. `resolve()` hashes the whole file, which is fine for one post page but too slow for 50 rows.
- Lookup: new `ForumRewrite\Images\ListThumbnails`, one `posts` query per page by primary key, filtered to non-retracted bodies containing `Image-v1:`. No schema change: `threads.body_preview` holds only the first line and Recent truncates bodies at 1024 characters, so neither can carry the reference.
- Rows: both lists already share the `thread-row` grid (`partials/thread_row.php`, `partials/recent_entry.php`); a new `partials/row_thumbnail.php` serves both, and `.thread-row--thumb` adds the first column in `public/assets/site.css`.
- Flag: `FORUM_IMAGE_CONTENT_ENABLED`, as for post images.

## Completion conditions

- Normal entry: open `/` and `/recent` (or the Recent route) on zenmemes.
- End-to-end: an image post shows its thumbnail in both lists; clicking it opens the thread.
- Recovery: a missing or retracted image shows no thumbnail and no broken icon.
- Release: PR open with checks recorded; deployment belongs to the maintainer.

## Concepts touched

[Post presentation](../../concepts/post_presentation.md): changed (adds list thumbnails). [Recent conversation](../../concepts/recent_conversation.md): used unchanged. Proposed: none.

## Verification and recovery plan

Smoke set plus changed tests; a test fixture with an image post on both lists. Every theme at phone and desktop width. qdb and other profiles unchanged. Full suite not run, per `AGENTS.md`.

## Starting revisions and branch

Branch `feat/board-thumbnails` from `822d6134692883e145728db96b1a9aa62f1ca496`.

Planning commit: `42e93369`.

## Dated execution history

- 2026-11-05T16:44:35Z: record written before detailed inspection.
- Planning commit `42e93369` confirmed before implementation.
- Choice: each row shows the image of its own post. For the board that is the thread root; for Recent it is the contribution itself, so an image reply shows its image. The thumbnail repeats the title link and is hidden from the tab order and screen readers.
- Discovery: the board row and Recent row cannot carry the image reference from existing columns (see Reuse decisions), so a per-page lookup was added instead of a read-model change.
- Correction during work: the first CSS placed the thumbnail rules before the base row rules, which would have kept the excerpt in column 1; moved after them before any check.
- Not changed: board search and profile pages also use `thread_row.php`; they show no thumbnails because they do not run the lookup. Follow-up if wanted.

## Study history

2026-11-05T16:44:35Z intake: **0.50 projected total** for both lists. Single exposed Claude rater; board templates were seen earlier today in `v3/site_purpose`, but the attachment and listing code was not inspected. R1 hashes not re-verified. Extended estimates enabled.

2026-11-05T17:45:00Z closeout: delivered scope v1 in full. Projection 0.50 preserved; **retrospective scope assessment 0.40**, single exposed Claude rater, not independent. Maintainer comfortable-fit and acceptance pending.

## Outcome

On zenmemes, board list rows and Recent rows for posts with a stored image show a small thumbnail, as tall as the row, linking where the title does. Other rows, other profiles and other pages are unchanged. Live deployment and the About example swap in `v3/site_purpose` remain.

## Verification

- `./v3 test --smoke BoardListTest RecentPostTest ImageContentTest`: 439 run, 439 passed. New test `BoardListTest::testImagePostsShowAThumbnailOnTheBoardAndRecent` covers a stored image (thumbnail), a reference with no file (none), a text post (none), and images turned off (none), on both lists. Full suite not run, per `AGENTS.md`.
- Headless Chrome against a local zenmemes server seeded with three image posts, one text post and an image thread with a reply: board and Recent in all 13 themes at 390 and 1280 px. Every thumbnail loaded, filled the row's content height (73 to 122 px tall, 56 px wide), and the reply count stayed in its own column. Contact sheets reviewed by eye.
- `./v3 feedback check`: 0 problems; `./v3 feedback index` regenerated.
- Not verified: `tests/browser/recent.mjs`, statically generated first pages on a real deployment, and the live site.

## Final review

Diff covers 12 files within the outcome: controllers, one helper, one partial, row templates, CSS, test, concept, item and index. No schema or dependency change; `playwright-core` was installed from the existing lockfile for local checks only.

Commit/merge/publication status: completion commit `acc161fc`; branch `feat/board-thumbnails` pushed; [PR #28](https://github.com/gulkily/v3/pull/28) open; [thread reply](https://zenmemes.com/threads/thread-20261103065646-b1b83702) `reply-20261105165352-caf2a99e` posted as claude (new key, fingerprint awaiting approval). Not merged or deployed.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
