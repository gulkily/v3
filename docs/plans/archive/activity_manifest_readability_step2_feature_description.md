# Activity Manifest Readability — Step 2: Feature Description

## Problem

Activity cards repeat source/signature filenames above the commit manifest, and inline manifest paths are hard to scan.

## User Stories

- As an activity reader, I want each changed file path on its own line so that commit contents are easy to scan.
- As an activity reader, I want a file listed once per card so that the audit context is not repetitive.

## Core Requirements

- Put every non-public-key manifest file path on a new line beneath its status and role.
- Retain public-key filenames inline with `Public key:`.
- Omit source/signature file listings above the manifest when the manifest is present.
- Keep the same behavior and information in Classic Activity and Forte Activity.

## Shared Component Inventory

- `templates/partials/activity_commit_manifest.php` — extend as the shared file-list presentation for both views.
- `templates/pages/activity.php` and `templates/partials/paned_activity_detail_pane.php` — remove only the duplicated activity source/signature display while retaining their existing event context.
- `templates/partials/source_metadata.php` — retain unchanged for post pages.

## User Flow

1. A reader opens an activity item in either view.
2. The card shows its existing event context once.
3. The commit manifest lists each file on a separate, scannable line.

## Success Criteria

- A manifest path never appears inline with its status/role label.
- Source and signature paths are not duplicated above and below the manifest.
- Public-key paths remain inline after their label.
- Classic and Forte render the same result.

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.
