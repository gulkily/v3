# Step 2: Feature Description — Thread Root Card Meta Reorder

## Problem
On the thread page, the root post's byline, labels, and reply count sit between the title and the body, pushing the body away from the title it belongs to and hurting readability (e.g. long-form posts like poems).

## User Stories
- As a reader, I want the title and body of a thread's root post to sit next to each other, so the content reads naturally without metadata interrupting it.
- As a reader, I still want to see who posted it, when, its labels, and the reply count — just after the body instead of before it.

## Core Requirements
- In `templates/partials/thread_root_card.php`, move the `by … on …` byline line, the `Labels: …` line, and the `N replies`/`1 reply` line to appear after `<div class="body">`, before the action button row.
- Preserve each line's existing conditional visibility (labels only when present, reply count only when > 0) and exact text/markup — this is a reorder, not a rewording.
- The agent-authored-reply marker (`Agent-authored reply`) that currently sits with this group is out of scope; leave its position as-is unless doing so breaks adjacent markup.
- No visual/styling changes beyond the reorder itself (no new CSS).

## Shared Component Inventory
- `templates/partials/thread_root_card.php` is the single template that renders a thread's root post on the thread page — the only place this reorder applies.
- `templates/partials/thread_card.php` (board/tag list preview card) and `templates/partials/post_card.php` (reply posts) are separate, distinct components with their own meta ordering; they are not touched, since the request is scoped to the thread page's root post only.

## Simple User Flow
1. Reader opens a thread page (e.g. `/threads/thread-20260923043029-16d220e9`).
2. Root post renders: title, then body immediately below it.
3. Byline, labels, and reply count appear together after the body, before the reply/reaction buttons.

## Success Criteria
- On any thread page, the root post's `<h1>` title is immediately followed by `<div class="body">` with no `<p class="meta">` lines in between.
- Byline, labels (when present), and reply count (when > 0) all appear after the body and before the button row, in their original relative order and with unchanged text.
- No other page (board, tags, replies) changes appearance.
