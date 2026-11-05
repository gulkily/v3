# Board and Recent image thumbnails

Cycle: `v3/board_thumbnails`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/board_thumbnails`; [host ledger](../effort_estimation_trial.md). Standing P2 enrollment.

Extended estimates: enabled

**Cycle estimate: 0.50**

Status: planning committed; implementation next.

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

To be filled from inspection: the board list card in `templates/pages/board_list.php`, the Recent template, and existing image attachment rendering and URLs.

## Completion conditions

- Normal entry: open `/` and `/recent` (or the Recent route) on zenmemes.
- End-to-end: an image post shows its thumbnail in both lists; clicking it opens the thread.
- Recovery: a missing or retracted image shows no thumbnail and no broken icon.
- Release: PR open with checks recorded; deployment belongs to the maintainer.

## Concepts touched

[Post presentation](../../concepts/post_presentation.md) and [recent conversation](../../concepts/recent_conversation.md): likely changed. Proposed: none.

## Verification and recovery plan

Smoke set plus changed tests; a test fixture with an image post on both lists. Every theme at phone and desktop width. qdb and other profiles unchanged. Full suite not run, per `AGENTS.md`.

## Starting revisions and branch

Branch `feat/board-thumbnails` from `822d6134692883e145728db96b1a9aa62f1ca496`.

Planning commit: pending.

## Dated execution history

- 2026-11-05T16:44:35Z: record written before detailed inspection.

## Study history

2026-11-05T16:44:35Z intake: **0.50 projected total** for both lists. Single exposed Claude rater; board templates were seen earlier today in `v3/site_purpose`, but the attachment and listing code was not inspected. R1 hashes not re-verified. Extended estimates enabled.

*— Claude · 2026-11-05 UTC · Model: claude-opus-5-5*
