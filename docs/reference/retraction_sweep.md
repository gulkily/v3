# Local retraction cleanup

The sweep performs repeatable local cleanup from retained accepted records. There is no separate cleanup ledger, generation stamp, stored traversal cursor or publication gate. Existing task-queue status and retries are the operational record.

```sh
./v3 retraction-sweep preview
./v3 retraction-sweep run --limit=250
./v3 retraction-sweep status
./v3 task-queue enqueue-retraction-sweep
./v3 task-queue status
```

Add `--json` for structured results. Set the normal repository, static/public artifact and exchange database paths for the deployed site. `--limit` controls SQLite in-memory batch size; each invocation traverses the complete store. `preview` and `status` report current inventory without claiming a saved completion checkpoint. `run` reports the work it actually performed. Failure exits nonzero with a retryable error. A successful run removes recognized obsolete sweep-state files; `--state-path` remains an upgrade compatibility option and lock location, not a newly written ledger.

The existing cron worker discovers eligible work every five minutes using its ordinary task history. Concurrent enqueues coalesce. Terminal failures remain visible in task-queue status; use `enqueue-retraction-sweep` to retry. Manual runs do not update the queue's completion history. Backlog, worker limits and SQLite readers may delay cleanup. If the worker cannot finish a full traversal, run the same sweep manually and inspect the reported error; never infer completion from enqueueing.

## Covered data and tradeoffs

- Managed generated HTML/static releases, offline base/update artifacts and high-traffic cached pages are retired using normal file timestamps. Files predating accepted records are eligible immediately. Later files become eligible after five minutes, catching output produced late by stale publishers. This intentionally also expires clean generated copies: it trades cache lifetime for no retraction-specific tracking. Maintain ordinary refresh/publication jobs; static-only installations must rebuild after retirement. Browser/offline clients need a subsequent refresh/update; already downloaded copies are not remotely erased.
- Existing assets, attachments, independent Docs releases and paths reached through directory symlinks are preserved. Retiring an active static pointer allows ordinary dynamic fallback where configured. Use `build-static`/normal offline publication to repopulate generated copies.
- Saved LLM request/response/error/provider metadata is scrubbed for affected dependencies. With accepted retractions present, unknown legacy dependency payloads are conservatively scrubbed too; unrelated historical diagnostic payloads may therefore be lost. Known unrelated exchanges are preserved. Late provider results are readable until a later successful sweep.
- SQLite payload scrubbing uses secure deletion and WAL checkpointing. An active reader can prevent checkpoint completion: let it finish, then retry. An interrupted transaction can safely rerun. This is logical local cleanup, not a forensic-erasure guarantee.

Canonical content deletion belongs to acceptance/migration. Git history purge, backups, remote/provider copies, other logs, attachments and independently published replies/quotes are outside this sweep.

## Recovery on the deployed host

Previous retractions are not migrated. New retractions require their complete text evidence set; see [compatibility and maintenance](author_post_retraction.md#compatibility-and-maintenance). The `migrate` subcommand handles only legacy source-removal receipts.

For the previously reported qdb.us missing-target/cold-page problem, use the host's normal repository/database/public-root settings, run `high-traffic prepare`, then `high-traffic refresh /`, and request the cold page. Rendering uses the ordinary prepared live read model; it does not rebuild or scan the canonical corpus. Missing/malformed legacy retraction evidence alone must not refuse publication. If a renderer times out, its error identifies the limit and last stage; fix that operational condition and retry. Local fixture success does not verify the live host.

After deployment verify normal post/reply pages, an actual worker run, manual cleanup retry, high-traffic cache hit/refresh and offline update consumption. Do not switch back to old code that depends on deleted originals or Git acceptance proofs. No production migration, cache retirement or history rewrite is implied by local tests.
