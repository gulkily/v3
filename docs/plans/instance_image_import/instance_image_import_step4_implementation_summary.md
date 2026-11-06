> **Feature plan:** [Step 1](./instance_image_import_step1_solution_assessment.md) · [Step 2](./instance_image_import_step2_feature_description.md) · [Step 3](./instance_image_import_step3_development_plan.md) · [Step 4](./instance_image_import_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/instance_image_import`; [host ledger](../effort_estimation_trial.md#cycle-v3instance_image_import). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.60

Branch: `claude/compassionate-turing-d20wyy`, the session's designated branch. Planning commit `fe504ce`. Steps 1–3 were committed before their approvals at the cloud session's stop-hook request, so the planning commit holds only the organization moves, link repairs and the plan index entry.

## Study entry

2026-11-06T15:53:54Z, `railspike-effort` / `v3/instance_image_import`, Step 4 entry. Step 3 explicitly approved. Whole-cycle projection **0.60** reaffirmed. Same scope v1/A, baseline `6efe5ba333ebbb1f63a474609e26c3d4da09509d`, R1/P2 and single exposed Claude rater. Stage-work originals copied from Step 3: S1 0.08, S2 0.18, S3 0.12, S4 0.12, S5 0.10, non-additive.

## Stage 1 - Download the image ZIP

**Original estimate: 0.08**

**Retrospective stage-work assessment: 0.06**

- Changes:
  - `InstanceArchiveDownloader::download` takes a download path: `REPOSITORY_PATH` (default) or `IMAGES_PATH`. Any other path is refused.
  - Image download failures name the image backup in their message. HTTP failures carry the status as the exception code, so later stages can tell 403 (backups off) from 404 (no image download).
  - The `PK` signature check stays in Stage 2, where the ZIP is read.
- Verification:
  - Passed: `./v3 test ImportInstanceCommandTest`, 10/10, including the new `testImageDownloadUsesItsOwnPathAndReportsStatus` (success, 403, 404, refused path, no file left behind).
  - Passed: `./v3 test --smoke`, 433/433.
  - Full suite not run.
- Notes: the repository archive path keeps its existing message and behavior.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-06 UTC*

## Stage 2 - Copy referenced images into the store

**Original estimate: 0.18**

**Retrospective stage-work assessment: 0.16**

- Changes:
  - New `src/ForumRewrite/Import/InstanceImageImport.php`. It lists references from destination posts, finds those the store lacks, unpacks the ZIP into a private staging directory and adds only those images. Preview checks each image and writes nothing.
  - New `src/ForumRewrite/Images/ImageZipReader.php`: the bounded ZIP reader moved unchanged out of `ImageRestore.php`, which now calls it. Restore behavior is unchanged.
  - `ImageStore::putAll` added. It reads the inventory once, skips present images, rejects mismatched files and skips images over the limit. It shares `put`'s write path, and `put` itself is unchanged.
  - The copy runs under the forum write lock that uploads in `LocalWriteService.php` take.
  - `tests/run.php` lists the new test file.
- Verification:
  - Passed: `./v3 test InstanceImageImportTest ImageZipRestoreTest ImageZipBackupTest ImageContentTest`, 10/10. New tests cover a non-empty store, no overwrite, an image from a removed post excluded, an image absent from the ZIP, a mismatched file, the 1024-image limit, preview writing nothing and a corrupt ZIP leaving no store.
  - Passed: `./v3 test --smoke`, 430/430.
  - Full suite not run.
- Notes:
  - In-scope refinement from new understanding: Step 3 planned to reuse `ImageStore::put` unchanged. `put` re-hashes the whole store on every call, so copying up to 1024 images could hash 256 MiB that many times. `putAll` keeps the limit checks in `ImageStore` and hashes the store once. Scope and outcome are unchanged.
  - The copy ignores the manifest's revision, because each file is checked against its post's reference.
- Estimate details: original scope unchanged apart from the refinement above; no revised estimate.

*— Claude · 2026-11-06 UTC*

## Stage 3 - Run the copy from import-instance

**Original estimate: 0.12**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `scripts/import_instance.php` runs the image step after the text import. A dry run also counts images from source posts the plan would import (`InstanceImageImport::plannedReferences`).
  - `InstanceImageImport::fromSource` downloads the image ZIP only when an image is missing. A download or copy failure reports every missing image with its reason and leaves text untouched.
  - Report lines: `Images: complete|partial`, then a reason line per cause, then `images_added` (`images_to_add` in a dry run), `images_present` and `images_missing`. Missing images give exit 2.
  - An error while listing references reports `Images: partial` with "Rerun the same command to retry"; it no longer aborts after text is in.
  - Removed the "Image files are separate and are not transferred" notice from `ContentImportRunner.php` and the command. `import-repository` (local archive import) keeps its own notice, because it has no source to fetch from.
- Verification:
  - Passed: `./v3 test ImportInstanceCommandTest InstanceImageImportTest`, 13/13. The real-source legacy test now checks that the dry run counts 1 image to add and stores nothing, that the import stores the image and the destination serves it at `/images/<file>` with image content on, and that a repeat reports it present without downloading the ZIP.
  - Passed: `./v3 test ContentImportPlannerTest ContentImportRecoveryTest ImportDeadlineRecoveryTest ImportedContentPublicationTest`, 22/22.
  - Passed: `./v3 test --smoke`, 430/430.
  - Full suite not run.
- Notes: the time limit still covers the whole command. Images start only after text is committed, so a timeout leaves only images to retry; Stage 4 tests this.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-06 UTC*

## Stage 4 - Recovery and resume

**Original estimate: 0.12**

**Retrospective stage-work assessment: 0.12**

- Changes:
  - Download reasons: HTTP 403 reads "image backups are turned off on the source"; 404 reads "the source has no image download; it may run an older version". Other failures keep their own message, including missing PHP ZIP support and timeouts.
  - A partial image result prints "Rerun ./v3 import-instance with the source URL to retry the images."
  - `--resume` takes the source URL saved with the interrupted import and fetches images from it, in a private workspace created for the image step. If no URL was saved and images are missing, it reports them missing for that reason. Resume still needs no network when no image is missing.
- Verification:
  - Passed: new `ImportInstanceCommandTest::testImageFailuresKeepTextAndRerunOrResumeRecovers`. Backups off and an older source each end text-complete, exit 2 with the reason, and no image stored. A stalled image download under `--time-limit=4` ends the run after the text is committed, with no pending import. A rerun then adds the image and exits 0. An import interrupted before publication resumes and adds the image from the saved source.
  - Passed: `./v3 test ImportInstanceCommandTest InstanceImageImportTest ImportDeadlineRecoveryTest ContentImportRecoveryTest`, 19/19.
  - Passed: `./v3 test --smoke`, 430/430.
  - Full suite not run.
- Notes:
  - The full-store and missing-ZIP-support cases are covered below the command, by `InstanceImageImportTest` and the existing `ImageZipRestoreTest` reader check. They pass through the same report path as the tested cases; no CLI test replays them.
  - A run killed by the time limit can leave its private staging directory in the system temp directory. Images are written by rename, so the store never holds a partial file.
  - PHP's built-in test server handles one request at a time, so the stalled download is kept short (8 seconds) to avoid blocking the rerun.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-06 UTC*

## Stage 5 - Docs and end-to-end check

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `docs/runbooks/instance_content_import.md`: new Images section with behavior, report lines, each reason and its fix, and retry by rerun or resume. The deployment example now checks `images_missing: 0` instead of saying images are a separate transfer.
  - `docs/reference/v3_cli.md`: the command description and exit codes mention the image copy and rerun.
  - `docs/runbooks/image_backup.md`: points operators with an existing store to `import-instance` instead of restore.
  - `import-instance --help`: two lines on the image copy and retry.
- Verification:
  - Passed, end to end: two local instances on `php -S`, built from the `parity_minimal_v1` fixture. The source had one new post with a 40×20 PNG in its store. The dry run reported `images_to_add: 1` and created no image store. The import exited 0 with `images_added: 1`, and the file appeared in `dest-images`. A repeat exited 0 with `import: 0`, `images_present: 1` and no image download. Chromium opened `/threads/e2e-image` on the destination with image content on; the image loaded at 40×20 and showed in the screenshot.
  - Passed: `./v3 test --smoke` plus `ImportInstanceCommandTest InstanceImageImportTest ImageZipRestoreTest ImageZipBackupTest ImageContentTest ContentImportPlannerTest ContentImportRecoveryTest ImportDeadlineRecoveryTest ImportedContentPublicationTest MarkdownListStructureTest PlatformDocsCatalogTest`, 476/476.
  - Failed, baseline: `PlatformDocsPageTest::testAssetsAreInertAndMissingDocumentsOfferRecovery` (expected 1, got 2). It fails the same way at the starting revision `6efe5ba` in a separate worktree, so this cycle did not cause it.
  - Passed: `git diff --check`.
  - Passed: final diff review. Changes stay within the approved files plus the `ImageZipReader` extraction and `ImageStore::putAll` recorded in Stage 2. No duplicated ZIP reading or store limit logic.
  - Not run: the full suite (`./v3 fulltest`), per project instructions.
  - Inapplicable: theme and navigation icon checks. No UI changed; the browser check only confirms existing image rendering.
- Notes: production and zenmemes.com imports were not tried. They are outside this cycle.
- Estimate details: original scope unchanged; no revisions.

*— Claude · 2026-11-06 UTC*

## Closeout and study outcome

- Delivered scope v1/A: `./v3 import-instance` copies the images that destination posts reference and lack, with dry run, counts, reasons, exit 2, and rerun and resume recovery. All five planned stages are complete, each with its own commit.
- Commits: planning `fe504ce`; stages `84ef42d`, `c639161`, `10b3961`, `3b45acc` and this Stage 5 commit. Steps 1–3 were committed before approval, up to `46e1c06`, at the stop hook's request.
- Corrections versus new requests: one in-scope refinement (`ImageStore::putAll`, Stage 2). One new request, incremental sync, is deferred to the backlog.
- Unverified: production use, the full suite, and CLI-level replays of the full-store and missing-ZIP-support cases (covered by lower-level tests).
- 2026-11-06T16:06:06Z, `railspike-effort` / `v3/instance_image_import`, Step 4 closeout. Entry projection 0.60 kept; no prospective revision. **Retrospective scope assessment: 0.55**, single exposed Claude rater; stage retrospectives 0.06/0.16/0.10/0.12/0.10, non-additive. No measured labor. Maintainer comfortable-fit judgment and acceptance pending.

## Enhancement Requests

- Incremental sync (2026-11-06): separate work, recorded in [Step 2](./instance_image_import_step2_feature_description.md#enhancement-requests) and the [backlog](../backlog/incremental_instance_sync.md).
