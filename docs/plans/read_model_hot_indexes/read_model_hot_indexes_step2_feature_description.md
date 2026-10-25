> **Feature plan:** [Step 1](./read_model_hot_indexes_step1_solution_assessment.md) · [Step 2](./read_model_hot_indexes_step2_feature_description.md) · [Step 3](./read_model_hot_indexes_step3_development_plan.md) · [Step 4](./read_model_hot_indexes_step4_implementation_summary.md)

Debug mode: enabled

Effort study: `railspike-effort` / `v3/read_model_hot_indexes`; [ledger](../effort_estimation_trial.md), standing enrollment P2.

Extended estimates: enabled (requested 2026-10-24, effective from Step 3).

## Problem

The board/tags/RSS listing and the single-thread reply query sort and filter on read-model columns that have no supporting index, so SQLite does full table scans and in-memory sorts on nearly every dynamic content page, with cost growing linearly as the instance accumulates posts and threads.

## User stories

- As a reader loading a thread permalink, I want the page to return quickly even on a large instance, so that reading does not get slower as the forum grows.
- As a reader browsing the board, tags, or RSS, I want the listing to render without scanning every thread, so that navigation stays responsive.
- As an operator deploying this change, I want the read model to rebuild automatically and safely, so that I do not have to run manual recovery steps.
- As a contributor, I want the schema change to follow the repository's read-model checklist, so that deployed read models rebuild correctly before the changed queries run.

## Core requirements

- Add indexes supporting the hot queries: `posts(thread_id, sequence_number)`, `posts(author_identity_id)`, and `threads(last_activity_at DESC, root_post_id DESC)`.
- The board, tags, RSS, and single-thread flows must render byte-identical content to before (indexes change performance, never results).
- Follow the `CONTRIBUTING.md` read-model schema-change checklist: bump `ReadModelMetadata::SCHEMA_VERSION`, update `docs/specs/read_model_schema_v1.md`, and add/update a regression test that starts from the prior schema version and verifies normal startup rebuilds before a changed query runs.
- A stale/older read model must trigger an automatic rebuild on normal startup; no manual repair step is required for the ordinary path.
- `./v3 rebuild` succeeds and the new indexes are present and used by the target queries.

## Delivery scope

- Work type: **application change** (read-model schema + supporting test + spec update).
- Not documentation-only: application source (`ReadModelSchema`, `ReadModelMetadata`), a regression test, and the schema spec are all modified.

## Completion boundary

- Normal entry: a reader opens the board/tags/RSS or a thread permalink; an operator deploys and the read model rebuilds on first startup.
- End-to-end outcome: those pages render identical content, with the target queries served via the new indexes instead of full scans + filesorts.
- Recovery: if a deployed read model is stale or schema-mismatched, normal startup rebuilds it automatically; the existing rebuild-failure errors (write/permissions/cache) remain the fallback path and are unchanged.
- Release condition: schema-change checklist complete, regression test green, `./v3 rebuild` verified, and the affected UI/API flows confirmed unchanged in content.

## Known user or environment

No specific end user, machine, or environment is named in the request; this item is omitted.

## Risks

- **Results drift:** an index should never change query output, but a malformed `ORDER BY`-matching index or a query edit could. Earliest validation: `EXPLAIN QUERY PLAN` plus before/after content comparison on board/tags/RSS/thread. Mitigation: indexes only, no query rewrites in this cycle.
- **Rebuild not triggered on deploy:** if the version/fingerprint gate does not actually force a rebuild, deployed instances could run new queries against an old-shaped model. Earliest validation: the prior-version rebuild regression test. Mitigation: bump `SCHEMA_VERSION` (changing `statements()` also changes the fingerprint) and test the gate directly.
- **Rebuild cost / larger read model:** adding indexes slightly increases rebuild time and file size. Earliest validation: `./v3 rebuild` on a representative model. Mitigation: three narrow indexes only; no covering-index tuning.

## Shared component inventory

- `ReadModel/ThreadRepository.php` — canonical owner of the board listing (`boardRowsSql`), single-thread row (`byId`), and reply queries (`allReplyPostsByThreadId`, `replyPostsByThreadId`); the queries that benefit. Reused, not changed in shape.
- `ReadModel/ReadModelSchema.php` — canonical schema statement list; the index definitions are added here (reuse the existing `statements()` surface).
- `ReadModel/ReadModelMetadata.php` — canonical schema-identity/version source; `SCHEMA_VERSION` bumped here.
- `Offline/PublicOfflineSnapshotBuilder.php` — already defines `posts_thread_sequence_idx`; the main read model is simply brought in line with it. No change needed there.
- No new component is introduced.

## Simple user flow

1. Operator deploys the updated code (or runs `./v3 rebuild`).
2. On first normal startup, the schema version/fingerprint mismatch triggers an automatic read-model rebuild, which now creates the new indexes.
3. A reader opens the board, a tag page, the RSS feed, or a thread permalink.
4. SQLite serves the listing sort and the reply query via the new indexes.
5. The page renders the same content as before, without a full scan + filesort.

## Success criteria

- `EXPLAIN QUERY PLAN` shows the new indexes used for the board sort (`threads.last_activity_at`) and the reply query (`posts.thread_id` + `sequence_number`); no full `SCAN` + `USE TEMP B-TREE FOR ORDER BY` on those paths.
- Board, tags, RSS, and a thread permalink render content identical to pre-change output.
- The prior-version regression test confirms normal startup rebuilds before the changed query runs.
- `./v3 rebuild` succeeds and the affected focused read-model tests pass.

## Effort snapshot and approval

- Cycle `v3/read_model_hot_indexes`, scope v1; starting revision `611bcbb5` (current `main`, Step 1 docs committed); reference R1 / protocol P2; single exposed rater; UTC 2026-10-24.
- Step 2 handoff projected total: **≈0.30 reaffirmed** (unchanged from Step 1). Scope is unchanged — the three indexes plus the mandatory schema-change checklist; no new requirements surfaced. Single exposed, non-blind rater; not measured effort, an approval, or a completed outcome.
- Documentation boundary includes this cycle's plans/debug log, later index organization, and ledger bookkeeping. Planning writes remain uncommitted.
- Await `Approved Step 2` before Step 3; requirements may be revised without advancing.
