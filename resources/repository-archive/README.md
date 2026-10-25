# Content repository snapshot

This archive contains the site's exported content at the revision named in
`repository-snapshot.json`. The TAR.GZ and ZIP downloads contain the same files.
Git history, application code and private instance configuration are not included.

## Read the content

Open files in `records/` with a text editor. Record headers identify the item and
its relationships; post text follows the headers. Common directories include:

- `posts/`: threads and replies, including references to attached images.
- `identity/` and `public-keys/`: identity records and public signing keys.
- `instance/`: published instance information.
- `post-timestamps/`: dates supplied for older posts without a recorded date.

Other directories contain supporting records. Which directories are present
depends on the source instance. Keep the records unchanged if you intend to verify
or restore this snapshot's image backup.

## Use the content in another instance

Install the [application](https://github.com/gulkily/v3) separately and follow its
setup instructions. Run the following from that application's directory, using
an initialized destination repository and explicit destination paths:

```sh
./v3 import-repository /backups/repository.tar.gz /path/to/destination-repository /path/to/cache/index.sqlite3 --dry-run
```

Review the preview before repeating without `--dry-run`. The importer accepts
TAR.GZ, not ZIP; download TAR.GZ if you intend to import. Importing merges supported
records into the destination under its existing rules; it does not recreate the
source instance's authority or trusted history. Conflicting or unsupported records
may need operator review. See the application's
[CLI reference](https://github.com/gulkily/v3/blob/main/docs/reference/v3_cli.md).

## Keep images with their matching snapshot

Image files are a separate `images.tar` download. Keep the content and image
archives with matching revision filenames together. `repository-snapshot.json`
identifies the source revision and checksums the records; do not edit or discard it.
If the site changes between downloads, refresh Backup and download both again.

Extract either content archive into an empty directory. With the application
installed, verify the image archive against the extracted `repository/` directory:

```sh
./v3 images verify /backups/images.tar /path/to/extracted/repository /path/to/cache/index.sqlite3
```

Verification does not install images. Follow
[Image backup and recovery](https://github.com/gulkily/v3/blob/main/docs/runbooks/image_backup.md)
for restoration, image-store configuration and rebuild steps. If verification
reports a mismatch, use the original matching pair with unchanged records;
do not edit the manifest to make it pass.

## Backup limits

This is a content snapshot, not a complete site backup. Keep a separate full Git
backup and private configuration for exact instance recovery. A downloaded SQLite
index is derived data and cannot replace the content or image archives.
