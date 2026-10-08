# Step 2: Feature Description — Omit Duplicate Preview Line from Thread List Card

## Problem
On the board and tag listing pages, each thread's preview line is the body's first line, so it repeats the title whenever a poster's first body line matches their subject.

## User Stories
- As a reader browsing the board or a tag page, I don't want to see a preview line that just repeats the thread's title, so the list stays compact and non-redundant.
- As a reader, I still want to see a preview for threads where it adds real information (i.e. the first line differs from the title).

## Core Requirements
- In `templates/partials/thread_card.php`, compare the preview (`$thread['body_preview']`, trimmed) to the displayed title (trimmed, case-sensitive); if they match exactly, omit the `<p class="thread-card__preview">` line for that thread. Otherwise render it exactly as today.
- No change to how the preview text itself is computed or escaped — only whether the line is shown.
- If the title came from the automatic body-excerpt fallback (no subject), it never exactly matches the raw preview line, since the fallback title is truncated with `...` — so the preview still renders in that case, same as today.
- No other line in the card (title, byline, labels, reply count) changes.

## Shared Component Inventory
- `templates/partials/thread_card.php` is used by exactly two pages: `templates/pages/board.php` and `templates/pages/tag.php` — both get this change automatically since they share the one partial.
- `templates/partials/paned_board_content_pane.php` (the separate Forte three-pane UI) renders its own preview independently and is out of scope, consistent with the thread-root-card fix staying out of Forte.
- Mirrors the technique already shipped for `templates/partials/thread_root_card.php` (Step 1's Option B) — same trim-and-compare approach, applied to a single preview line instead of a multi-line body remainder.

## Simple User Flow
1. Reader opens the board page or a tag page.
2. For each thread: if its preview line duplicates its title, the card shows title, byline, labels (if any), reply count (if any) — no preview line.
3. For any other thread, the card renders exactly as it does today, preview included.

## Success Criteria
- A thread whose title exactly matches its preview line shows no `<p class="thread-card__preview">` element.
- A thread whose title differs from its preview line renders exactly as it does today, preview included.
- A thread with no subject (excerpt-fallback title) always keeps its preview, since the fallback title can't exactly match the raw preview line.
- The Forte three-pane view's content pane is unaffected.
