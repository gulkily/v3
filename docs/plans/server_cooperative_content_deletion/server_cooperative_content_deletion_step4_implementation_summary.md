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

## Stage 2 - Read-model suppression
- Changes:
  - Derived read model: nullable `retracted_at` on `posts` and `threads`; `ReadModelMetadata::SCHEMA_VERSION` 15 → 16 (the schema fingerprint changes, so live databases rebuild).
  - `ReadModelBuilder::indexPosts` loads `records/post-retractions/` and, for a retracted post, stores a null subject, empty body, and null event fields, so the thread row gets an empty subject and body preview. Reply rows, reply counts, and the thread's structure are untouched.
  - A retraction applies only when its `Author-Identity-ID` equals the post's author. Posts tagged `identity`, `approval`, or `invitation` are never suppressed, because trust state is derived from their bodies.
  - Activity label for a retracted post is `Deleted post`. A thread-subject record can no longer refill a retracted root's subject, in both the full rebuild and `IncrementalReadModelUpdater::applyThreadSubjectWrite`.
- Verification:
  - New `tests/ReadModelPostRetractionsTest.php` (registered in `tests/run.php`): a marker string is absent from every text column of every table after retraction; replies remain; a retraction signed by another identity is ignored; deleting then restoring the original post file stays suppressed.
  - Full `php tests/run.php`: 1113 run, 9 failed; all 9 are the same long-standing failures listed by the runner, none new.
- Notes:
  - Deviation from the plan: no dedicated incremental retraction path was added. Stage 3 will refresh derived state through the existing full-rebuild path (`refreshDerivedStateAfterCommit`); an incremental path can be added later if rebuild cost matters.
  - Search and feed filtering depend on the same `posts`/`threads` rows, so they are covered here; raw-record and static-artifact exposure is Stage 4.
  - Because the authority tags are exempt, Stage 3 must reject retraction requests for those posts rather than record a no-op tombstone.
