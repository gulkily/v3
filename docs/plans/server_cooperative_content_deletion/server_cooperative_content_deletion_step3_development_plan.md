> **Feature plan:** [Step 1](./server_cooperative_content_deletion_step1_solution_assessment.md) · [Step 2](./server_cooperative_content_deletion_step2_feature_description.md) · [Step 3](./server_cooperative_content_deletion_step3_development_plan.md) · [Step 4](./server_cooperative_content_deletion_step4_implementation_summary.md)

Debug mode: enabled

# Server Cooperative Content Deletion Step 3 Development Plan

## Completion Contract

- Normal entry: a signed-in author opens their own post and chooses Retract; the browser signs a retraction intent.
- Outcome: the server verifies the author's signature, commits a signed `post-retraction` record (the tombstone, no prose), suppresses the post on every read surface, rebuilds derived state, and shows the author a status.
- Recovery: if the record commits but derived rebuild fails, the status shows "partly failed" and Retract can be retried idempotently; operators can see the stale marker.
- Deployment/external verification: none beyond the local app (`./v3 start`); no schema change to canonical records, only a derived read-model column.
- Release condition: every Step 2 success criterion passes in tests, including re-import of the original archive.

## Key Risks

- **High risk:** the original post file stays in the repo, so any raw-record route could still serve the prose. Validate in Stage 4 by requesting the raw path; mitigate with a suppressed response.
- **High risk:** resurrection via import/restore. Mitigate by applying the tombstone at read-model indexing, so every ingest path is covered; validate in Stage 2 before any UI work.
- Authority: only the post's own signing identity is accepted; others rejected in Stage 3 before any write.
- Thread-root retraction can break navigation; the root keeps its row with an empty subject and placeholder (Stages 2, 5).
- Weak promise misread as erasure; copy is fixed and reviewed in Stage 6.
- Derived read-model column forces a rebuild on deploy; it is derived state, rebuilt automatically.

## Stage 1
- Goal: Define the retraction record family so it is parsed, path-validated, and importable.
- Dependencies: none.
- Expected changes: new `PostRetractionRecord` and parser (headers: Record-ID, Created-At, Post-ID, Operation `retract`, Author-Identity-ID; no body prose); `CanonicalPathResolver::postRetraction(recordId)` under `records/post-retractions/`; `CanonicalRecordRepository::loadPostRetraction`; register the family in `SourcePathValidator`, `ArchiveRecordCatalog` (identity key `post-retraction:<id>`, dependency on the post), and `ContentImportPlanner`.
- Verification approach: parser unit tests and a planner test showing the record is imported with its detached signature.
- Risks or open questions:
  - Impact: a path the validator rejects would block import.
  - Early warning / validation: planner test in this stage.
  - Mitigation: mirror the `post-reactions` registrations exactly.
- Canonical components/API contracts touched: `Canonical/*`, `Import/ArchiveRecordCatalog.php`, `Import/ContentImportPlanner.php`.

## Stage 2
- Goal: Suppress retracted posts in the read model so no ingest path can resurrect them.
- Dependencies: Stage 1.
- Expected changes: `ReadModelBuilder::indexPosts` loads retraction records and indexes a retracted post with empty subject/body and a derived `retracted_at` column on posts and threads (`ReadModelSchema`); excluded from body previews, search, activity text, and tag/score derivation; replies untouched; `IncrementalReadModelUpdater` gains a retraction-write path with rebuild fallback.
- Verification approach: tests building a fixture repo with a retraction, asserting no prose in any table, replies intact, and identical results after deleting and re-importing the original post file.
- Risks or open questions:
  - Impact: prose left in FTS or activity tables.
  - Early warning / validation: scan every table for a marker string in tests.
  - Mitigation: suppress at insert time, not at render time.
- Canonical components/API contracts touched: `ReadModel/ReadModelBuilder.php`, `ReadModelSchema.php`, `IncrementalReadModelUpdater.php`.

