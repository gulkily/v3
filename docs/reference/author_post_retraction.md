# Author and operator post retraction

An author can retract their own post; an approved operator can retract another
user's supported post through the same signed flow. Open **… → Retract post**
on a supported thread-root card, reply card, or individual post page. The
ellipsis control has the accessible label More actions. QDB quote cards and
Forte paned views display retraction notices but have no retraction control.

The server stops showing the post in served content and keeps a small signed
record so ordinary reimports do not resurrect it. This is **suppression**, not
erasure. The shell [`delete-record`](v3_cli.md#delete-a-canonical-record) command
still physically removes a canonical file and is a separate operation.

## What retraction does

- The post's subject, body, and event fields are not shown in pages, search,
  feeds, `/api/*`, activity, profile pages, the offline snapshot, or the
  downloadable read-model database.
- Replies stay in place under an attributed retraction placeholder; a retracted
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

The completion text explains these limits; confirmation asks “Retract this post?”.
The wording lives in
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
- `Author-Identity-ID` always names the target post's author. New operator
  records additionally contain `Actor-Identity-ID` (the operator's own identity)
  and `Retraction-Class: operator`. The operator signs with their own key.
  Legacy author records without these headers retain author classification.
- Only supported posts with a signing author can be retracted. Identity,
  approval, and invitation posts remain protected for both roles.
- New submissions require verified signatures. Supplied invalid signatures
  are rejected during loading; legacy unsigned local author records remain
  readable for compatibility. Imported retractions require verified signatures.
- Re-importing an archive carries the record, and suppression is applied when the
  read model is built, so restoring the original post file does not resurrect it.

## Operator authority and audit evidence

The operator must be approved and have an existing operator approval seed,
committed in the canonical repository. Authority is rechecked under the write
lock when the signed action is accepted. Ordinary cross-user requests and a
privilege removal between prepare and finalize are denied without a commit.

An accepted operator action is backed by its exact record bytes in a locally
reachable addition commit labeled `Retract post <post-id> by <actor-identity>`
and the matching regular-file operator seed in that commit's sole parent.
The actor's signature is verified separately. Removing the current seed blocks
new actions but does not invalidate an already accepted action or its retry.

Activity's **Commits** view exposes the acceptance commit, retraction record,
signature, and actual signing identity/key. There is no separate retraction
entry in the regular activity feed. Source access to the retraction evidence
remains available; access to the original post record returns an attributed
410 notice, including for historical source URLs.

Imported operator actions cannot establish authority from the source archive's
history or claims. A record must already have matching acceptance evidence in
the destination's trusted history; otherwise the import is rejected. Accepted
local evidence remains effective when the original post is reimported, even
after the actor loses operator privilege.

## Write API

Both endpoints are `POST` and follow the prepare/sign/finalize pattern used for
posts.

| Endpoint | Input | Result |
| --- | --- | --- |
| `/api/prepare_retraction` | `post_id`, `actor_identity_id` | `prepare_token`, `canonical_record`, `record_path`, `already_retracted` |
| `/api/create_prepared_retraction` | `prepare_token`, `post_id`, `record_path`, `actor_identity_id`, `canonical_record`, `detached_signature` | `retraction_status`, `failed_step`, `commit_sha` |

- `actor_identity_id` identifies the signer in both requests. The existing
  `author_identity_id` request parameter remains a backward-compatible alias
  for the actor; canonical Author-Identity-ID still names the target author.
- `retraction_status` is `complete`, or `partial` when the record is committed but
  a refresh failed; `failed_step` is `read_model_refresh` or `artifact_invalidate`.
- To recover a partial result, call `/api/prepare_retraction` again. For an
  already-retracted post it needs no new signature and retries the refresh.
- Errors: neither author nor eligible operator, post not found, protected post, invalid
  signature, expired prepare token.

## Operator notes

- The read model retains nullable `retracted_at` and adds nullable
  `retraction_class` on `posts` and `threads` (schema version 18), so
  existing databases rebuild once after deploy.
- Rebuilds run with `PRAGMA secure_delete = ON` so the downloadable database does
  not keep retracted prose in freed pages.
- Operator scripts that read canonical files directly (`archive_thread`,
  `thread_attributes`, `delete-record`) are unchanged and can still see the
  original file.
- Preserve the canonical repository's Git history and signing public keys during
  backup/restore. Missing/pruned operator acceptance history fails closed;
  a files-only archive cannot prove historical operator authority. This may
  leave content visible if accepted evidence is lost, so restore the trusted
  repository history before serving restored content.
- Deploy code and schema together. Validate startup rebuild and retraction
  suppression on a restored repository. Do not roll back to code that ignores
  operator records; use a forward fix preserving signed evidence and history.
- A partial result keeps visible status and a Retry retraction control outside
  the closed menu. Retry repeats artifact retirement as well as a stale-model
  refresh, without a new signature or acceptance commit.

Local verification: `php tests/run.php`,
`node tests/browser/post_retraction_menu.mjs`, and
`node tests/browser/post_retraction.mjs`. Browser checks use Chromium from
`CHROMIUM_PATH` or `/opt/google/chrome/chrome` with Playwright Core.
