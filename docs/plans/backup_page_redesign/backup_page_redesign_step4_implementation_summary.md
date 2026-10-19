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
