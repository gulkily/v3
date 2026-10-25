# Post retraction

Retraction deletes the current canonical post and its detached signature and commits one durable retraction record in the same commit. The record contains target, actor, time, conversation structure, the original signed request/signature, display attribution and the source content hash; it contains no post prose or cleanup progress. Independent replies survive.

Use **… → Retract post** on the post or thread. Authors can retract their own posts; an approved operator can retract eligible posts. Signing, action-time privilege checks and protected identity/approval/invitation restrictions still apply. QDB and Forte display the shared notices.

Acceptance means the repository commit succeeded. Ordinary refresh updates the views, and the existing worker cleans generated artifacts and saved LLM payloads later. Cached pages and saved exchanges may remain readable until successful maintenance; queueing is not completion and five-minute discovery is not a deadline. Retractions do not disable publication or require recurring reconstruction of Git acceptance evidence.

Git history is **not purged in this release**. History purge is a separately planned release. Backups, downloaded copies, provider/remote copies, attachments and independently published replies or quotations are not erased.

## Records and retries

New records live at `records/post-retractions/post-retraction-<post-id>.json`. The signed request is embedded unchanged, so preserving or migrating it does not require a new signature. The local acceptance record is trusted canonical input, not proof supplied by an archive. Imports preserve existing accepted deletions and reject unfamiliar JSON acceptance claims; legacy signed claims still undergo import validation and explicit migration.

The prepare/finalize endpoints retain their signing flow. Finalize returns the accepted JSON `record_path`; `signature_path` is null because the signature is embedded. A repeated prepare request by the recorded actor or author uses the retained record without finding the original post or generating another acceptance commit. An interrupted transaction can retry from that same record. A rejected commit restores the original files.

If the response says **Site update incomplete**, retry retraction or rebuild the read model. If cleanup could not be queued, retry or run maintenance manually. These failures do not undo the committed deletion. Current source routes show a deletion notice; the retained record can be inspected through its current source path. Old archive imports do not restore the deleted post or its signature.

## Upgrade and legacy migration

Deploy compatible application code before migrating. Use the same repository/database settings as the site:

```sh
./v3 retraction-sweep migrate --repository-root=/path/repository
./v3 rebuild /path/repository /path/cache/post_index.sqlite3
./v3 retraction-sweep preview --repository-root=/path/repository
./v3 retraction-sweep run --repository-root=/path/repository
```

`migrate` verifies historical acceptance once, commits each eligible deletion/record conversion separately and reports unresolved items. It can recover structural metadata from historical source without restoring prose into the working tree. Existing trusted `post-removals` records are converted in place. Repeat the command after resolving missing trusted evidence; completed conversions are idempotent. JSON output is available with `--json`; unresolved items return a nonzero exit code.

Unconverted legacy suppression-only records do not globally block indexing or publication. Their content can remain visible until migration succeeds. Do not repair unknown authority by trusting imported claims or inventing signatures. Preserve unresolved records/history for operator review. Schema identity 26 forces the ordinary read model to rebuild on a code-only upgrade.

Rollback must use code that understands the accepted JSON records. Reverting to the old suppression implementation is not a supported rollback and may lose structural placeholders; do not restore deleted prose automatically. This migration does not rewrite Git history.

## Release verification

Inspect the acceptance commit: it should remove the post/signature and add the retained record together. Check the normal thread/post page, independent replies and an old-archive import. Exercise a failed refresh followed by retry. Run the existing queue worker and manual sweep, then verify affected generated artifacts and exchange payloads were retired. Local test evidence does not establish qdb.us deployment or recovery.

See [local cleanup and operator recovery](retraction_sweep.md) and [LLM cleanup](llm_exchange_privacy.md).
