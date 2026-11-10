# codebase_sizes — Step 4: Implementation Summary

> **Feature plan:** [Step 3](./codebase_sizes_step3_development_plan.md) · [Step 4](./codebase_sizes_step4_implementation_summary.md)

- Debug mode: off (not enabled for this cycle).
- Effort study: Step 3 was written without a projected-total snapshot; Step 4 entry projection unavailable, not backfilled. Retrospective and maintainer comfortable-fit judgment: pending.
- Open question from Step 3 (download sizes inline): approved as proposed (yes).

## Stage 1 - Byte formatting and size probes

- Changes:
  - New `ForumRewrite\Support\ByteSize`: `format(int): string` (binary units, one decimal) plus the probes `file()`, `gitStore()`, `trackedTree()`. They sit here, not as private controller methods as planned, so they can be tested without rendering the page.
  - `CodebaseStateController::collectState()` gains `sizes` (read-model database, committed content records, content repository Git store, app checkout Git store), each with a relative `state/...` source command.
  - `tests/ByteSizeTest.php`, registered in `tests/run.php`.
- Verification:
  - `./v3 test ByteSizeTest LocalAppSmokeTest`: 168 run, 168 passed.
  - Probe timing on the v3 checkout itself (4,016 tracked files, 46 MiB): `trackedTree` 18 ms, `gitStore` 4 ms.
- Notes:
  - First draft ran git through `BoundedProcess`, which reads 64 KiB per 10 ms poll: 665 ms for the same `ls-tree`. A large content repo would hit the budget and show "unavailable". Switched to coreutils `timeout -s KILL 2` with a blocking read and a 64 MiB output cap; `BoundedProcess` left unchanged.
  - With no `timeout` binary or no `proc_open`, probes return null and the row reads "unavailable".

## Stage 2 - Last-built archive sizes

- Changes:
  - `ArchiveCache::peekBytes(string $identity, string $kind): ?int`: the same symlink and metadata-size guards as `open()`, plus matching identity, kind and byte count. It never opens, hashes or locks the archive.
  - `ImageBackup::cachedBytes(string $kind): ?int`, for `tar.gz` and `zip` only. It reuses the private `identity()`, returns null when `archive-exports/` doesn't exist (so it never creates storage just to look), and returns null on any error, such as uncommitted records.
  - `CodebaseStateController::downloads()` adds `bytes` and `on_request` to each download; the SQLite download uses `ByteSize::file()`.
  - New `ArchiveExportTest::testCachedBytesReportsOnlyAVerifiedBuildForTheCurrentRevision`: not built yet, no storage created, built size matches, other format null, tampered metadata null, new commit null.
- Verification:
  - `./v3 test ArchiveExportTest ByteSizeTest LocalAppSmokeTest`: 180 run, 180 passed.
  - Timing on a 5,000-record scratch repo: `cachedBytes('tar.gz')` 22 ms, `trackedTree(records)` 47 ms.
- Notes: the page now computes the archive identity twice per render (tar.gz and zip). That measured cheap, and it is the same cheap identity the download hit path already uses.

## Stage 3 - Collapsed Sizes card and inline download sizes

- Changes:
  - `templates/pages/codebase_state.php`: new `<details class="card codebase-sizes">` (closed, `<summary><h2>Sizes</h2></summary>`) before Downloads. It reuses the `codebase-facts` table and `codebase-source` rows. Values read `244.0 KiB (249,856 bytes)`; nulls read `unavailable`, or `built on request` for the archives. Download rows appear only when text backups are enabled.
  - Downloads list: each link gets a `meta` size when one is known.
  - `public/assets/tool-details.css`: summary cursor, inline `h2`, spacing when open.
  - `tests/LocalAppSmokeTest.php`: asserts the card, its summary, that it starts collapsed, a size row, a source command and a download row.
- Verification:
  - `./v3 test ArchiveExportTest ByteSizeTest LocalAppSmokeTest AssetFingerprintTest PresentationProfileMatrixTest BackupFeatureFlagTest`: 193 run, 193 passed. Full suite not run.
  - Manual: `./v3 start`, downloaded `repository.tar.gz` once, then checked iPhone 13 emulation (390 px). Collapsed and expanded both render; scrollWidth equals viewport width; tar.gz shows 4.1 KiB, zip shows "built on request", SQLite shows 244.0 KiB.
- Notes: archive rows show a description instead of a command, since their size comes from the cache metadata rather than one shell call.

## Stage 4 - Deploy check and thread reply

- Status: pending merge and deploy. After deploy: expand Sizes on zenmemes.com, compare the database row with `ls -l` on the server, then reply as `claude` in [the request thread](https://zenmemes.com/threads/thread-20261103063011-37a33e13).

*— Claude (Opus 5.5) · 2026-11-10 UTC*
