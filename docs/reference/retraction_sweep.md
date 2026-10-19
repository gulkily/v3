# Local retraction sweep

Accepted author/operator retractions immediately queue local cleanup. The
existing task-queue cron worker discovers missed/imported retractions and
rechecks completed coverage every five minutes. No additional cron entry is
needed. Enqueueing does not mean execution has finished; queue backlog can delay it.

```
./v3 retraction-sweep preview
./v3 retraction-sweep run --limit=250
./v3 retraction-sweep status
./v3 task-queue enqueue-retraction-sweep
./v3 task-queue status
```

`preview` changes no storage and reports a bounded sample, not a full inventory
when `remaining` is true. `run` immediately processes a batch without waiting
for cron; repeat while `remaining` is true. The worker continues batches using
the same saved progress. A batch examines up to the limit in exchange rows and
artifact directory entries separately; canonical validation and individual directory
listings are not capped by that row budget. `status` reports pending/complete/failed/idle and the
last run time. Completion describes the last verified scan; later canonical
changes and exchange inserts invalidate it, and periodic reconciliation catches
restored legacy artifacts. `totals` accumulates examined, cleaned, uncertain,
and retired-artifact counts across one scan; top-level counts describe one batch.
An absent exchange store is reported explicitly and is not created.

Options: `--limit=1..10000`, `--repository-root`, `--exchange-database-path`,
`--state-path`, `--static-html-root`. Repository/static defaults honor the usual
environment and active site profile. Exchange location honors private
`LLM_EXCHANGE_DATABASE_PATH`. Public artifacts use `FORUM_PUBLIC_ARTIFACT_ROOT`
or `<application>/public`. Progress defaults to a configuration-specific file
under `<application>/state/private/`; `FORUM_RETRACTION_SWEEP_STATE_PATH` or
`--state-path` overrides it. Use the same configuration for web/worker/manual
runs and a distinct state file for each instance. State includes traversal paths
and identifiers, never removed prose; keep it private.

## Coverage

- Generated HTML in configured presentation roots, including active/inactive
  releases, interrupted candidates, and temporary HTML payloads.
- Served offline snapshot/update databases, manifests, and their temporary copies.
- Recorded LLM request/response/error/context payloads with proven retracted
  dependencies, including context-only dependencies and narrowly classifiable
  historical root-score calls. Arbitrary diagnostic strings are also cleared;
  only identifiers, time/status and dependency evidence remain. Unknown provenance
  stays withheld and unchanged; uncertainty counts do not mean failed cleanup.

Fresh generation-stamped artifacts survive repeat runs. A stale source model or
publication begun before a retraction must refresh/rebuild before publishing;
old unmarked releases require one full static build before shared refresh or
activation. Directory symlinks are rejected, except the managed `current` pointer,
which is unlinked when stale. Explicit artifact roots must be real directories.

Exclusions: canonical records and Git history, analysis/Unicode-risk/Fastmod/media
caches, agent-generation stores, application/task/provider logs, read-model
recovery/candidate copies, backups, attachments, published replies, provider and
remote data, downloaded and browser copies. Read-model refresh remains the
existing retraction flow's responsibility. Signed authority evidence is preserved.
Logical cleanup is not forensic secure erasure.

## Failure and recovery

An enqueue failure keeps accepted retraction and an explicit browser retry.
Worker failures use bounded queue retries and remain visible in queue status;
a terminal failure is not silently re-created every cron tick. Inspect private
state, canonical evidence, root permissions and SQLite contention, then run
manually or explicitly enqueue a retry. No new signature/acceptance commit is
needed. A killed process resumes conservatively; already scrubbed rows are safe
to revisit. Secure deletion clears updated SQLite pages; WAL truncation must
succeed before completion. Long-lived readers can block it: stop/drain those
readers and retry. Do not manually discard SQLite recovery files.

Deploy the web writers/readers and queue worker together, with shared settings
and permissions for private progress, exchanges, artifact roots and publication
locks (`.git/retraction-publication.lock`, or a repository-specific sibling lock
for non-Git repositories). Snapshot assembly (bounded by its existing limits) and shared-release copying
share the publication lock
so interrupted temporary databases cannot reappear after retirement. Rendering
and provider calls stay outside that lock. Pause/drain old workers and publishers
during rollout.
Run preview, immediate run/status, and the installed cron worker against the
intended instance; then build a fresh static release if needed. Before rollback,
pause/drain writers and workers; do not restore diagnostic payload backups or
code that exposes retracted content. Prefer a forward fix preserving evidence.
