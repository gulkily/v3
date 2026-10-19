> **Feature plan:** [Step 1](./backup_page_redesign_step1_solution_assessment.md) · [Step 2](./backup_page_redesign_step2_feature_description.md) · [Step 3](./backup_page_redesign_step3_development_plan.md) · [Step 4](./backup_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

# Step 2: Feature Description — Backup Page Redesign

Approved direction: Step 1 Option A (two sections by backup type) with a download table in each section.

## Problem

On `/tools/backup/` the image archive link sits in prose, while the text-content download links sit in a separate Downloads module further down, so a reader cannot tell which files make up each backup.

## User stories

- As an operator, I want text-content downloads and image downloads in separate, labeled sections so that I can back up each kind deliberately.
- As an operator, I want each section's downloads in a table (item, format, description, link) so that I can compare and pick files at a glance.
- As an operator, I want the "keep matching revisions together" guidance next to the images table so that I do not restore mismatched files.

## Core requirements

- Two sections, "Text content" and "Images", each holding its own download table and its explanatory text.
- Text table: repository (.tar.gz), repository (.zip), SQLite index. Images table: the image archive only, in the same columns.
- The image section appears only when image backup is available, as today; text-content section is unaffected by that.
- Download URLs, revision query strings and all backup logic are unchanged.
- Freshness, recent items and the long explainers stay on the page, after the two sections.

## Delivery scope

- Work type: application change (template, styling and tests; no controller logic or route changes).

## Completion boundary

- Normal entry: open Tools, then Backup.
- End-to-end outcome: both sections render with working download links; downloaded files match today's.
- Recovery: with image backup unavailable the page shows only the text section with no empty images section; Backup stays usable.
- Release condition: smoke and browser image-backup tests pass; checked in dark, light and one retro theme at phone width.

## Risks

- **Tables overflow at phone width** — impact: unreadable or sideways-scrolling page. Earliest validation: render the existing table style at 375px in Stage 1. Mitigation: reuse the `codebase-facts` pattern or stack rows on narrow screens.
- **Browser test selectors** — impact: `image_content_browser.mjs` locates links by `href` prefix; layout changes could break it. Validation: run it after the template change. Mitigation: keep hrefs and anchors unchanged.
- **Pairing guidance lost** — impact: mismatched image and repository restores. Validation: acceptance check that the note sits beside the images table. Mitigation: keep the note with the table; do not collapse it.
- **Template conflict** — impact: `instance.php` also changes on `feature/feature_flag_change_pages` (Stage 5). Mitigation: base this feature on that branch or merge it first.

## Shared component inventory

- Backup page (`instance.php`, `InstancePageController`): extend; reorganize, keep the data it already supplies.
- Codebase page downloads list (`CodebaseStateController`, `codebase_state.php`): shows the same three text downloads. Not changed; revisit for consistency later.
- Table styling (`codebase-facts` in `tool-details.css`): reuse; extend only if phone width requires.
- Section headings and notices (`feature-flag-group-heading`, `feature-flags-notice`): reuse.
- Image backup runbook: unchanged.

## Simple user flow

1. User opens Backup.
2. Text content section: reads freshness, picks files from the table.
3. Images section: reads the pairing note and downloads the archive.
4. User scrolls to recent items and the explainers if wanted.

## Success criteria

- Every download link appears exactly once, inside its own section's table.
- No standalone Downloads module remains.
- The pairing note is adjacent to the images table.
- No horizontal page scroll at 375px in the three checked themes.
- Smoke tests and the browser image-backup test pass.

## Enhancement Requests

- **Request:** Per-section download tables with one image row for now. **Disposition:** in scope (Step 1 revision). **Approval status:** approved in Step 1. **Outcome:** Step 3.

- **Request:** Show download sizes in the tables. **Disposition:** separate work; exact sizes are unknown for generated repository archives and need controller logic outside this scope. **Approval status:** deferred by the user. **Outcome:** backlogged in `todo.txt` ("backup page: download sizes").

## Approval Gate

Reply **Approved Step 2** to proceed to the development plan.
