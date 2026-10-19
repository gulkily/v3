> **Feature plan:** [Step 1](./backup_page_redesign_step1_solution_assessment.md) · [Step 2](./backup_page_redesign_step2_feature_description.md) · [Step 3](./backup_page_redesign_step3_development_plan.md) · [Step 4](./backup_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

# Step 1: Solution Assessment — Backup Page Redesign

## Original Query

We need to redesign the backup page to have images as a separate thing. Right now they're mixed in with the text, and then the text file links are in their own module, which makes no sense.

## Understood Intent

On `/tools/backup/`, the image archive link sits inside the same section as the text-content description, while the text-content download links live in a separate Downloads card further down. Organize the page by what is being backed up (text content vs images), each with its own downloads, instead of by block type. Download links and backup logic stay as they are.

## Problem

The page splits one backup (text content) across two modules and folds the other (images) into prose, so users can't tell which files make up each backup.

## Options

### Option A: Two sections by backup type

"Text content" holds freshness, the repository and SQLite download links, and its explanatory text; "Images" holds the image archive link and the revision-pairing note. The recent-items preview and the long explainers move to the end.

- Pros: each backup's links and context sit together; smallest change; keeps one page and URL.
- Cons: the long "why/ELI5/technical" text still needs a home and may crowd the page.

### Option B: Separate sub-pages under Backup

`/tools/backup/` becomes a short overview linking to a Text content page and an Images page, each with its own downloads and notes.

- Pros: clearest separation; room for image-specific detail and restore guidance.
- Cons: more navigation for a two-item task; new routes, nav and tests; the pairing requirement is easy to miss across pages.

### Option C: One download table with a type column

A single table of artifacts (type, format, revision, link), with Images visually separated and explanatory text collapsed below.

- Pros: all links scannable at once; very compact.
- Cons: weak fit for the "keep matching revisions together" guidance; tables suit narrow phone widths poorly.

## Revised Direction

User chose **Option A**, plus a compatible part of Option C: each section presents its downloads as a table.

- **Text content** section: table of the repository (.tar.gz), repository (.zip) and SQLite index downloads, with columns for item, format, description and link.
- **Images** section: a table in the same format with a single row (the image archive) for now, leaving room for more rows later.
- The revision-pairing note stays at the images section; the table columns match so the two read as one system.
- Phone width needs a check (tables can overflow), which belongs in Step 2 risks.

## Recommendation

Choose **Option A with per-section download tables** as one vertical slice: the user opens Backup, finds two clearly labeled sections, and downloads each backup's files from a table in the same place. Option B can follow later if image guidance grows.

## Enhancement Requests

- **Request:** Move Image backup below the tab bar and align styling with Feature Flags. **Disposition:** done on `fix/backup-page-tools-nav-order` (`73e1f121`); this redesign builds on that branch's template. **Approval status:** n/a. **Outcome:** the redesign supersedes the section order from that commit.

## Approval Gate

Reply **Approved Step 1** to proceed to the feature description.
