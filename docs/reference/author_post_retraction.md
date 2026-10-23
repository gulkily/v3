# Post retraction: complete workflow

This is the end-to-end guide for author/operator retraction: signed acceptance,
served-content suppression, local cleanup, verification, and recovery. The
[sweep reference](retraction_sweep.md) and [LLM privacy reference](llm_exchange_privacy.md)
provide additional implementation and operational detail.

An author can retract their own post; an approved operator can retract another
user's supported post through the same signed flow. Open **… → Retract post**
on a supported thread-root card, reply card, or individual post page. The
ellipsis control has the accessible label More actions. QDB quote cards and
Forte paned views display retraction notices but have no retraction control.

The server stops showing the post in served content and keeps a small signed
record so ordinary reimports do not resurrect it. Retraction combines served-content
suppression with cleanup of covered local copies; it does not erase every copy.
The shell [`delete-record`](v3_cli.md#delete-a-canonical-record) command
still physically removes a canonical file and is a separate operation.

## Workflow at a glance

1. Open **… → Retract post** and confirm. The browser prepares a canonical
   retraction record, signs it with the acting identity, and submits it.
2. The server validates the signature and current authority under the write
   lock, then commits the record and detached signature. Operator acceptance
   retains the trusted history needed to prove authority.
3. The server refreshes the read model and retires generated presentation and
   snapshot copies. Original source URLs and affected LLM exchanges are withheld.
   A refresh failure leaves the accepted action in place with a partial result.
4. Acceptance queues a local sweep. The browser acknowledges **Local cleanup
   queued.** Queueing is separate from completion; the browser can be closed.
5. The existing cron worker runs bounded sweep batches. It discovers missed or
   imported work and reconciles completed coverage every five minutes. Operators
   can preview, run immediately, inspect status, and retry through the CLI.
6. Verify both served-content suppression and sweep completion. Completion applies
   to the covered local stores at the last verified scan, not to every copy ever
   made of the post.

## What retraction does

- The post's subject, body, and event fields are not shown in pages, search,
  feeds, `/api/*`, activity, profile pages, the offline snapshot, or the
  downloadable read-model database.
- Replies stay in place under an attributed retraction placeholder; a retracted
  thread root keeps its row with the title `Deleted post`.
- `/source/current/...` and `/source/blob/<commit>/...` answer `410` for the
  retracted post's record file.
- Recorded LLM exchanges containing the post as input or context are withheld
  from links, lists, and direct URLs; uncertain historical exchanges are also
  withheld. See [LLM exchange privacy](llm_exchange_privacy.md).
- Generated static artifacts and served snapshot copies are retired so they are
  rebuilt without the post. The sweep removes stale retained releases/candidates
  and scrubs proven-affected LLM payloads from local diagnostic storage.

## Source-owned thread labels

Retracting a post withdraws thread labels generated from that post when ownership
is proven. A label supplied independently by another surviving post, a button or
an operator can remain. Label-derived scores, vote totals, activity and ordering
are recomputed; independent replies remain readable. New hashtag-generated label
records carry the source post ID and exact content hash without modifying signed
post bytes.

For ambiguous legacy labels, the retraction result reports
`label_cleanup_status=unresolved` and `unresolved_label_records`. The browser shows
an operator-review message after the normal reload. Retraction remains accepted;
repeating it does not establish missing historical ownership. An operator uses
[read-only source inspection and explicit label resolution](v3_cli.md#source-post-deletion-and-label-repair).
Physical source deletion uses the same label-ownership policy and preserves
conversation placeholders. This release does not claim to reverse every other
generated-effect family or recall externally held copies.

## What retraction does not do

- It does not delete the original canonical post file or remove it from Git history. Anyone with a clone,
  and the whole-checkout downloads at `/downloads/repository.tar.gz` and
  `/downloads/repository.zip`, still has it.
- It does not reach backups, other servers, or copies other people saved.
- Canonical/history and backup purge remain separate. Managed artifact and LLM
  payload cleanup uses the [local retraction sweep](retraction_sweep.md).

The completion text explains these limits; confirmation asks “Retract this post?”.
The wording lives in
`src/ForumRewrite/Support/RetractedPostNotice.php`.

## Local cleanup: automatic or immediate

Keep the existing single task-queue worker cron entry; no separate sweep cron
is required. If the worker is not installed, `./v3 task-queue cron` prints its
crontab line. Install it under the intended worker identity with the same instance
configuration as the web application. The worker normally claims one queued task
per invocation; other jobs can delay cleanup.

```bash
# Inspect a bounded sample without modifying storage.
./v3 retraction-sweep preview

# Execute a batch immediately, without waiting for cron.
./v3 retraction-sweep run --limit=250

# Inspect current progress and the last verified scan.
./v3 retraction-sweep status

# Explicitly enqueue or retry cleanup through the worker.
./v3 task-queue enqueue-retraction-sweep
./v3 task-queue status
```

Commands print readable summaries; add `--json` for structured fields. Repeat
`run` while **More work: yes**, or let the worker continue saved progress. A batch
examines up to the limit in exchange rows and artifact directory entries
separately; canonical validation and directory listings are not capped by that
budget. Preview is a sample when more work remains.

| Sweep status | Meaning | Next action |
| --- | --- | --- |
| `idle` | No retractions require an initial scan. | No action. |
| `pending` | Work remains or newer canonical/exchange state invalidated completion. | Run another batch or wait for the worker. |
| `complete` | The last scan finished for covered stores. | Verify suppression and review retained/excluded copies. |
| `failed` | Cleanup could not finish or its evidence is unavailable. | Repair the reported condition and run or enqueue a retry. |

Counts describe exchanges examined/cleaned, uncertain exchanges retained, and
artifacts retired. JSON `totals` accumulates a scan across batches; top-level
counts describe a single batch. Uncertain exchanges are withheld and retained,
so an uncertainty count does not by itself mean cleanup failed. An absent
exchange store is reported and is not created by the sweep.

## Coverage and retained copies

| Store | Result |
| --- | --- |
| Normal pages, search, feeds, activity, profiles, APIs, current read model and served offline content | Retracted subject/body/event fields are suppressed; replies remain. |
| Original current/historical source URLs | HTTP `410` with the attributed retraction notice. |
| Generated HTML, active/inactive static releases, build candidates, offline snapshot/update databases, manifests and temporary payload copies in configured roots | Stale copies are retired; fresh generation-stamped output survives repeat sweeps. |
| LLM exchanges with proven retracted dependencies | Withheld from inspection; request/response/error/context payloads and arbitrary diagnostic strings are scrubbed. Minimal non-content status/provenance remains. |
| LLM exchanges with uncertain dependencies | Withheld but retained; reported for policy review. |
| Proven unaffected LLM exchanges | Remain available and unchanged. |
| Canonical files, Git history, whole-checkout downloads and backups | Retained; not purged by retraction or the sweep. |
| Analysis/Unicode-risk/Fastmod/media caches, agent-generation stores, application/task/provider logs, read-model recovery/candidate copies | Outside sweep coverage. |
| Attachments, published replies/quotes in other posts, remote/provider data, downloaded or browser copies | Not automatically removed. |

LLM visibility applies equally to authors and operators. Affected/uncertain
exchanges disappear from links and lists, and direct URLs return `404` without
payloads. Exchange pages use `Cache-Control: private, no-store`, with no static
or offline fallback. New calls track the post IDs actually supplied to the model,
including context and derived-summary dependencies. Complete provenance is
required; only narrowly recognizable historical root-only scoring calls can be
classified without the newer manifest. A primary-post association or text search
alone is not proof. Late proven-affected results are scrubbed at recording;
new exchange inserts invalidate the previous sweep completion for reconciliation.

The sweep never rewrites canonical history or deletes signing/authority evidence.
Logical removal from covered storage is not a forensic secure-erasure guarantee.

## Verification and recovery

After retraction, check the ordinary post/thread view for an attributed notice
and verify that unrelated replies remain. Original source URLs should return
`410`; affected LLM exchange URLs should return `404`. Inspect sweep and queue
status to distinguish accepted retraction, pending cleanup, and a verified scan.
Do not infer global erasure from the absence of content in a page or search result.

| Condition | Recovery |
| --- | --- |
| **Retracted. Site update incomplete.** | Use **Retry retraction**. It repeats artifact retirement and a stale-model refresh without another signature or acceptance commit. |
| **Retracted. Cleanup could not be queued.** | Use the visible retry control, or have the operator run/enqueue cleanup. Accepted retraction stays in place. |
| Worker unavailable, delayed, or enqueue missed | Inspect `task-queue status`; run immediately if needed. Recurring discovery catches missed/imported work. |
| Sweep fails or exhausts queue retries | Inspect sweep/queue status, canonical evidence, permissions and SQLite readers. Repair the cause, then run or explicitly enqueue a retry. Terminal failures are not silently re-created every cron tick. |
| Process interrupted during cleanup | Retry normally; saved progress and repeat-safe cleanup recover conservatively. |
| SQLite checkpoint blocked by readers | Stop/drain long-lived readers and retry. Completion requires successful WAL retirement; do not discard SQLite recovery files manually. |
| Stale/unmarked static release rejected | Refresh/rebuild from a current read model. Old unmarked releases require a full static build before shared refresh or activation. |
| Directory symlink or invalid artifact traversal | Correct the configured roots/layout, then retry. Explicit roots must be real directories; only the managed `current` pointer is retired as a symlink. |
| Canonical evidence or trusted operator history missing/malformed | Restore valid records, signatures, signing keys and trusted history before serving restored content. Do not delete evidence to make cleanup pass. |

Exchange inspection fails closed when canonical state cannot be validated.
Canonical retraction validation still needs the original post and, for operator
actions, locally trusted acceptance history. A files-only restore cannot prove
operator authority; losing that evidence can prevent suppression from taking
effect. Restore the trusted repository before exposing restored content.

## Instance configuration and deployment

Use the same repository, exchange store, presentation roots and private progress
location for web, worker and manual commands. Repository/static defaults honor
normal environment settings and the active site profile. Exchange storage uses
private `LLM_EXCHANGE_DATABASE_PATH`; public artifacts use
`FORUM_PUBLIC_ARTIFACT_ROOT` or `<application>/public`.

Sweep options are `--repository-root`, `--static-html-root`,
`--exchange-database-path`, `--state-path`, `--limit=1..10000`, and `--json`.
Progress defaults to a configuration-specific file under
`<application>/state/private/`; `FORUM_RETRACTION_SWEEP_STATE_PATH` overrides it.
Keep progress private and use a distinct state file per instance.

Deploy web writers/readers and workers together, pausing/draining older writers,
workers and publishers during rollout. Ensure access to the configured stores,
private progress and publication locks (`.git/retraction-publication.lock`, or a
repository-specific sibling lock for a non-Git canonical repository). Validate
startup read-model freshness, suppression, immediate cleanup and the installed
cron command against the intended instance; build a fresh static release if needed.

Publication checks prevent stale builds from becoming active after retraction.
Snapshot assembly and shared-release copying share the publication lock with
retirement; rendering and provider calls stay outside that lock. The sweep is
not a replacement for ordinary read-model refresh/rebuild.

For rollback, pause/drain writers and workers first. Preserve signed evidence,
trusted history and signing keys; avoid restoring payload backups or readers
that ignore retractions. Prefer a forward fix. Production rollout and restoration
must be verified on the actual target instance.

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
| `/api/create_prepared_retraction` | `prepare_token`, `post_id`, `record_path`, `actor_identity_id`, `canonical_record`, `detached_signature` | `retraction_status`, `cleanup_status`, `failed_step`, `commit_sha` |

- `actor_identity_id` identifies the signer in both requests. The existing
  `author_identity_id` request parameter remains a backward-compatible alias
  for the actor; canonical Author-Identity-ID still names the target author.
- `cleanup_status` is `pending` after queueing, or `failed` if queueing failed;
  it is separate from signed acceptance. Retry without signing again. Operators
  inspect current cleanup through `./v3 retraction-sweep status`.
- `retraction_status` is `complete`, or `partial` when the record is committed but
  a refresh failed; `failed_step` is `read_model_refresh` or `artifact_invalidate`.
- To recover a partial result, call `/api/prepare_retraction` again. For an
  already-retracted post it needs no new signature and retries the refresh.
- Errors: neither author nor eligible operator, post not found, protected post, invalid
  signature, expired prepare token.

## Operator notes

- The read model retains retraction time, class and attribution on `posts`
  and `threads`. Startup checks schema identity and rebuilds incompatible
  disposable models; the current version is defined in
  [`ReadModelMetadata`](../../src/ForumRewrite/ReadModel/ReadModelMetadata.php),
  with the schema documented in [the read-model specification](../specs/read_model_schema_v1.md).
- Rebuilds run with `PRAGMA secure_delete = ON` so the downloadable database does
  not keep retracted prose in freed pages.
- Operator scripts that read canonical files directly (`archive_thread`,
  `thread_attributes`, `delete-record`) are unchanged and can still see the
  original file.
## Local verification

```bash
php tests/run.php RetractionSweepTest ExchangeDependenciesTest SqliteLlmExchangeStoreTest LlmExchangeRecorderTest TaskQueueWorkerTest PostRetractionScriptTest
php tests/run.php
node tests/browser/post_retraction.mjs
node tests/browser/post_retraction_menu.mjs
node tests/browser/llm_exchange_privacy.mjs
```

Browser checks use Chromium from `CHROMIUM_PATH` or `/opt/google/chrome/chrome`
with Playwright Core. Coverage includes signed author/operator actions, partial
refresh/enqueue recovery, CLI preview/run/status, actual generated cron execution,
late exchanges, interrupted transactions, SQLite reader contention, publication
locking and stale/symlink artifact handling. Local results do not establish
production deployment or backup/history erasure.
