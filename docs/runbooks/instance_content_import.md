# Import content from another instance

Use `./v3 import-instance` to download a public instance's repository archive,
merge its supported forum content into this instance, and refresh the local
read model, static pages, and offline snapshots. This is an on-demand command;
there is no schedule or web UI in this release.

## Run an import

Run from the application checkout as the operator who owns the destination
repository and can publish its generated files. Use the same `FORUM_SITE_ID`,
repository, database, and static-root configuration as the running instance.
The destination must already be initialized, have a Git commit, and have no
unrelated pending changes. Git author name/email must be configured.

```bash
./v3 import-instance https://forum.example --dry-run
./v3 import-instance https://forum.example
```

A hostname defaults to HTTPS. An explicit `http://` URL is supported for a
source that uses HTTP, including local instances. The URL is the instance's
base, including a deployment subdirectory when applicable; the command adds
`/downloads/repository.tar.gz`. Do not pass a Backup page or archive URL.
TLS certificate checks remain enabled. Sources requiring login or credentials
are not supported.

For a short name, create a JSON object mapping names to base URLs:

```json
{"community": "https://forum.example", "local": "http://localhost:8000"}
```

```bash
./v3 import-instance community --sources=/private/instances.json --dry-run
./v3 import-instance community --sources=/private/instances.json
```

Unknown names fail with guidance; presentation names such as `chouse` do not
implicitly identify a remote server. Explicit destination overrides are:

```bash
./v3 import-instance https://forum.example \
  --repository-root=/srv/forum-content \
  --database-path=/srv/forum-state/index.sqlite3 \
  --static-html-root=/srv/forum-state/static_html
```

The defaults are the existing `FORUM_REPOSITORY_ROOT`, `FORUM_DATABASE_PATH`,
and `FORUM_STATIC_HTML_ROOT` environment settings, or the application's local
repository/cache and active profile's static root. The command prints resolved
locations. It does not initialize an absent destination. Target the live
instance's database so imports and ordinary writes share the same lock.
Publication covers the selected site profile/static root.

## Coverage and results

The merge includes public threads, replies, quotes, labels/tags, subject
changes, reactions, identity bootstrap records, public keys, and detached
signatures. IDs, authored timestamps, record bytes, and relationships are
preserved. Local visibility and scoring rules still apply: a source author's
approval on another instance does not grant approval here, and default board
views may filter unliked content. Check a thread or its ordinary tag page when
verifying an imported item. Offline snapshots keep their existing bounded
public-content selection.

The following are explicitly excluded: instance configuration/feature flags,
approval seeds, approval/invitation actions (including actions stored as posts),
private data, source Git history, and derived databases. Records from unsupported
families, invalid records, and missing/rejected dependencies are reported.
Legacy posts without explicit `Created-At` are reported instead of having their
signed bytes changed or their creation time inferred from foreign history.
Detached signatures are copied with their records; import is not a new signed
submission and does not independently authenticate a source's authorship claims.
When a duplicate record exists at another path and a new signature cannot be
associated safely, the group is reported for manual review.

Identical records are skipped. Different bytes at the same path or canonical
identity become conflicts; the local version stays in place. Source deletions
never remove local data. Related records/signatures with rejected dependencies
are withheld together. The existing `import-repository` command remains
available for local archives with its previous options and policy.

Preview downloads and validates the archive and reports the proposed merge. It
may create temporary files and lock files but does not change destination
records, commits, the read model, or published views. Actual runs list category
counts and every excluded/unsupported/invalid/conflicting record. Private review
reports and conflict copies live below the printed
`<repository>/.git/instance-import/<run-id>/` directory.

| Exit code | Meaning |
| --- | --- |
| 0 | Complete supported-content merge or preview; intentional exclusions are listed. |
| 2 | Partial result: conflicts, invalid records, or unsupported records need review. Accepted content is published on an actual run. |
| 1 | Failure: use the error and recovery guidance; publication may be unfinished. |

## Recovery

Imports serialize with normal writes and keep a private journal before changing
content. A failure can leave import-owned files or a committed import awaiting
publication. Preserve the journal and its saved payloads. Correct the reported
cause, then run:

```bash
./v3 import-instance --resume \
  --repository-root=/srv/forum-content \
  --database-path=/srv/forum-state/index.sqlite3 \
  --static-html-root=/srv/forum-state/static_html
```

Use the same destination paths and `FORUM_SITE_ID` as the failed run. Resume
uses the saved source payload, so it does not need the remote source or alias
file. It verifies import-owned bytes, recognizes an already-created import
commit, and rebuilds/publishes even when there are no new records. A new source
import is refused while a recovery journal is pending.

If unrelated pending edits or divergent import-owned content are detected,
automatic recovery stops. Inspect `pending.json`, the saved payload/report, and
Git status; preserve local work before resolving the mismatch. Do not delete the
journal, discard the repository, or reset it wholesale to force a retry. An
unrecoverable divergence requires operator review of the recorded base commit
and owned files. The generic [operator recovery guide](operator_recovery.md)
covers database sidecars and publication failures.

Public destinations publish complete static releases plus the standalone
snapshot/update files served ahead of release snapshots. Private destinations
refresh the read model and retain their existing access gates without publishing
public static/offline artifacts. Resume is retry-based recovery, not rollback of
a committed import. Old run directories retain review data; remove completed
runs only after reviewing their reports and confirming no pending journal
references them.

## Requirements, limits, and deployment check

Use the application's supported PHP runtime with SQLite, zlib, HTTPS stream
support/OpenSSL, Git, and `gzip` on `PATH`. The source needs its existing public
repository download endpoint; no API upgrade or database migration is required.

Transfers allow at most 256 MiB compressed, a 120-second deadline, and five
validated redirects. Archive validation allows at most 1 GiB expanded and
100,000 entries; individual canonical records over 16 MiB are reported as invalid
before parsing. Regular GNU/ustar archives and GNU long filenames are supported;
links, special files, traversal, multiple repository roots, and unsupported tar
extensions are rejected before destination changes. These limits are fixed in
this release; exceeding them is a reported failure, not a truncated import.

For a production smoke check, preview a compatible source first and inspect the
resolved source/destination and exclusions. Import into a disposable destination
with the production runtime/configuration; check an imported thread/tag page,
its attribution/reactions, the active static release, and the served offline
snapshot. Repeat the command and confirm zero additions. On the actual target,
follow the same reviewed command and inspect its report and ordinary site views.
No cron entry is needed. Production-host validation is separate from the local
two-instance HTTP acceptance tests.
