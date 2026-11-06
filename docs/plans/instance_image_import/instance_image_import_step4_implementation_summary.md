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

## Enhancement Requests

- Incremental sync (2026-11-06): separate work, recorded in [Step 2](./instance_image_import_step2_feature_description.md#enhancement-requests) and the [backlog](../backlog/incremental_instance_sync.md).
