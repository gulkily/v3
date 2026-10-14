# Image backup and recovery

Image support uses one private local store per content instance, outside Git.
`FORUM_IMAGE_CONTENT_ENABLED` defaults off; a root-approved operator can change it
from Tools → Feature Flags. Environment overrides take precedence. Disabling it
stops uploads and image delivery/rendering, but preserves records, bytes and
access to retained-image backups. Existing downloaded copies cannot be recalled.

## Download a complete pair

From Tools → Backup, download either content repository archive **and** the image
collection `.tar`. Keep matching revision filenames together. The image archive
contains `manifest.json` with the repository HEAD and checksums, plus every retained
object under `objects/`. The repository includes Git history. SQLite is derived
and cannot substitute for either archive. Image exports include retained objects
from previously published posts, even if those posts are later removed.

Links pin the revision displayed on the page. If publication changes it between
downloads, refresh Backup and download both again. A wrong revision, dirty canonical
records, missing/corrupt image or capacity error produces a failed download, never
a purportedly complete image archive. On a busy instance, pause publishing while
taking the pair. Approved-members-only access also protects the exports.

## Restore on another instance

Use an isolated destination and the repository archive from the same pair. Extract
your trusted repository snapshot into a fresh directory; it contains a top-level
`repository/`, including `.git`. Preserve that history: the ordinary content-import
command creates a new destination revision and is not a backup-pair restoration.

```bash
mkdir -p /private/restored-forum
# The directory must be empty before extracting your repository snapshot.
tar -xzf /backups/forum-repository-REVISION.tar.gz -C /private/restored-forum
./v3 images verify /backups/forum-images-REVISION.tar /private/restored-forum/repository /private/restored-forum/cache/index.sqlite3
./v3 images restore /backups/forum-images-REVISION.tar /private/restored-forum/repository /private/restored-forum/cache/index.sqlite3
./v3 rebuild /private/restored-forum/repository /private/restored-forum/cache/index.sqlite3
```

The default image destination is a sibling of the repository, named
`<repository-basename>-images`. Set `--image-root=/private/images` for another
fresh directory, then configure that exact `FORUM_IMAGE_ROOT` in the new instance.
It must be absolute, outside the repository and every served web root, and contain
no symlink components. Point the new application at the restored repository and
read-model paths; enable image support and check a thread and reply before cutover.
Use newly built static artifacts, not old releases from another instance.

Verification checks the revision, clean canonical records, strict tar paths/types,
full inventory, checksums, file types/dimensions, and all current post references.
It uses temporary private staging but does not activate a store. Restore validates
first, then renames the verified store into place. Existing different stores are
never overwritten; an identical existing store makes retries safe. Both commands
exit nonzero on failure. The old instance can remain intact until cutover.

## Failure recovery

- Wrong pair: locate matching revision archives; do not edit the manifest or posts.
- Missing/damaged image: restore the verified pair into a fresh image root and
  switch configuration deliberately. Preserve the old store for diagnosis.
- Interrupted restore: the old store remains intact. Retry; normal failures remove
  staging automatically. After a killed process, an operator may remove its orphan
  `.image-restore-*` directory only after confirming no restore is running.
- Publication reports a committed post with an image-save failure: keep the form
  open and retry the unchanged submission with the same file. Its preparation token
  expires; after expiry, use operator recovery into a verified store rather than
  publishing a duplicate. Canonical references are never silently rewritten.
- Temporary export failure: free disk/retry. Confirm no export is running before
  removing orphan `forum-image-export-*` temporary files left by a killed process.

## Capacity and deployment

JPEG/PNG only: 5 MiB per image, 4096 pixels per side, one image per post/reply,
and an optional description of at most 500 UTF-8 bytes. Retained store: 256 MiB/1024 files. No image
transformations, automatic retained-image deletion or cloud backend are included.
Original bytes, including embedded metadata, are preserved.

Use PHP with Phar and the normal project prerequisites. Configure PHP/web-server
request limits above 5 MiB plus the signed form envelope (for example,
`upload_max_filesize=6M`, `post_max_size=8M`). Image compose needs JavaScript and
Web Crypto over HTTPS or localhost. Keep the private store writable by the PHP
user, and make the same instance paths available to CLI and web processes.

Image/repository exports are serialized; repository web snapshots are limited to
512 MiB/100000 files and require twice their snapshot bytes plus 16 MiB temporary
space. Images have their own 256 MiB bound. Oversized repositories need an operator
filesystem backup during maintenance. All instance writers must use the same
write lock; stop external Git/filesystem writers during snapshots/restoration.
Validate archive duration and PHP timeouts on the deployment host.

All content, including QDB routes, now passes through the PHP front controller.
It can serve matching static HTML; flag-state mismatches fall back to live rendering.
Do not restore direct Apache/CDN content shortcuts that bypass flag/membership
checks. `/images/` responses are private/no-store and must never be mapped directly
to the store. Offline snapshots do not contain image bytes.

## Future object storage (Option C)

Image references use SHA-256 and metadata, not hostnames or filesystem paths.
A future bucket adapter must preserve these identities, authorization, complete
inventories, revision-bound exports, and independent restoration. Credentials and
provider URLs belong in deployment configuration, never signed posts or backups.

## Upload decoder readiness

New uploads require PHP GD with JPEG and PNG decoding in the **serving PHP runtime**.
Run `php -r 'var_export(gd_info());'` for the CLI launcher; separately check the
web-server PHP configuration when using Apache/FPM. Enable the matching GD package
(for this Ubuntu/PHP 8.1 runtime: `sudo apt-get install php8.1-gd`) and reload the
serving process. Missing decoding support rejects uploads; existing reads and
backup/restore do not require GD. Never replace decoding checks with header checks.
