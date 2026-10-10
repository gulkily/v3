> **Feature plan:** [Step 1](./instance_content_sync_step1_solution_assessment.md) · [Step 2](./instance_content_sync_step2_feature_description.md) · [Step 3](./instance_content_sync_step3_development_plan.md) · [Step 4](./instance_content_sync_step4_implementation_summary.md)

## Problem

Operators currently download a repository archive separately before importing it, and existing import coverage is incomplete. They need one command accepting an instance name/URL that brings its forum content into the destination and makes the result available locally.

## User Stories

- As an operator, I want to supply an instance name/URL so that downloading and importing require one command.
- As an operator, I want a preview, progress, and actionable results so that I can inspect coverage, resolve conflicts, and recover failures.

## Core Requirements

- Accept a full instance URL, hostname, or explicitly configured name alias; show the resolved source and destination. Reject unknown aliases with guidance rather than guessing a site's address. Preserve local-archive import.
- Download the source's repository archive and merge all supported public forum content: threads/replies/quotes, labels/tags, subject changes, reactions, and attribution/public-key/signature records. Preserve identifiers, relationships, authorship, timestamps, and signed content. Report every excluded, unsupported, invalid, or conflicting category; never label a partial result as a complete import.
- Proposed interpretation of “all”: merge content while retaining destination settings and approval authority. Exclude private messages, approval/invitation authority, instance settings, source Git history, and derived databases from the merge. Authenticated sources and whole-instance restoration are outside this release.
- Provide an optional preview that leaves destination content unchanged; skip identical records and retain local versions on conflicts. Never propagate source deletions. Report imported, duplicate, excluded, and conflicting counts with review locations.
- Refresh local content views before reporting success; prevent overlapping mutations and provide actionable download, validation, import, and publication failures. Retrying must avoid duplicates and recover unfinished publication even when no new records remain.

## Delivery Scope and Completion Boundary

**Work type: application change.** CLI first is the recommendation for review; a web UI remains optional and is deferred in this draft. Scheduling and incremental sync are explicitly deferred. Release requires the documented command to download, import, and publish supported content end to end, with verified preview, repeat-run, conflict, and failure recovery behavior.

## Risks

- **Coverage/trust:** Missing records or imported authority could change meaning or permissions. Before Step 3, audit record families and approval-bearing content; agree exclusions and validate destination trust behavior.
- **Remote archives:** Incompatible layouts, unsafe entries, or excessive downloads could fail or affect local files. Validate representative exports early; require bounded transfers and archive validation before destination changes.
- **Partial completion:** Interrupted imports or failed refreshes could leave stale views. Before Step 3, establish a recovery contract covering both durable content and publication, including concurrent local writes.

## Shared Component Inventory

- Extend the existing archive importer and CLI help/reference with remote-source resolution, download, coverage, and result reporting.
- Reuse the source Backup/Instance page's repository download; no new source API.
- Reuse canonical readers and publication paths feeding board/quote lists, thread/post pages, profiles/users, tags, activity/feeds, read APIs, and configured static/offline views; no separate imported-content rendering.
- A later web entry belongs in Tools/Backup and should reuse the same import workflow and results.

## Simple User Flow

1. Supply the instance name/URL and optionally preview.
2. Run the command; follow download, import, and publication progress.
3. Inspect local content and the result summary; review conflicts or follow recovery guidance.

## Success Criteria

- One invocation imports the agreed content set and exposes eligible content through normal local views.
- Repeating an unchanged import adds zero duplicates; preview changes zero destination records.
- Conflicts retain local content; failures identify recovery; no category is silently omitted.
