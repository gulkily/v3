> **Feature plan:** [Step 1](./server_cooperative_content_deletion_step1_solution_assessment.md) · [Step 2](./server_cooperative_content_deletion_step2_feature_description.md) · [Step 3](./server_cooperative_content_deletion_step3_development_plan.md) · [Step 4](./server_cooperative_content_deletion_step4_implementation_summary.md)

Debug mode: enabled

# Server Cooperative Content Deletion Step 4 Implementation Summary

## Plan clarifications

- Stage 6 control placement: the Retract control goes in `partials/post_card.php` and `partials/thread_root_card.php` (what `thread.php` delegates to), plus `post.php`. Paned forte views are not part of the first slice. This refines file names only; Stage 6's scope and the Completion Contract are unchanged.

## Stage 1 - Retraction record family
- Changes:
  - New `PostRetractionRecord` and `PostRetractionRecordParser`: headers Record-ID, Created-At, Post-ID, Operation `retract`, Author-Identity-ID; empty body required; Record-ID must equal `post-retraction-<Post-ID>`, which makes a retraction one-per-post and idempotent.
  - `CanonicalPathResolver::postRetraction`, `CanonicalRecordRepository::loadPostRetraction` (path must match Record-ID).
  - Registered `records/post-retractions/` in `SourcePathValidator`, `ArchiveRecordCatalog` (record path, identity key `post-retraction:<id>`), and `ContentImportPlanner` (depends on the post and the author identity).
- Verification:
  - `php tests/run.php CanonicalRecordParsersTest` and `ContentImportPlannerTest`: all pass, including new parser, repository path-mismatch, resolver, and planner tests (import with signature; missing post reported as invalid).
  - Full suite: no failures attributable to this stage. Pre-existing failures are unchanged. `TestRunnerBehaviorTest::testInterruptedRunReportsCurrentSlowTest` failed once in the full run, passed in isolation, and failed once on stashed `main`, so it is timing-flaky.
- Notes:
  - Retraction records are not excluded from import (unlike approval seeds), so a re-import carries the tombstone; suppression itself is Stage 2.
  - Signature file convention (`.asc`) already covered by `ArchiveRecordCatalog::isSignaturePath`; the signing flow is Stage 3.
