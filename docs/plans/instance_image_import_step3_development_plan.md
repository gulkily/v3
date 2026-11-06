> **Feature plan:** [Step 1](./instance_image_import_step1_solution_assessment.md) · [Step 2](./instance_image_import_step2_feature_description.md) · [Step 3](./instance_image_import_step3_development_plan.md) · [Step 4](./instance_image_import_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/instance_image_import`; [host ledger](./effort_estimation_trial.md#cycle-v3instance_image_import). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60

Concepts touched: *instance import* (changed); *image store* and *image backup* (used unchanged). No concept inventory entry exists for these; this cycle adds none.

## Completion Contract

- Normal entry: `./v3 import-instance <source>`, with `--dry-run`, and `./v3 import-instance --resume`.
- End-to-end outcome: after one run, imported image posts display their images on the destination with image content turned on.
- Required recovery: a rerun copies remaining images after a failed download, image backups turned off, an older source without an image ZIP, missing PHP ZIP support, a full store, a timeout or an interrupted run. The text import is never undone or blocked.
- External verification: one real import between two local instances, with a browser check of an imported image post. Production use is not part of this cycle.
- Release condition: smoke and changed tests pass, the real import passes, docs are updated and the final diff is reviewed. The full suite is not run here.

## Key Risks

- **High risk:** a source's image ZIP may hold images of posts the source removed. Impact: removed content returns. Validation: a Stage 2 test with a ZIP image that no post references. Mitigation: copy only images that destination posts reference.
- Time limit. Impact: a large ZIP can exceed the 300-second limit. Validation: a Stage 4 test with a short `--time-limit`. Mitigation: fetch images only after the text import is recorded, so a timeout leaves only images to retry.
- Resume. Impact: `--resume` has no source URL, and resume must work offline today. Validation: Stage 4 checks that the saved import record keeps the source. Mitigation: resume tries the saved source for images. If that fails, it reports the images as missing and tells the operator to rerun with the source URL.
- Concurrent uploads. Impact: the 1024-image limit could be crossed. Mitigation: add images under the write lock that uploads in `LocalWriteService.php` already take.

## Stage 1 - Download the image ZIP

**Estimate: 0.08**

- Goal: the downloader can fetch the source's `/downloads/images.zip` as well as the repository archive.
- Dependencies: none.
- Expected changes: `InstanceArchiveDownloader::download` takes the download path, defaulting to the repository archive. Missing ZIP and refused responses give distinct, bounded reasons.
- Verification approach: extend the downloader tests in `ImportInstanceCommandTest.php` for success, not found, refused and over-size.
- Estimate details: driver: error wording for absent versus refused downloads; uncertainty: low.
- Risks or open questions: older sources answer the ZIP path with a page instead of a ZIP. Mitigation: check the `PK` signature and report "source has no image download".
- Canonical components/API contracts touched: `src/ForumRewrite/Import/InstanceArchiveDownloader.php` (extended).

## Stage 2 - Copy referenced images into the store

**Estimate: 0.18**

- Goal: a new `InstanceImageImport` class adds missing referenced images from a ZIP to a non-empty store.
- Dependencies: none.
- Expected changes: `InstanceImageImport::run(ZIP path, references, preview)` returns counts for added, already present and missing, plus a reason. Move the bounded ZIP reader out of `ImageRestore.php` into a shared reader that both classes use. Restore behavior stays the same. Add each image through `ImageStore::put` under the forum write lock. Unreferenced and mismatched ZIP files are skipped. A full store stops copying and reports the remaining images as missing.
- Verification approach: new `InstanceImageImportTest` covering a non-empty store, no overwrite, a removed-post image excluded, a mismatched file, a full store and preview writing nothing. Rerun the existing image restore tests.
- Estimate details: driver: extracting the reader without changing restore; uncertainty: medium.
- Risks or open questions: the reader expects the manifest's revision to match. Mitigation: the copy ignores the revision and checks each file against its post reference.
- Canonical components/API contracts touched: `src/ForumRewrite/Images/ImageRestore.php` (reader extracted), `ImageStore.php` and `ImageBackup::references` (reused), new `src/ForumRewrite/Import/InstanceImageImport.php`, because no class merges selected images into a non-empty store.

## Stage 3 - Run the copy from import-instance

**Estimate: 0.12**

- Goal: an ordinary import and a dry run copy or preview images and report them.
- Dependencies: Stages 1 and 2.
- Expected changes: after the text import, `scripts/import_instance.php` lists references from destination posts. In a dry run it also lists those from source posts the plan would import. It downloads the ZIP only when some image is missing. Report lines follow the Fastmod score lines. Missing images make the result partial and exit 2. The old "images are not transferred" notice goes away.
- Verification approach: extend the real-source test in `ImportInstanceCommandTest.php` so an imported image post's image is stored and served; dry run leaves the store unchanged; a repeat run downloads no ZIP.
- Estimate details: driver: dry-run reference listing; uncertainty: low.
- Risks or open questions: none beyond Key Risks.
- Canonical components/API contracts touched: `scripts/import_instance.php`, `ContentImportRunner.php` (`media_notice` removed).

## Stage 4 - Recovery and resume

**Estimate: 0.12**

- Goal: every Completion Contract recovery case ends text-complete, exit 2 with a reason, and a rerun fixes it.
- Dependencies: Stage 3.
- Expected changes: resume reads the source URL from the saved import record. Reasons for backups off, no image download, missing ZIP support, full store and timeout.
- Verification approach: CLI tests for each case followed by a successful rerun, and resume with the source offline.
- Estimate details: driver: simulating each failure with the real router; uncertainty: medium.
- Risks or open questions: the saved record may not keep the URL. Mitigation: fall back to the rerun message, as Step 2 allows.
- Canonical components/API contracts touched: `ContentImportRunner.php` saved record (read only), `scripts/import_instance.php`.

## Stage 5 - Docs and end-to-end check

**Estimate: 0.10**

- Goal: operators learn the behavior from `--help` and docs, and a real import proves the outcome.
- Dependencies: Stage 4.
- Expected changes: update `--help`, `docs/reference/v3_cli.md` and `docs/runbooks/image_backup.md`. The runbook keeps restore for empty stores and drops the manual copy advice.
- Verification approach: two local instances, one import, then open an imported image post in a browser; smoke set plus changed tests; `git diff --check`; final diff review for duplication and scope.
- Estimate details: driver: setting up two instances; uncertainty: low.
- Risks or open questions: none.
- Canonical components/API contracts touched: the docs above.

## Study checkpoint

2026-11-06T15:51:09Z, `railspike-effort` / `v3/instance_image_import`, Step 3 handoff. Step 2 explicitly approved. Scope v1/A unchanged; the incremental sync request stays separate backlog work. Starting revision `6efe5ba333ebbb1f63a474609e26c3d4da09509d`; reference R1, protocol P2; frozen R1 hashes not re-verified. Single exposed Claude rater. Whole-cycle projection **0.60** reaffirmed. Stage work originals 0.08, 0.18, 0.12, 0.12 and 0.10 are separate judgments and do not add up to the cycle estimate. No implementation, measured labor or accepted outcome.

*— Claude · 2026-11-06 UTC*
