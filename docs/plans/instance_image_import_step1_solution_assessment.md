> **Feature plan:** [Step 1](./instance_image_import_step1_solution_assessment.md) · [Step 2](./instance_image_import_step2_feature_description.md) · [Step 3](./instance_image_import_step3_development_plan.md) · [Step 4](./instance_image_import_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/instance_image_import`; [host ledger](./effort_estimation_trial.md#cycle-v3instance_image_import). Standing P2 enrollment. No parent cycle.

Extended estimates: enabled

Cycle estimate: 0.55

## Original Query

We need a command to do this just like with text... either add to import-instance a flag to do images too or?

## Understood Intent

"This" means copying images from another instance. Today `./v3 import-instance` copies posts and other text but no images. The maintainer then has to download the image ZIP by hand and run `./v3 images verify` and `./v3 images restore`. `images restore` refuses a destination that already has different images, so there is no supported way to add another instance's images to an existing store. The maintainer wants one command that brings images across the way `import-instance` brings text.

Problem: Imported posts show missing images, and no command copies another instance's images into a destination that already has its own.

Concepts touched: *instance import* (changed in every option); *image store* (used unchanged: `ImageStore::put` checks each file and enforces the 256 MiB / 1024 image limit); *image backup* (used unchanged: the source's `/downloads/images.zip`).

No matching feedback item in `docs/feedback/items/`.

## Option A - import-instance copies images automatically

**Estimate: 0.55**
Main driver: recovery when the image download fails, is absent or hits the store limit. Uncertainty: medium, mostly in time limits for large ZIPs.

After the text import, `import-instance` lists images that the destination's posts reference but its store lacks. It downloads `/downloads/images.zip` from the source with the existing downloader and reads it with the existing ZIP reader in `ImageRestore.php`. It adds only the listed images through `ImageStore::put`, which checks each file against its post's reference. It never overwrites an image. The report counts images added, already present and missing. `--dry-run` shows the counts without writing. If images cannot be fetched, the text import still completes and the command exits 2 with the reason. A rerun fills gaps left by earlier imports.

- Pro: nobody can forget the images, which matches how Fastmod scores already transfer.
- Pro: images from removed posts stay out, because only current references are copied.
- Pro: no matching archive revisions are needed, since each image is checked against its post.
- Con: every import now downloads the image ZIP, even when the operator wants text only.
- Con: a source with image backups turned off makes every import end as partial.

## Option B - the same copy behind an `--images` flag

**Estimate: 0.50**
Main driver: the same copy and recovery as Option A. Uncertainty: medium.

Option A's copy runs only when the operator passes `--images`. Without the flag, the import prints a short hint that images were skipped.

- Pro: text-only imports stay as fast as today.
- Pro: a source without image backups does not turn ordinary imports partial.
- Con: an operator who forgets the flag gets posts with missing images.
- Con: a later rerun with `--images` is needed to repair them.

## Option C - a separate `./v3 images import` command

**Estimate: 0.50**
Main driver: the same copy, plus a second command entry and its help. Uncertainty: medium.

A new `./v3 images import <source-url or ZIP>` adds a source's images to the destination's store using Option A's rules. `import-instance` stays text-only and prints the follow-up command.

- Pro: also accepts a ZIP already downloaded, so offline transfers work.
- Pro: keeps `import-instance` unchanged.
- Con: two commands for one transfer, which is what the maintainer asked to avoid.
- Con: an operator who skips the second command still gets missing images.

## Recommendation

Choose Option A. It is the only option where an ordinary import brings its images.

Option A is a complete vertical slice: one command, one report and one recovery path. Build the copy as a separate class so a later `images import` for local ZIPs (Option C) can reuse it. If Step 2 shows that partial results from sources without image backups are a problem, report a missing image download as a warning rather than a partial result.

*— Claude · 2026-11-06 UTC*

## Study checkpoint

2026-11-06T15:45:46Z, `railspike-effort` / `v3/instance_image_import`, intake and Step 1 handoff. Scope v1: Option A as recommended above. Starting revision `6efe5ba333ebbb1f63a474609e26c3d4da09509d`. Reference R1, protocol P2; frozen R1 hashes not re-verified for this checkpoint. Single exposed Claude rater. Before rating, the rater read `scripts/import_instance.php`, `FastmodScoreImport.php`, `InstanceArchiveDownloader.php`, `ImageStore.php`, `ImageRestore.php` and the image backup runbook in an earlier session. Pristine intake is missing. Intake and Step 1 projected total **0.55**. Option estimates 0.55, 0.50 and 0.50 are alternatives and do not add up. No implementation, measured labor or accepted outcome.

## Enhancement Requests

None yet.
