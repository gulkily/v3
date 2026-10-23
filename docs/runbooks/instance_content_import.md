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

Archive preparation reports separate validation, gzip integrity, extraction,
and legacy timestamp phases. Longer phases update roughly once per second with
elapsed time, checked entries or files, processed MiB, and recovered-date counts
where applicable. Extraction totals include temporary history files used for
legacy dates; they are not counts of records that will be imported.

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
For legacy posts without `Created-At`, the importer recovers the original Git
addition date (following renames) only when archived bytes match the source
history tip. It reads a temporary, isolated object store; source configuration,
hooks, alternates, replacements, and history are never installed in the destination.
Legacy verification and history lookup use up to four concurrent Git processes.
Each post still follows its own rename history; results are applied in canonical
path order so completion order cannot change the saved metadata. History lookup
has a shared 120-second budget and 1 MiB output limit per query. All outstanding
workers are stopped if recovery fails. Progress includes completed lookups and
the number of Git processes running.
Recovered dates are committed as `records/post-timestamps/<post-id>.json`, bound
to the exact post bytes by SHA-256. Rebuilds and subsequent imports use these
portable dates before local Git history. Post and signature bytes stay unchanged.
Missing/unusable history leaves legacy posts invalid; filesystem timestamps and
the new import commit date are never substituted. Conflicting metadata or local
creation dates require review. The source's date is provenance, not independent
proof of when an author wrote a post.
Detached signatures are copied with their records; import is not a new signed
submission and does not independently authenticate a source's authorship claims.
When a duplicate record exists at another path and a new signature cannot be
associated safely, the group is reported for manual review.

Identical records are skipped. Different bytes at the same path or canonical
identity become conflicts; the local version stays in place. Source deletions
never remove local data. Related records/signatures with rejected dependencies
are withheld together. The existing `import-repository` command remains
available for local archives with its previous options and policy.

`--dry-run` downloads and validates the archive and reports the proposed merge. It
may create temporary files and lock files but does not change destination
records, commits, the read model, or published views. It cannot be combined with
`--resume`; remove `--dry-run` to perform the import. Reports list category counts, grouped review causes, and exclusion counts. A
blocked record points to its rejected/excluded dependency and root cause;
"missing dependency" means no matching record exists in the source or destination.
Timestamp metadata for an intentionally excluded post is also excluded. Replies
to excluded authority posts remain withheld, since their parent would be absent.
Use `--verbose` for every excluded/unsupported/invalid/conflicting record, for
example `./v3 import-instance --dry-run --verbose zenmemes.com | less`.
Closing a pager stops further stdout output while the command finishes its work
and cleanup. Exit status continues to describe the import, not the pager. Private review
reports and conflict copies live below the printed
`<repository>/.git/instance-import/<run-id>/` directory.

| Exit code | Meaning |
| --- | --- |
| 0 | Complete supported-content merge or preview; intentional exclusions are listed. |
| 2 | Partial result: conflicts, invalid records, or unsupported records need review. Accepted content is published on an actual run. |
| 1 | Failure: use the error and recovery guidance; publication may be unfinished. |

## Recovery

### Source download failures

An HTTP failure during `Downloading repository archive...` occurs before the
merge. It does not itself change destination content, create a recovery journal,
or publish views. `--dry-run` uses the same download; destination rebuilds and
`--verbose` cannot repair a failing source exporter. The CLI reports the HTTP
status and up to 1 KiB of sanitized plain-text source detail, reading at most
4 KiB under its existing deadline. HTML, encoded, or invalid UTF-8 error bodies
fall back to status and retry guidance.

For a bounded endpoint probe matching the importer, save the body separately:

```bash
probe_body=$(mktemp)
curl --silent --show-error --max-time 25 --max-filesize 16384 \
  --dump-header - --output "$probe_body" \
  -H 'Accept: application/gzip' -H 'Accept-Encoding: identity' \
  -A 'ForumInstanceImport/1' \
  https://zenmemes.com/downloads/repository.tar.gz
```

For a non-200 `text/plain` response, inspect the saved body. With a 200 response,
curl may exit 63 because the archive exceeds the probe limit; that is a partial
probe file, not an importable archive. Remove the temporary file after inspection.

`409` with `Backup failed: Missing required post header: Created-At` identifies
source backup parsing, not a local merge conflict or evidence of disabled public
downloads. An existing image-store directory selects the image-aware exporter,
which scans plain-text posts too. Deploy the legacy-compatible backup fix on the
source. Update the importing checkout as well to obtain improved diagnostics.
The original [zenmemes.com findings](../plans/zenmemes_import_http409_findings.txt)
are preserved for incident context.

On the source, from its content repository, identify candidates and inspect the
addition history of a reported path (replace `POST_ID`):

```bash
rg --files-without-match --glob '*.txt' '^Created-At: .+' records/posts
git log --follow --diff-filter=A --format='%aI %H' -- records/posts/POST_ID.txt
```

Missing dates can be supported legacy data. Backup scanning accepts hash-bound
timestamp metadata or Git provenance, never filesystem modification times.
Remaining parse/reference errors identify the affected record path. Preserve
history, original post bytes, and signatures; do not invent dates, skip records,
or remove the image store to bypass validation. Missing/corrupt referenced images
still block complete backups and require source repair.

After an authorized source deployment, verify a successful public archive
response and repeat the original WSL workflow from `~/v3-test-kris`, using the
same destination configuration:

```bash
./v3 import-instance zenmemes.com --dry-run
./v3 import-instance zenmemes.com
./v3 import-instance zenmemes.com
```

Review the preview before importing. Check an imported thread/tag page and
original creation dates; the repeat should report `import: 0` if the source has
not changed. Image objects remain a separate transfer. A local test pass does
not verify zenmemes.com deployment or this WSL destination's recovery.

### Interrupted merges

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

The CLI emits resume guidance only when the selected destination already has
`.git/instance-import/pending.json`. A download failure without that journal
requires source repair and retry, not `--resume`. Preserve an independently
pending journal even if a later download attempt fails.

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

### Documentation publication

Imports, `--resume`, forum builds and shared refreshes perform no documentation
rendering or bulk inventory, validation or copying. Changed application code,
missing docs and broken docs templates do not make imports rebuild the library.
Previously published docs remain available across forum publication and cleanup.

Publish docs separately for initial static setup or after docs, rendering, profile
or asset changes, using the same destination and profile as the site:

```sh
./v3 docs publish --repository-root="$records" --static-html-root="$static_root"
```

Use `--json` for automation and the site's normal `FORUM_SITE_ID` environment.
The complete library, browse/search pages, downloads and assets activate together
under the static root's `-docs` sibling. This command does not replace the forum
release, read model or forum offline snapshot. Docs use `/api/docs-version` for
update notifications, independently of forum imports.

An existing combined release is retained through a bounded legacy reference until
a successful docs publication replaces it. Without any publication, PHP readers
remain available; static-only hosting needs the separate docs command. A failed
docs publication preserves the prior library. Correct the reported cause and retry
`docs publish` with the same options/profile. Use import `--resume` only for the
pending forum import; it does not retry docs publication.

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
