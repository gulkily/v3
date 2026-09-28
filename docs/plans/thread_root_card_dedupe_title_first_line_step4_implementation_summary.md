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
