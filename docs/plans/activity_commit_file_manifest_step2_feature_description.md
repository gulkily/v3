# Activity Commit File Manifest — Step 2: Feature Description

## Problem

An activity event represents one record, but its commit can change several files. Readers of Classic Activity and Forte Activity cannot currently inspect that complete atomic change or reliably find the signing key for a signature it includes.

## User Stories

- As a community member, I want every file changed by an activity item's commit listed in the activity view so that I can audit the complete change.
- As a reader, I want each listed file identified by its change status and canonical role so that filenames have useful context.
- As a signature verifier, I want a listed signature accompanied by its signing identity's public key so that I can inspect the key even when it was committed earlier.

## Core Requirements

- For every activity item with an available source commit—regardless of activity kind—enumerate every changed file exactly once, including additions, modifications, deletions, and renames.
- Show each file's repository path, change status, and a concise observable canonical role; link only files permitted by existing source-access rules.
- When the manifest includes a detached signature, show its signer identity and canonical public-key link independently of whether the public-key file changed in that commit; show an explicit unavailable state when no key can be resolved.
- Classic Activity and Forte Activity must expose the same manifest information for the same event.
- Preserve existing activity links, source metadata, approval gating, and graceful behavior for missing/unavailable commits.

## Shared Component Inventory

- `Application::fetchActivity()` — canonical activity data source; extend it with one shared manifest model for both views.
- `templates/pages/activity.php` — Classic Activity consumer; use the new activity manifest component.
- `templates/partials/paned_activity_detail_pane.php` — Forte Activity consumer; use the same new component.
- `templates/partials/source_metadata.php` — existing source/signature summary also used by post pages; retain unchanged to avoid expanding post-page scope.
- `/source/commits/{sha}` — existing complete commit-file source; extend/reuse its authoritative file data rather than creating another commit interpretation.

## User Flow

1. A reader opens Classic Activity or Forte Activity and selects an event.
2. The event shows its complete commit file manifest.
3. The reader follows an allowed file link or the signing identity's public-key link.
4. The reader can assess the full atomic change without switching to an incomplete view.

## Success Criteria

- Representative multi-file commits across activity kinds show all changed paths in both Activity views, not only each event's source path.
- Both views show identical paths, statuses, roles, and signature/public-key associations for the same commit.
- A signature whose public key predates the commit still exposes that key's link.
- Missing commits, deleted files, and unresolved keys remain understandable and do not break either feed.

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.
