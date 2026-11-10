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

Application change to the portable client. `thread` and `reply` take `--image FILE` and `--image-description TEXT` and publish one image with the post, prepared the way the browser composer prepares it, because the server only validates.

Excluded: more than one image per post (the server allows one); images in `create_signed_post`, which does not accept files.

## Approach and risks

- The server takes the file only on `/api/create_prepared_post` as a multipart `image` field, bound to an `Image-v1:` line in the signed body. A write with `--image` therefore uses the prepared flow even where the single-request flow is available.
- The browser composer (`image_prepare.js`) resizes and re-encodes every image before upload; the server only checks it (`ImageUploadValidator`: PNG or JPEG, at most 2048 pixels per side; `ImageReference`: 5 MiB). frm.py does the same preparation in two ways, both requested by the maintainer:
  - With Pillow installed: the browser's steps and limits. Source at most 32 MiB, 16384 pixels per side and 40 megapixels; EXIF orientation applied; first frame; long side at most 2048 (`int`, like the browser's `floor`); JPEG sources stay JPEG at quality 85, anything else (PNG, WebP, GIF, BMP) becomes PNG, alpha kept; ratio times 0.8 up to eight times until the result fits 5 MiB. Re-encoding drops all metadata. Resampling is Lanczos rather than the canvas's, so pixels differ slightly from a browser upload.
  - Without Pillow (frm.py stays standard-library only): an already-prepared PNG or JPEG within the upload limits is sent with its pixels untouched and its metadata removed (PNG `tEXt`, `zTXt`, `iTXt`, `eXIf`, `tIME`; JPEG comments and APP1 to APP15 except Adobe APP14). Refused, with a message naming Pillow as the fix: other formats, larger images, animated PNGs, and JPEGs whose EXIF orientation isn't upright.
- frm.py builds the `Image-v1:` line from the prepared bytes (SHA-256, type, bytes, width, height, description percent-encoded like the browser's `encodeURIComponent` plus `!'()*`) and prints an `Image:` line with the result.
- Checked before any request: description at most 500 bytes with no control characters, no existing `Image-v1:` line in the body.
- Resume: the operation keeps the prepared bytes and the line in `operation.txt`, so a resumed write sends the bytes that were signed, even if the original file changed or Pillow's output would differ.
- Multipart through curl: field values go through temporary files (`name=<file`), so authored text starting with `@` or `<` is never read as a path.
- Unapproved keys: the server refuses at prepare; frm.py reports `Rejected:` and keeps the draft.

## Reuse decisions

- Server contract: `ImageReference` line format, `ImageUploadValidator` limits, `WritePostAndIdentityApiController::createPreparedPost` file handling, `browser_signing.js` description encoding, `image_prepare.js` preparation steps and limits. No server change.
- Client: existing `prepared_post`, `submit`, `api` retries and `request`; `request` gains an optional `files` argument rather than a second HTTP path.
- Tests: `SingleRequestHttpTest::server`, `ImageContentTest::png`, the approval-seed pattern from the browser fixtures.

## Completion conditions

- Normal entry: `python3 frm.py --site … reply --thread … --body-file post.txt --image shot.png --image-description "…"`.
- End-to-end: the post carries the signed `Image-v1:` line and `/images/<sha256>.png` serves the same bytes.
- Recovery: an unapproved key is rejected with nothing published; a body that already has a reference is refused before any request; without Pillow, an image that needs preparing is refused before any request with the fix named.
- Release: PR merged and deployed by the maintainer, so `/frm.py` serves the new version.

## Dated execution history

- 2026-11-10: first version uploaded the original file and checked 4096 pixels per side (the reference-format ceiling). The maintainer asked whether the browser's client-side resize and compression were accounted for; they weren't, and the server's upload limit is 2048. Asked to support both, frm.py now prepares with Pillow when installed and otherwise sends only already-prepared PNG/JPEG with metadata removed.

## Concepts touched

[Agent posting client](../../concepts/agent_posting_client.md): changed (image attachments).

## Verification

- `python3 -I tests/frm_client_test.py`: 26 passed (9 new: reference format; without Pillow, PNG and JPEG metadata removal with pixels kept, rotated JPEG refused, limits; with Pillow, 3000 px PNG to 2048, rotated JPEG turned upright without EXIF, WebP to PNG with alpha, stepwise shrink under the byte limit; multipart fields).
- `./v3 test FrmClientTest LlmsTxtRendererTest`: 23 passed. The new end-to-end test: rejected while unapproved; after an approval seed, published without Pillow (`python3 -S`) with the bytes served back unchanged; a 2500 x 1200 PNG published with Pillow and stored at 2048 x 983.
- Smoke set and the llms.txt tests; full suite and browser tests not run, per `AGENTS.md`.

## Final review

Diff checked for scope: frm.py, its two test files, the agent API reference, llms.txt quickstart text, Claude's skill text, the concept entry, this record and the index.

Commit/merge/publication status: PR open.

Attribution: Claude (claude-opus-5-5), 2026-11-10 UTC.
