# Step 4: Implementation Summary — Omit Duplicate Title Line from Thread Root Card

## Stage 1 - Skip the body's first line when it matches the title
- Changes:
  - In `templates/partials/thread_root_card.php`, computed `$postBodyDisplay`: split the raw body on its first line break, trim the first segment, and compare it to the trimmed title; if equal, render only the remainder through `$br`, otherwise render the full body unchanged
  - `<div class="body">` now renders `$postBodyDisplay` instead of `$post['body']` directly
- Verification:
  - `php -l templates/partials/thread_root_card.php` — no syntax errors
  - Manual check (scratch script, removed after use): rendered a synthetic thread whose subject exactly matched the body's first line — confirmed the title appears once (`<h1>`) and the body starts from the second line, with no duplicate
- Notes:
  - Comparison is on raw (un-escaped) text, consistent with how `$title` is handled elsewhere in this template until render time

## Stage 2 - Automated test coverage
- Changes:
  - Added `LocalAppSmokeTest::testThreadRootCardOmitsDuplicateTitleLine()`, using a temporary copy of the `parity_minimal_v1` fixture repository with three synthetic root posts:
    - subject exactly matches the body's first line → asserts the `<h1>` shows it once and `<div class="body">` starts from the second line, not the duplicate
    - subject equals the entire (single-line) body → asserts an empty `<div class="body"></div>`, no error
    - no subject, body long enough to trigger the truncated excerpt-title fallback (ending in `...`) → asserts the full original body text still renders unchanged (first line not stripped, since the fallback title never exactly matches it)
- Verification:
  - `php -l tests/LocalAppSmokeTest.php` — no syntax errors
  - `php tests/run.php LocalAppSmokeTest::testThreadRootCardOmitsDuplicateTitleLine` — 1 run, 1 passed
  - `php tests/run.php` (full suite) — 577 run, 571 passed, 6 failed; all 6 failures are pre-existing/long-standing (tracked failing since 2026-09-25, before this branch existed), unrelated to this feature — no new failures introduced
- Notes:
  - The existing `root-001` fixture (title "Hello world" vs. first body line "First line preview.") already exercises the non-matching case elsewhere in the suite and continues to pass unchanged
  - Matches the Step 3 plan's minimum commit count: 1 planning-doc commit + 2 stage commits = 3 total
