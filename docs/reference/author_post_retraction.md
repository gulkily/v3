# Post retraction

Retraction deletes the current canonical post and its detached signature and commits a signed text request, detached signature and separate text acceptance receipt together. The receipt contains target, actor, time, conversation structure, display attribution, source content hash and hashes binding the signed evidence; it contains no post prose or cleanup progress. Independent replies survive.

Use **… → Retract post** on the post or thread. Authors can retract their own posts; an approved operator can retract eligible posts. Signing, action-time privilege checks and protected identity/approval/invitation restrictions still apply. QDB and Forte display the shared notices.

Acceptance means the repository commit succeeded. Ordinary refresh updates the views, and the existing worker cleans generated artifacts and saved LLM payloads later. Cached pages and saved exchanges may remain readable until successful maintenance; queueing is not completion and five-minute discovery is not a deadline. Retractions do not disable publication or require recurring reconstruction of Git acceptance evidence.

Git history is **not purged in this release**. History purge is a separately planned release. Backups, downloaded copies, provider/remote copies, attachments and independently published replies or quotations are not erased.

## Records and retries

New requests live at `records/post-retractions/post-retraction-<post-id>.txt`,
with their detached signature at the same path plus `.asc`. The separate
`records/post-retraction-acceptances/post-retraction-<post-id>.txt` receipt uses
readable `Key: value` fields. See the [record contract](../specs/post_retraction_record_v1.md).
Preserve all three files. Local acceptance is trusted canonical input, not proof
supplied by an archive; unfamiliar incoming acceptance claims are rejected.

Prepare/finalize retain their signing flow. Results identify `record_path`
(the signed request), `signature_path` and `acceptance_path`. Repeated prepare by
the recorded actor or author retries from the retained evidence without finding
the original post or generating another acceptance commit. Interrupted writes
can retry; a rejected commit restores the prior files. An exact signed finalize
replay also retries after the prepared token has been removed. A missing or altered
companion is an evidence error, not authority to reconstruct or invent acceptance.

If the response says **Site update incomplete**, retry retraction or rebuild the read model. If cleanup could not be queued, retry or run maintenance manually. These failures do not undo the committed deletion. Current source routes show a deletion notice; the retained record can be inspected through its current source path. Old archive imports do not restore the deleted post or its signature.

## Compatibility and maintenance

Previous retractions are not migrated or supported. Use this format for new
retractions; do not restore deleted prose. Rollback requires code that reads
all three retained files; older JSON-only readers are incompatible.

Run `./v3 rebuild`, then `./v3 retraction-sweep preview` and `run` as needed.
`retraction-sweep migrate` remains only for legacy **source-removal** receipts;
it does not convert or reinterpret old or new retraction requests. This format
change does not rewrite Git history.

## Release verification

Inspect the acceptance commit: it should remove the post/signature and add all three retained files together. Check the normal thread/post page, independent replies and an old-archive import. Exercise a failed refresh followed by retry. Run the existing queue worker and manual sweep, then verify affected generated artifacts and exchange payloads were retired. Local test evidence does not establish qdb.us deployment or recovery.

See [local cleanup and operator recovery](retraction_sweep.md) and [LLM cleanup](llm_exchange_privacy.md).
