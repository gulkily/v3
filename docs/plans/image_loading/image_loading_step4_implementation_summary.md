> **Feature plan:** [Step 1](./image_loading_step1_solution_assessment.md) · [Step 2](./image_loading_step2_feature_description.md) · [Step 3](./image_loading_step3_development_plan.md) · [Step 4](./image_loading_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/image_loading`; host ledger [image_loading.md](../effort_ledger/image_loading.md). Standing P2 enrollment.

Extended estimates: enabled. Stage values are stage work only; they are not summed into the cycle estimate.

**Cycle estimate: 0.90**

Branch `image-loading`, from `origin/main` `22fc9f1` with `a4a4aea` merged in. Planning commits: Steps 1–3 were committed as each was approved, ending with `ef0199e`; the ledger and debug log went into those commits, the same deviation from the planning-commit rule as zen_strip and zen_hero. Base: `./v3 test --smoke ImageContentTest MediaEmbedRendererTest GalleryPageTest BoardListTest ZenStripTest`, 514 run, 514 passed.

## Study snapshots

- 2026-11-22 UTC: **Approved Step 3** by the maintainer ("approved step 3").
- 2026-11-22 UTC, Step 4 entry: **0.90** projected total, reaffirmed from Step 3. Claude, single exposed rater. Scope v1. Stage originals copied from Step 3 (not new ratings): 0.10, 0.20, 0.15, 0.10, 0.30, 0.05.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*

## Stage 1 - Browsers keep served images for a year

**Original estimate: 0.10**

**Retrospective stage-work assessment: 0.10**

- Changes:
  - `ImageController`: a served original or copy is sent with `Cache-Control: private, max-age=31536000, immutable` (`ImageController::KEEP`). Every error answer keeps `private, no-store`.
  - New `tests/ImageServingTest.php`, registered in `tests/run.php`. It starts a local server and reads real response headers.
  - `tests/image_content_apache_test.py` expects the new header in place of `no-store`.
- Verification:
  - `./v3 test ImageServingTest`: 1 run, 1 passed. It covers an original, a `tile` copy, three missing addresses, and the image flag off. The full suite was not run.
  - **Not run:** `tests/image_content_apache_test.py`. Apache is not installed in this workspace.
  - **Pending:** the outside request to production after deploy, to see whether the host's own cache line weakens ours.
- Notes:
  - Nothing in the repository says why `no-store` was chosen, and the maintainer doesn't know either.
- Estimate details (extended mode only): original scope as Step 3 Stage 1; uncertainty low; no revisions.

*— Claude · 2026-11-22 UTC · Model: claude-fable-5-2*
