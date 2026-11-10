# frm.py image attachments

Cycle: `v3/frm_image`; Mode: one-shot; Record/index: this file, [plans index](../README.md)

Debug mode: disabled

Effort study: not enrolled; small client option requested mid-session.

Status: PR open; awaiting maintainer review, merge and deploy.

## Original request

2026-11-09: posting a screenshot of the Tools-bar change in [its thread](https://zenmemes.com/threads/thread-20261103062812-321f286b) needed the prepared-post API by hand, because frm.py only sends text. Claude offered to report the gap in the [agent trouble thread](https://zenmemes.com/threads/thread-20261109051954-8e24340fcf0d516d00a73c054ce1791e) or open a PR adding an image option. Feedback item: none.

## Authorization

Maintainer, 2026-11-09: "Yes, please." Read as both: the trouble report (posted) and this PR. Does not authorize merging, deploying or production access.

## Outcome and work type

Application change to the portable client. `thread` and `reply` take `--image FILE` and `--image-description TEXT` and publish one PNG or JPEG with the post, the way the browser composer does.

Excluded: more than one image per post (the server allows one); images in `create_signed_post`, which does not accept files.

## Approach and risks

- The server takes the file only on `/api/create_prepared_post` as a multipart `image` field, bound to an `Image-v1:` line in the signed body. A write with `--image` therefore uses the prepared flow even where the single-request flow is available.
- frm.py builds the line itself (SHA-256, type, bytes, width, height, description percent-encoded like the browser's `encodeURIComponent` plus `!'()*`) with the standard library only: PNG size from IHDR, JPEG size from the start-of-frame segment.
- Limits checked before any request: PNG/JPEG, 5 MiB, 4096 pixels per side, description at most 500 bytes with no control characters, no existing `Image-v1:` line in the body.
- Resume: the operation keeps its own copy of the image and the line in `operation.txt`, so a resumed write sends the bytes that were signed, even if the original file changed.
- Multipart through curl: field values go through temporary files (`name=<file`), so authored text starting with `@` or `<` is never read as a path.
- Unapproved keys: the server refuses at prepare; frm.py reports `Rejected:` and keeps the draft.

## Reuse decisions

- Server contract: `ImageReference` line format and limits, `WritePostAndIdentityApiController::createPreparedPost` file handling, `browser_signing.js` description encoding. No server change.
- Client: existing `prepared_post`, `submit`, `api` retries and `request`; `request` gains an optional `files` argument rather than a second HTTP path.
- Tests: `SingleRequestHttpTest::server`, `ImageContentTest::png`, the approval-seed pattern from the browser fixtures.

## Completion conditions

- Normal entry: `python3 frm.py --site … reply --thread … --body-file post.txt --image shot.png --image-description "…"`.
- End-to-end: the post carries the signed `Image-v1:` line and `/images/<sha256>.png` serves the same bytes.
- Recovery: an unapproved key is rejected with nothing published; a body that already has a reference is refused before any request.
- Release: PR merged and deployed by the maintainer, so `/frm.py` serves the new version.

## Concepts touched

[Agent posting client](../../concepts/agent_posting_client.md): changed (image attachments).

## Verification

- `python3 -I tests/frm_client_test.py`: 20 passed (3 new: line format for PNG and JPEG, limits, multipart fields).
- `./v3 test FrmClientTest`: 11 passed, including the new end-to-end test (rejected while unapproved, then published after an approval seed; image bytes served).
- Smoke set and the llms.txt tests; full suite and browser tests not run, per `AGENTS.md`.

## Final review

Diff checked for scope: frm.py, its two test files, the agent API reference, llms.txt quickstart text, Claude's skill text, the concept entry, this record and the index.

Commit/merge/publication status: PR open.

Attribution: Claude (claude-opus-5-5), 2026-11-10 UTC.
