# History-free repository exports — one-shot implementation

2026-10-24. User authorized a one-shot archive-size fix and explicitly selected
“omit .git.” This is separate from the pending retraction simplification Step 2;
no retraction implementation or history purge is included.

## Result

Both repository TAR.GZ and ZIP routes, with and without a pinned revision, use the
same locked snapshot exporter. It excludes `.git` before traversal/capacity checks
and copying. Git history, configuration, hooks, indexes and reflogs are absent;
the source repository and its maintenance configuration are unchanged. Existing
content capacity, dirty-record rejection, revision matching, failure cleanup and
image inventory validation remain in effect.

Legacy posts receive portable `records/post-timestamps/` metadata in the exported
copy when needed; signed post/signature bytes remain unchanged. Modern posts do
not trigger a legacy Git history walk. A small `repository-snapshot.json` records
the source revision and a checksum of exported records so matching image archives
can be verified/restored without Git. This is snapshot metadata, not an ongoing
cleanup ledger or a replacement for trusted Git history. Existing Git-backed
image restores remain supported, including `.git` pointer files.

The backup page and image recovery runbook now describe current-content exports.
They explicitly exclude historical revisions and a complete instance backup.
History-dependent acceptance evidence still needs a separate full Git backup;
ordinary content import retains destination authority rules. No new dependency,
schema, navigation destination, scheduled worker or production configuration.

## Verification

- Focused PHP suite: **20/20** across repository exports, image backup/restore,
  archive validation, legacy timestamp imports, backup-page rendering and the two
  affected application smoke tests. Final image/export regression rerun: **7/7** after
  the Git-pointer compatibility adjustment.
- Both formats omit a synthetic 600 MiB `.git` file before export limits apply;
  source HEAD, signatures, records and Git contents remain unchanged. Pinned and
  unpinned actual application routes are covered, along with wrong revisions,
  dirty source rejection, history-free import, modified-snapshot rejection and
  restoration of actual images from the matching pair.
- QDB validation copy at `5661621eb648be28fc13884fac0145194e5eaa5b`, recovered from
  the supplied archive: final TAR.GZ **7,032,777 bytes (6.7 MiB)** and ZIP
  **19,959,902 bytes (19.0 MiB)**, versus original TAR.GZ **937,403,333 bytes**.
  All **31,101** canonical files in the validation source match the original
  supplied archive byte-for-byte. TAR.GZ passes the existing bounded importer.
  All **18,931** post records retain
  exact bytes and creation dates; QDB needed no generated legacy date records.
  Separate legacy-date fixtures cover that compatibility path. Sizes are measured
  locally; compression timestamps can vary and local timings are not host SLAs.
- Chromium: **20 rendered states**, widths 390/1440, Light/Dark/Word97/Chicago/
  Midnight, image section present/absent, actual theme styles loaded, no horizontal
  overflow, correct descriptions and visible download controls. Inspected the
  Midnight phone and Light desktop screenshots. Browser stale-revision HTTP 409
  recovery and both successful download actions pass.
- The first temporary browser harness omitted fingerprinted-asset routing; its
  results were discarded. The corrected FrontController harness verified styles
  loaded before recording the passing matrix. A temporary fixture setup error was
  also corrected; neither required application changes.
- PHP syntax and `git diff --check` pass. UI verification is indexed in
  `ui_copy_audit_checklist.md`. Full repository suite, physical devices and
  production deployment were not performed.

## Delivery boundary

Implemented in `/home/wsl/agent/v3-codex`; user subsequently authorized committing
and pushing the fix. Production deployment and mutation of the supplied archive
or `~/v3` working repository remain outside this change.
Private validation copies/artifacts live under `/tmp`. Existing retraction planning
and effort-study edits are unrelated and must stay outside any implementation
commit. Dependency delta: **zero**.

## Commit and integration verification

Archive fix committed as `042e684a`. Integrated remote main at `8d51a177` before
push, retaining its canonical Railspike upgrade provenance when duplicate local
upgrade notes conflicted. No archive implementation conflict. All 20 focused
export/image/import/backup/application checks passed again on the combined tree.
Retraction planning remains separately preserved and uncommitted.
