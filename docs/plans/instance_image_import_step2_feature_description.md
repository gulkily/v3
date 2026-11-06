> **Feature plan:** [Step 1](./instance_image_import_step1_solution_assessment.md) · [Step 2](./instance_image_import_step2_feature_description.md) · [Step 3](./instance_image_import_step3_development_plan.md) · [Step 4](./instance_image_import_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/instance_image_import`; [host ledger](./effort_estimation_trial.md#cycle-v3instance_image_import). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60

## Problem

`./v3 import-instance` copies another instance's posts but none of their images, so imported image posts show missing pictures. No command adds a source's images to a destination that already has images of its own.

Concepts touched: *instance import* (changed: it now copies images); *image store* (used unchanged); *image backup* (used unchanged: the source's image ZIP). No concept inventory entry exists for these yet; this cycle adds none.

## User stories

- As an operator, I want one `import-instance` run to bring a source's posts and their images, so that imported posts display correctly.
- As an operator, I want a rerun against an earlier source to fill in its missing images, so that past imports get repaired without new text.
- As an operator, I want a clear count and reason when images could not be copied, so that I know what to fix and retry.
- As a moderator, I want images from removed source posts kept out, so that an import never brings back content the source took down.

## Core requirements

- After the text import, the command copies every image that a destination post references and the destination store lacks. No flag is needed.
- Each copied image must match its post's reference exactly. The command never overwrites or deletes an existing image.
- Images that no destination post references are never copied.
- A failed or absent image download never undoes or blocks the text import. The command finishes the text, reports the reason and exits 2.
- `--dry-run` reports the image counts it would produce and writes nothing.

## Delivery scope

- Work type: application change.
- Files: the import command and its import classes, a new image-copy class beside them, the archive downloader, tests, the CLI reference and the image backup runbook.
- Out of scope: a separate `./v3 images import` command for local ZIPs, changes to the source's image ZIP format, and raising the store limit.

## Completion boundary

- Normal entry: `./v3 import-instance <source-url>`, with or without `--dry-run`, and `./v3 import-instance --resume`.
- End-to-end outcome: imported image posts display their images on the destination with image content turned on.
- Recovery: a rerun of the same command retries the images. This covers a failed download, a source with image backups turned off, an older source without an image ZIP, missing PHP ZIP support, the store limit, a timeout and an interrupted run.
- Release condition: smoke and changed tests pass, and one real import between two local instances shows images on the destination.

## Risks

- **Time limit.** The import has a 300-second limit, and a large image ZIP adds to it. Impact: a slow download could end a run before its text import is recorded. Earliest validation: time a 256 MiB ZIP download locally. Mitigation: download images only after the text import is complete, so a timeout leaves only images to retry.
- **Resume has no URL argument.** `--resume` takes no source. Impact: a resumed import could not fetch images. Earliest validation: confirm the interrupted-import record keeps the source URL. Mitigation: read the source from that record; otherwise report "rerun with the source URL".
- **Source without image backups.** Every import from such a source would end as partial. Impact: operators may learn to ignore exit 2. Earliest validation: check the source's response with image backups turned off. Mitigation: name the cause in one line and count missing images, so the partial result stays specific.
- **Concurrent writes.** A post upload can write to the store while the import adds images. Impact: the 1024-image limit could be crossed. Mitigation: add images through `ImageStore::put`, which checks the limit on each write, under the same lock the import already holds.

## Shared component inventory

- `scripts/import_instance.php`: extend. It already prints the Fastmod score result; image counts follow the same pattern.
- `src/ForumRewrite/Import/InstanceArchiveDownloader.php`: extend to download a second path. It already handles URL checks, redirects, size and time limits.
- `src/ForumRewrite/Import/FastmodScoreImport.php` and `ContentImportRunner.php`: reuse their pattern for a supplement that follows the text import, including dry-run and resume.
- `src/ForumRewrite/Images/ImageRestore.php`: reuse its bounded ZIP reader. Its restore path is not reused, because it requires matching revisions and refuses a non-empty store.
- `src/ForumRewrite/Images/ImageStore.php`: reuse `put`, `isPresent` and the limit checks unchanged.
- `src/ForumRewrite/Images/ImageBackup.php`: reuse `references` to list the images that destination posts reference.
- New: one image-copy class in `src/ForumRewrite/Import/`. No existing class merges selected images into a non-empty store.

## Simple user flow

1. The operator runs `./v3 import-instance https://source.example`.
2. The text import runs and reports as it does today.
3. The command lists images that destination posts reference and the store lacks.
4. If any are missing, it downloads the source's image ZIP and adds the matching images.
5. The report shows images added, already present and still missing, with a reason for any missing.
6. If anything is missing, the operator fixes the cause and runs the same command again.

## Success criteria

- An import from a source with image posts shows those images on the destination with no manual step.
- Every copied image's checksum matches its post's reference, and no existing image changes.
- A rerun after any recovery case above copies the remaining images and exits 0 once none are missing.
- An image belonging only to a removed source post never appears in the destination store.

*— Claude · 2026-11-06 UTC*

## Study checkpoint

2026-11-06T15:48:38Z, `railspike-effort` / `v3/instance_image_import`, Step 2 handoff. Step 1 Option A explicitly approved. Scope v1/A unchanged. Starting revision `6efe5ba333ebbb1f63a474609e26c3d4da09509d`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Projected total 0.55 → **0.60**, from new understanding, not changed scope: resume needs the saved source URL, and the downloader needs a second path. Step 1 values preserved in the Step 1 artifact. No implementation, measured labor or accepted outcome.

## Enhancement Requests

- **Request (2026-11-06):** a future way to sync optimally, without downloading the whole archive every time. **Disposition:** separate work. It changes how both the repository archive and the image ZIP are fetched, which this cycle's scope excludes. This cycle only skips the image ZIP when no image is missing. **Approval status:** not approved; backlog only. **Follow-up:** [incremental instance sync](./backlog/incremental_instance_sync.md).
