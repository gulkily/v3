# Author post retraction

An author can retract their own post. The server then stops showing it
everywhere it serves content and keeps a small signed record so an import or
restore cannot bring it back. This is **suppression**, not erasure.

Operators removing content use [`delete-record`](v3_cli.md#delete-a-canonical-record)
instead; the two are separate mechanisms.

## What retraction does

- The post's subject, body, and event fields are not shown in pages, search,
  feeds, `/api/*`, activity, profile pages, the offline snapshot, or the
  downloadable read-model database.
- Replies stay in place under a "deleted by its author" placeholder; a retracted
  thread root keeps its row with the title `Deleted post`.
- `/source/current/...` and `/source/blob/<commit>/...` answer `410` for the
  retracted post's record file.
- Generated static artifacts and served snapshot copies are retired so they are
  rebuilt without the post.

## What retraction does not do

- It does not remove the original record from Git history. Anyone with a clone,
  and the whole-checkout downloads at `/downloads/repository.tar.gz` and
  `/downloads/repository.zip`, still has it.
- It does not reach backups, other servers, or copies other people saved.
- Physical purge of history, artifacts, and backups is a later slice under the
  same record.

The site's confirmation and completion text states this; the wording lives in
`src/ForumRewrite/Support/RetractedPostNotice.php`.

## Record format

`records/post-retractions/post-retraction-<post-id>.txt`, with a detached
signature beside it as `<record>.asc`:

```
Record-ID: post-retraction-<post-id>
Created-At: 2026-10-13T12:00:00Z
Post-ID: <post-id>
Operation: retract
Author-Identity-ID: openpgp:<40 lowercase hex>

```

- The body must be empty; no prose is kept.
- The record id is derived from the post id, so there is at most one per post and
  repeating a retraction is idempotent.
- A retraction takes effect only when `Author-Identity-ID` equals the post's own
  author. Identity, approval, and invitation posts are never suppressed.
- Re-importing an archive carries the record, and suppression is applied when the
  read model is built, so restoring the original post file does not resurrect it.

## Write API

Both endpoints are `POST` and follow the prepare/sign/finalize pattern used for
posts.

| Endpoint | Input | Result |
| --- | --- | --- |
| `/api/prepare_retraction` | `post_id`, `author_identity_id` | `prepare_token`, `canonical_record`, `record_path`, `already_retracted` |
| `/api/create_prepared_retraction` | `prepare_token`, `post_id`, `record_path`, `author_identity_id`, `canonical_record`, `detached_signature` | `retraction_status`, `failed_step`, `commit_sha` |

- `retraction_status` is `complete`, or `partial` when the record is committed but
  a refresh failed; `failed_step` is `read_model_refresh` or `artifact_invalidate`.
- To recover a partial result, call `/api/prepare_retraction` again. For an
  already-retracted post it needs no new signature and retries the refresh.
- Errors: not the post's author, post not found, authority post, invalid
  signature, expired prepare token.

## Operator notes

- The read model gains a nullable `retracted_at` column on `posts` and `threads`
  (schema version 16), so existing databases rebuild once after deploy.
- Rebuilds run with `PRAGMA secure_delete = ON` so the downloadable database does
  not keep retracted prose in freed pages.
- Operator scripts that read canonical files directly (`archive_thread`,
  `thread_attributes`, `delete-record`) are unchanged and can still see the
  original file.