## Stage 3
- Goal: Accept a signed retraction from the post's author only, and report status.
- Dependencies: Stage 2.
- Expected changes: `LocalWriteService::prepareRetraction(array $input): array` and `finalizePreparedRetraction(array $input): array`, following `prepareApproval`/`finalizePreparedApproval`; checks post exists, `author_identity_id` equals the post's author, signature verifies via `publicKeyForIdentity`; writes record and signature, commits, syncs derived state; idempotent on repeat. Routes `/api/prepare_retraction` and `/api/create_prepared_retraction` in `Application`. Response carries `status` of `complete` or `partial` with the failed step.
- Verification approach: tests for author success, non-author rejection with no file written, bad signature, already-retracted retry, and forced rebuild failure producing `partial`.
- Risks or open questions:
  - Impact: wrongful or blocked retraction on multi-key authors.
  - Early warning / validation: key-case enumeration test.
  - Mitigation: accept only the identity that signed the post.
- Canonical components/API contracts touched: `Write/LocalWriteService.php`, `Application.php` routes.

## Stage 4
- Goal: Close non-template leaks: APIs, search, feeds, raw records, exports, static artifacts.
- Dependencies: Stage 3.
- Expected changes: audit `/api/get_post`, `get_thread`, `list_index`, activity APIs, search, feeds, and the raw canonical record route; serve a "retracted" response in place of the original post file; rebuild or invalidate affected static artifacts via `StaticArtifactInvalidator`.
- Verification approach: tests requesting each surface and asserting the marker string is absent, plus an export check.
- Risks or open questions:
  - Impact: an unlisted surface still serves prose.
  - Early warning / validation: grep for `records/posts` readers and reconcile against the audit list.
  - Mitigation: note any deferred surface in the Step 4 summary rather than shipping silently.
- Canonical components/API contracts touched: `Application.php`, `Host/StaticArtifactBuilder.php`, `Write/StaticArtifactInvalidator.php`, API handlers.

## Stage 5
- Goal: Render a deleted-post placeholder everywhere posts appear.
- Dependencies: Stage 2.
- Expected changes: one shared placeholder partial used by the canonical post/event block in `thread.php`, `post.php`, `message.php`, `board.php`, `tag.php`, `profile.php`, `username.php`, `activity.php`, and `forte_*` views; retracted thread roots show the placeholder with replies below.
- Verification approach: smoke tests for each template with a retracted root and a retracted reply; manual check of one paned view.
- Risks or open questions:
  - Impact: forked markup drifting between templates.
  - Early warning / validation: only the partial contains placeholder text.
  - Mitigation: extend the existing block rather than adding variants.
- Canonical components/API contracts touched: `templates/pages/*`, `templates/partials/*`.

## Stage 6
- Goal: Give the author the Retract action and an honest status display.
- Dependencies: Stages 3 and 5.
- Expected changes: Retract control shown only on the viewer's own non-retracted posts; `browser_signing.js` gains a retraction flow (prepare, sign, finalize) with confirmation copy stating that Git history, backups, and others' copies are not erased; status line for complete, partial (with Retry), and failure.
- Verification approach: smoke test for control visibility by viewer, copy assertions, and a manual browser run of the full flow.
- Risks or open questions:
  - Impact: false assurance from the wording.
  - Early warning / validation: copy review by the user at Step 4 handoff.
  - Mitigation: fixed wording reused from this stage's single string.
- Canonical components/API contracts touched: `public/assets/browser_signing.js`, `templates/pages/post.php`, `thread.php`.

## Stage 7
- Goal: End-to-end verification and documentation.
- Dependencies: Stages 1–6.
- Expected changes: an integration test covering the Step 2 success criteria in one flow (retract, surfaces clean, replies present, non-author rejected, re-import stays suppressed); reference docs note the retraction API and its distinction from `delete-record`.
- Verification approach: full `php tests/run.php`; known unrelated failures recorded, not fixed.
- Risks or open questions:
  - Impact: pre-existing flaky tests masking regressions.
  - Early warning / validation: compare against a `main` baseline.
  - Mitigation: record the baseline in the summary.
- Canonical components/API contracts touched: `tests/*`, `docs/reference/v3_cli.md` and API reference.
