# Backup Page Freshness Preview Step 2 Feature Description

## Problem

The public Backup page offers downloadable snapshots but does not tell users when the available artifacts were generated or what recent content they contain. Users need enough visible evidence to judge whether a download is current and complete before retrieving it.

## User stories

- As a forum user, I want to see when the backup artifacts were generated so that I can assess their freshness before downloading.
- As a forum user, I want to preview recent included items so that I can verify that the backup contains current forum content.
- As a forum maintainer, I want the preview and freshness metadata to describe the same backup snapshot so that the page does not create contradictory trust signals.

## Core requirements

- The Backup page displays a clear generated-at timestamp for the available backup snapshot.
- The Backup page displays a concise, ordered preview of recent included content items.
- The preview has explicit empty and limited-result states and does not imply that it is a complete archive listing.
- The existing repository-archive and SQLite download links remain available and understandable alongside the freshness information.
- `/backup/` and `/tools/backup/` provide the same freshness and preview experience.

## Shared component inventory

- **Backup page (`/backup/`, `/tools/backup/`)**: reuse and extend the canonical Backup page/template; both aliases already render the same download-oriented surface.
- **Repository archive downloads (`/downloads/repository.tar.gz`, `/downloads/repository.zip`)**: reuse unchanged as the downloadable content snapshot surfaces; expose their shared snapshot metadata on the Backup page.
- **SQLite read-model download (`/downloads/read_model.sqlite3`)**: reuse unchanged as the downloadable index snapshot; expose the shared freshness context on the Backup page.
- **Activity page and activity feed (`/activity/`, including content-filtered views and RSS)**: reuse or extend the existing recent-content representation for preview semantics rather than creating a parallel activity model. The preview should be bounded to the backup snapshot it describes.
- **Thread/listing views**: reuse existing content labels, titles, and links where the preview identifies included items; no new general-purpose listing component is needed.

## Simple user flow

1. The user opens the Backup page.
2. The page shows the backup snapshot's generated-at time and a short recent-items preview.
3. The user compares the preview and timestamp with their freshness expectations.
4. The user downloads the repository archive or SQLite database using the existing links.

## Success criteria

- Every Backup page alias shows the generated-at timestamp before download links.
- Every Backup page alias shows a bounded recent-items preview or an explicit empty state.
- The timestamp and preview remain internally consistent with the downloadable snapshot presented on the page.
- Existing backup download links and current backup-page smoke coverage continue to work.
