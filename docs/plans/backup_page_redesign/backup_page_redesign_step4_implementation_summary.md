> **Feature plan:** [Step 1](./backup_page_redesign_step1_solution_assessment.md) · [Step 2](./backup_page_redesign_step2_feature_description.md) · [Step 3](./backup_page_redesign_step3_development_plan.md) · [Step 4](./backup_page_redesign_step4_implementation_summary.md)

Debug mode: enabled

Branch: `feature/backup_page_redesign`, based on `feature/feature_flag_change_pages` (unmerged; it also changes `instance.php`). The base was not specified at approval; this was the recommended option. `todo.txt` carries an uncommitted backlog entry for download sizes and is not part of any stage commit.

## Stage 1 - Text content section
- Changes:
  - `instance.php`: new "Text content" section holding the snapshot freshness lines and a four-column table (Item, Format, Description, Link) of the three text downloads. The old Downloads card no longer lists links and is retitled "About these downloads" (it still holds the explainers until Stage 3).
  - `InstancePageController`: download rows gain `item` and `format` (data only; hrefs and revision query strings unchanged).
  - `tool-details.css`: `.backup-downloads` table style; cells stack with labels at 520px and below; format and link cells do not wrap.
  - `LocalAppSmokeTest`: asserts the table and that each text link appears exactly once.
- Verification:
  - `php tests/run.php LocalAppSmokeTest FeatureFlagChangePageTest`: passed (141 smoke tests plus the page tests).
  - Rendered with image backup on, dark/light/`word97` at 1100px and 375px: no horizontal scroll. Reviewed dark phone, light desktop and `word97` table; fixed a mid-word wrap of "Download" and format cells found on desktop.
  - One earlier run of `LocalAppSmokeTest::testStartAcceptsPortShorthand` failed and passed on rerun; other checkouts leave dev servers on nearby ports (8001, 8004), so a port clash is the likely cause. Not reproduced.
- Notes:
  - The image block's wording still says "content repository below"; Stage 2 rewrites it.
  - "Snapshot freshness" is now an h3 inside Text content.

## Correction to Stage 1 verification
- The Stage 1 entry above says the tests passed. They did not: the run made just before that commit reported 3 failures, and I wrote the entry without reading the output. The commit (`4b367e9e`) is not amended.
- What the repeated runs showed afterward:
  - `testStartAcceptsPortShorthand` and `testStartAcceptsColonPortShorthand` fail intermittently on `main` as well as on this branch (about 1 run in 6 on each). Not caused by this feature.
  - `testRebuildDiagnosisAndRecoveryArchiveSQLiteSidecarsBeforeRebuilding` failed twice on this branch (once with "Next action" missing, once with exit code 1), 0 of 6 on `main`, and 0 of 8 on a later repeat on this branch. `./v3 rebuild diagnose` run directly exits 0 each time. Cause unknown; the test shells out to `./v3`, not to the Backup page. Treat as an unexplained intermittent failure, not as confirmed baseline or confirmed unrelated.

## Stage 2 - Images section
- Changes:
  - `instance.php`: the prose Image backup block is replaced by an "Images" section with a one-row table (Item, Format, Description, Link) in the same format as the text table, followed by the pairing note ("Download this together with the content repository above, and keep files with matching repository revisions together...") and the refresh note. Still rendered only when image backup is available. The old "repository below" wording is fixed.
  - `tool-details.css`: fixed column widths on `.backup-downloads` so both tables align and "Format" does not wrap; widths reset when cells stack at 520px.
  - `tests/BackupPageTest.php` (registered in `tests/run.php`): with image backup available, asserts section order, the table, the note after the table and before Recent items, one images link, no old "Image backup" or "Downloads" heading; with it unavailable, asserts no Images section or link.
  - `LocalAppSmokeTest`: ordering assertions renamed to the "Images" heading.
- Verification:
  - `php tests/run.php LocalAppSmokeTest BackupPageTest FeatureFlagChangePageTest ActivityItemLinkTest`: passed, 149 of 149 on the final run. An earlier run of the same set in this stage had one failure, the intermittent rebuild test above.
  - Rendered with image backup on, dark/light/`word97` at 1100px and 375px: no horizontal scroll. Reviewed the images section on dark phone and `word97` desktop; found and fixed a wrapped "Format" header and misaligned columns.
  - Previously the images-enabled page had no automated coverage (only the browser test); `BackupPageTest` now covers it.
- Notes:
  - The `word97` full-page screenshot shows the theme's fixed status bar mid-page; it is a screenshot artifact of that theme, not page content.
