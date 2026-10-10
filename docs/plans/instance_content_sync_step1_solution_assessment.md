> **Feature plan:** [Step 1](./instance_content_sync_step1_solution_assessment.md) · [Step 2](./instance_content_sync_step2_feature_description.md) · [Step 3](./instance_content_sync_step3_development_plan.md) · [Step 4](./instance_content_sync_step4_implementation_summary.md)

## Original Query

From the TODO file:

> Make it easy to import/sync content from another instance.
> - Make this schedulable too.

Please write Step 1 of `docs/fdp/FEATURE_DEVELOPMENT_PROCESS.md`.

## Understood Intent

An instance operator should be able to bring another instance's content into their own. The user approved Option A first and saved Option B separately for later review. On approval, they deferred scheduling to a separate request and requested a command accepting an instance name/URL, with a possible web UI. This updated scope supersedes the scheduled first release originally proposed below.

## Problem Statement

Existing [archive import](../../scripts/import_repository_archive.php) and [background queue](../reference/v3_cli.md#manage-the-background-task-queue) capabilities do not yet provide a convenient, repeatable workflow from a remote instance through to visible local content.

## Option A — Scheduled archive pull and additive import

Let an operator save a source instance, preview and run an import from its repository download, and enable recurring pulls through the existing background queue.

- **Pros:** Reuses existing downloads, duplicate detection, conflict reporting, and worker operations; can avoid read-model schema changes.
- **Cons:** Repeated full downloads grow costly; changed records require review; the current importer needs tighter content boundaries and failure recovery before unattended use.

## Option B — Incremental content sync API

Provide a remote change feed that the destination can consume manually or on a schedule, with explicit rules for additions, edits, and deletions.

- **Pros:** Efficient recurring transfers; clearer support for evolving content and resumable progress.
- **Cons:** Requires a new cross-instance contract and compatible source deployments; authentication, retention, and conflict semantics expand the first release substantially.

## Option C — Git-based repository replication

Have operators connect a remote content repository and schedule fetch-and-merge operations, then refresh the local site's content views.

- **Pros:** Uses the existing canonical repository and transfers changes efficiently while retaining history.
- **Cons:** Requires remote Git access and merge recovery; repository-wide replication can mix content with instance settings and trust decisions; harder to make approachable.

## Recommendation

**Option A is approved with scheduling deferred:** deliver on-demand, one-way additive import from a compatible public instance by name/URL. Remote edits become reported conflicts and remote deletions do not delete local content. Incremental sync remains a separate future FDP cycle.

- **Complete vertical slice:** An operator supplies a source name/URL, optionally previews the import, downloads and imports in one command, sees the result locally, and inspects conflicts or recovers a failed run. Prevent overlapping imports.
- **Content boundary:** Step 2 defines the requested “all the data” coverage, including associated public content beyond threads/replies, while distinguishing content merging from full-instance restoration.
- **Validate early:** Confirm archive compatibility, record validation, and visibility under local trust rules. Prove repeat runs avoid duplicates and interrupted imports or failed publication recover without leaving permanently stale content.
- **Scope viability:** Target one day/eight development stages by reusing the importer. Scheduling and incremental sync are deferred; Step 2 assesses the optional web UI separately from the required CLI outcome.
