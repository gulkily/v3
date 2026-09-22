# Step 4: Implementation Summary — Board/Activity Carry-Back (+ Board Score column)

## Stage 1 - Relative-date rows for Board and Activity
- Changes:
  - `templates/partials/paned_board_thread_list.php`: list-row date span now calls `$relativeTimestamp(...)` instead of `$timestamp(...)`.
  - `templates/partials/paned_activity_item_row.php`: same swap for the activity item row date span.
  - `templates/partials/paned_activity_commit_row.php`: same swap for the activity commit row date span.
- Verification:
  - `php -l` on all three edited files — no syntax errors.
  - Grepped the three files for remaining `$timestamp(` calls — none left, confirming full swap with no missed full-format usage on these rows.
  - Started a throwaway local server on `127.0.0.1:8010` against this working tree (the already-running `127.0.0.1:8000` process was serving a different checkout at `/home/wsl/v3`, not this repo — left untouched) and curled `/forte` and `/forte/activity/`: both now render `<time datetime="..." title="Sep 20, 2026 at 01:28 UTC">2 days ago</time>` in place of the old full-format text. Test server stopped after verification.
- Notes:
  - `$relativeTimestamp` is registered globally in `TemplateRenderer::renderFile()`, so no wiring was needed beyond the template edits.
  - No other `$timestamp` usage exists on Board/Activity partials, so nothing else needed to be left alone deliberately — there was nothing else to touch.
