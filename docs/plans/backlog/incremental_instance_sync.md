# Incremental instance sync

Status: backlog. Requested by the maintainer on 2026-11-06 during the [instance image import](../instance_image_import/instance_image_import_step2_feature_description.md) cycle. Not planned or approved.

## Request

"In the future, we will need a way to get optimized sync. We can't be downloading the whole archive every time."

## Current behavior

- Every `./v3 import-instance` run downloads the source's full repository archive (`/downloads/repository.tar.gz`), even when the destination already has nearly all of it.
- After the instance image import cycle, a run also downloads the full image ZIP (`/downloads/images.zip`) whenever any referenced image is missing. A run with no missing images skips that download.
- The source's export cache avoids rebuilding the archive per request, but the bytes still cross the network in full.

## Goal

Repeated imports from the same source transfer only what changed since the last import: new or changed text records and the specific missing images.

## Questions for a future Step 1

- How the destination tells the source what it already has: the last imported revision, a list of hashes, or a date.
- Whether images can be fetched one at a time by hash instead of as one ZIP, and how removed-post images stay excluded.
- How a changed or rewritten source history is detected and falls back to a full download.
- Which access rules apply, since the full archive is public only when backups are turned on.
