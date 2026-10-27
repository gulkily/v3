# Archive export recovery

Scope: `/downloads/repository.tar.gz`, `/downloads/repository.zip`, `/downloads/images.tar`
Storage: `archive-exports/` beside the configured read-model database; private directory, outside the content repository and public roots
Coordination: Existing `image-export.lock` and `forum-rewrite.lock` beside that database
Limits: One builder; five-second aggregate lock-wait deadline; six retained entries and 2 GiB including protected entries and reserved build space
Reservation: Twice source bytes plus 64 MiB; filesystem availability is checked before snapshot creation
Dependencies: Existing PHP, Git, tar and zip; no new daemon, package or configuration setting

## Visitor and import recovery

- HTTP 503: Another backup is being prepared. Retry after the `Retry-After: 5` interval. The same direct URL or Backup link is safe to use again. Repeated requests do not force a second compatible build.
- HTTP 409: Refresh Backup and download the repository and image artifacts for the current revision again. An old requested revision is never silently replaced or served from an obsolete cache entry.
- HTTP 500: Ask the operator to check export logs, source integrity and archive storage, repair the cause, then retry. The browser receives no internal exception path; the server error log retains the cause under `Archive export failed:`. The instance importer reports this source error and can be rerun; a failed download is not a resumable import workspace.

Archives preserve their existing contents, revision-based filenames and pairing rules. They are public-content snapshots, not full machine backups. Already admitted transfers keep their snapshot; a later content change prevents new admission of the old archive. Copies already downloaded cannot be recalled.

## Diagnose persistent contention on qdb.us

Use the host's actual configured database directory; the reported instance uses `/home/qdb/v3/state/cache`.

```bash
sudo lslocks -o PID,COMMAND,TYPE,MODE,PATH
sudo lsof /home/qdb/v3/state/cache/image-export.lock
sudo lsof /home/qdb/v3/state/cache/forum-rewrite.lock
df -h /home/qdb/v3/state/cache
df -i /home/qdb/v3/state/cache
du -sh /home/qdb/v3/state/cache/archive-exports
```

Inspect the reported PID and its children with `ps -p <PID> -o pid,ppid,etime,%cpu,%mem,stat,wchan:32,args` and `pstree -ap <PID>` when those tools are installed. Check the PHP/web-service error log for `Archive export failed:` and request logs for duration/status. Active tar/zip work can legitimately outlast the five-second wait; that deadline limits lock waiting, not build CPU time. A completed compatible archive can be served while another format is compressing, provided source validation can acquire the writer lock.

Do not remove either lock file: file existence is not evidence of ownership, and unlinking a held lock breaks mutual exclusion. Do not automate worker termination. If work is progressing, let it finish and retry. If a holder is confirmed stuck, use the site's established service recovery procedure after considering its other requests and child processes. An abandoned builder releases its lock when all owning processes exit; the next cache miss cleans its private staging directory before rebuilding.

Storage failures: Let active downloads finish, or free unrelated expendable disk space. Cleanup skips reader-locked archives, counts them against capacity, and refuses a new build when it cannot reserve space. Source-integrity failures require repairing the logged record/image problem, not bypassing validation. Warm requests still validate source bytes, permissions, image inventory, generator version and the cached archive checksum; they avoid snapshot copying and compression, not all filesystem reads.

## Verify the deployed outcome

After deploying through the normal release process, run two overlapping requests from a separate shell, saving each response and headers separately:

```bash
curl --fail --show-error --dump-header /tmp/qdb-a.headers --output /tmp/qdb-a.tar.gz https://qdb.us/downloads/repository.tar.gz &
curl --fail --show-error --dump-header /tmp/qdb-b.headers --output /tmp/qdb-b.tar.gz https://qdb.us/downloads/repository.tar.gz &
wait
```

Inspect both header files: a cold build longer than five seconds may legitimately give one 503; retry that request after the indicated interval. Each eventual 200 must extract/list successfully with `tar -tzf`. Repeat the download and confirm the completed cache artifact's inode/modification time is unchanged and no new compressor runs. Without a source change, compare the returned archives with `cmp`. Verify ZIP and image downloads from Backup, including matching revisions when images are available. In the browser, confirm the busy response wraps on a phone and retry succeeds; in import use, rerun the original import command after a busy source clears.

Record host revision, lock holder/duration, cold/warm responses, extraction and retry outcome. Local fixtures and synthetic benchmarks do not establish qdb.us recovery. If contention persists, retain the process evidence rather than repeatedly deleting cache files.

## Cleanup and rollback

Cache entries are disposable derived data. Ordinary cleanup happens on cache misses; an active reader can retain an obsolete entry until a later miss. `.build-*` and `.publish-*` directories are reclaimed only while owning the global builder lock. Do not manually remove live entries or staging directories.

For manual cache removal, first quiesce export requests and confirm no builder, child compressor or admitted download remains; remove only `archive-exports/`, preserving both coordination lock files and the database. The next request recreates the cache. Pre-upgrade `/tmp/forum-image-export-*` files are not owned by this cleanup: inspect old workers before any separate operator cleanup. Reverting the application does not require canonical data or database migration; quiesce downloads before rollback and separately reclaim the unused private cache under the same rule.
