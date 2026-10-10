> **Feature plan:** [Step 1](./instance_incremental_sync_step1_solution_assessment.md) · [Step 2](./instance_incremental_sync_step2_feature_description.md) · [Step 3](./instance_incremental_sync_step3_development_plan.md) · [Step 4](./instance_incremental_sync_step4_implementation_summary.md)

## Original Query

> I would like to implement Option A, and then separately, Option B. Please add a second Step 1 document.

## Understood Intent

Deliver the [scheduled archive import feature](./instance_content_sync_step1_solution_assessment.md) first, then implement its Option B—an incremental content sync API—as a separate feature. Both address the original TODO: “Make it easy to import/sync content from another instance” and “make this schedulable too.” The options below compare approaches within that selected follow-up.

## Problem Statement

Recurring archive imports repeatedly transfer the full source collection and cannot efficiently identify remote changes, so operators need incremental synchronization that preserves local content and recovers from interruptions.

## Option A — Paginated content inventory and selective retrieval

Expose an inventory of eligible record identities and revisions; the destination compares it with prior observations and retrieves only new or changed records on each manual or scheduled run.

- **Pros:** Avoids full content downloads; does not require a retained change history; accommodates instances without shared repository history.
- **Cons:** Repeatedly scans the inventory; needs consistent pagination; a missing record cannot safely mean deletion unless the inventory is complete and its visibility scope is unchanged.

## Option B — Resumable change feed with archive bootstrap

Expose ordered additions, revisions, and explicit removals through a versioned API; the destination resumes from its last completed position and uses an archive baseline for initial import or recovery from expired history.

- **Pros:** Transfers only changes during normal operation; makes removals explicit; supports bounded runs and builds on the first feature's archive workflow.
- **Cons:** Requires stable progress markers, retention/reset rules, and a baseline tied to a feed position; compatible source deployments must provide the new API.

## Recommendation

**Option B within this assessment**, implemented only after the archive feature is delivered. Reuse its source setup, schedule controls, import validation, status, and recovery workflow; do not require the first release to implement the feed.

- **Complete vertical slice:** For one compatible public source, an operator enables incremental mode, establishes a baseline, runs or schedules updates, sees imported additions locally, reviews changed/removed records, and resumes interrupted work. Archive-only sources keep their existing workflow.
- **Change policy:** Import additions without duplication; report divergent revisions and removals for review without automatically overwriting or deleting local records. Preserve the first feature's content, identity, privacy, and local trust boundaries; bidirectional sync and authenticated sources remain separate scope.
- **Validate early:** Prove a baseline-to-feed handoff cannot miss changes, interrupted batches can replay safely, progress includes durable conflict outcomes, and source resets or expired history trigger explicit recovery. Distinguish remote removal from access loss.
- **Scope viability:** Target one day/eight stages for this complete flow using the delivered archive foundation. Reassess scope before further planning if source history or recovery makes that infeasible; defer automatic edit/deletion reconciliation independently.
