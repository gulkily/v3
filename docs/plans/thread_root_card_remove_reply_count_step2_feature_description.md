# Step 2: Feature Description — Remove Reply Count from Thread Root Card

## Problem
The root post's reply count (`N replies`/`1 reply`) on the thread page adds a metadata line that isn't needed there — the actual replies are visible right below on the same page.

## User Stories
- As a reader viewing a thread page, I don't need a reply count restated above the reply list I'm already looking at, so removing it reduces clutter in the root post's metadata block.

## Core Requirements
- In `templates/partials/thread_root_card.php`, remove the reply-count `<p class="meta">` line (and its `reply_count > 0` conditional) from the root post block entirely.
- No other line in the meta block (byline, labels, agent-authored marker) changes.
- Reply count elsewhere (e.g. board/tag list cards in `thread_card.php`) is out of scope and stays as-is — this only affects the thread page's root post.

## Shared Component Inventory
- `templates/partials/thread_root_card.php` is the single template rendering the thread page's root post; it's the only place this line is removed from.
- `templates/partials/thread_card.php` (board/tag list preview) renders its own separate reply-count line for a different context (list of threads) and is not touched.

## Simple User Flow
1. Reader opens a thread page.
2. Root post renders with byline, labels (if any), and the agent-authored marker (if applicable) — no reply count line.
3. Reader scrolls down and sees the actual replies, same as before.

## Success Criteria
- On any thread page, the root post's meta block no longer contains a reply-count line, regardless of how many replies exist.
- Byline, labels, and agent-authored marker still render exactly as before.
- Board and tag list pages still show reply counts on thread preview cards, unchanged.
